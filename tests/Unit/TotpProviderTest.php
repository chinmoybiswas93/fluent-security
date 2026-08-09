<?php

namespace FluentAuth\Tests\Unit;

use FluentAuth\App\Services\TwoFa\TotpProvider;

/**
 * The generator has to agree with every authenticator app on earth, and the only way
 * to know it does is the published test vectors - an implementation that is
 * self-consistent but wrong produces codes that never match anyone's phone.
 */
class TotpProviderTest extends BaseTestCase
{
    /**
     * The secret RFC 6238 uses, "12345678901234567890" in base32.
     */
    const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    /**
     * Appendix B of RFC 6238, truncated to the six digits authenticator apps show.
     *
     * @return array
     */
    public function rfcVectors()
    {
        return [
            [59, '287082'],
            [1111111109, '081804'],
            [1111111111, '050471'],
            [1234567890, '005924'],
            [2000000000, '279037'],
            [20000000000, '353130']
        ];
    }

    /**
     * @dataProvider rfcVectors
     */
    public function testItMatchesTheRfcTestVectors($timestamp, $expected)
    {
        $counter = (int)floor($timestamp / TotpProvider::PERIOD);

        $this->assertSame($expected, TotpProvider::getCodeForCounter(self::RFC_SECRET, $counter));
    }

    public function testItAcceptsTheCurrentCode()
    {
        $timestamp = 1111111111;
        $counter = (int)floor($timestamp / TotpProvider::PERIOD);

        $this->assertSame($counter, TotpProvider::verify(self::RFC_SECRET, '050471', 0, $timestamp));
    }

    public function testItToleratesClockDriftInBothDirections()
    {
        $timestamp = 1111111111;
        $counter = (int)floor($timestamp / TotpProvider::PERIOD);

        $previous = TotpProvider::getCodeForCounter(self::RFC_SECRET, $counter - 1);
        $next = TotpProvider::getCodeForCounter(self::RFC_SECRET, $counter + 1);

        $this->assertSame($counter - 1, TotpProvider::verify(self::RFC_SECRET, $previous, 0, $timestamp));
        $this->assertSame($counter + 1, TotpProvider::verify(self::RFC_SECRET, $next, 0, $timestamp));
    }

    public function testItRejectsAnythingFurtherOutThanTheWindow()
    {
        $timestamp = 1111111111;
        $counter = (int)floor($timestamp / TotpProvider::PERIOD);

        $tooOld = TotpProvider::getCodeForCounter(self::RFC_SECRET, $counter - 2);
        $tooNew = TotpProvider::getCodeForCounter(self::RFC_SECRET, $counter + 2);

        $this->assertFalse(TotpProvider::verify(self::RFC_SECRET, $tooOld, 0, $timestamp));
        $this->assertFalse(TotpProvider::verify(self::RFC_SECRET, $tooNew, 0, $timestamp));
    }

    /**
     * A code is valid for a minute and a half either side of its step, so without this
     * one seen over a shoulder or captured by a phishing proxy can simply be used again.
     */
    public function testASpentStepCannotBeReplayed()
    {
        $timestamp = 1111111111;
        $counter = (int)floor($timestamp / TotpProvider::PERIOD);

        $this->assertSame($counter, TotpProvider::verify(self::RFC_SECRET, '050471', 0, $timestamp));

        $this->assertFalse(
            TotpProvider::verify(self::RFC_SECRET, '050471', $counter, $timestamp),
            'The same code must not work twice.'
        );
    }

    /**
     * Accepting an earlier step after a later one has been spent would leave a window
     * of already used codes live again.
     */
    public function testStepsBeforeTheSpentOneAreAlsoClosed()
    {
        $timestamp = 1111111111;
        $counter = (int)floor($timestamp / TotpProvider::PERIOD);

        $previous = TotpProvider::getCodeForCounter(self::RFC_SECRET, $counter - 1);

        $this->assertFalse(TotpProvider::verify(self::RFC_SECRET, $previous, $counter, $timestamp));
    }

    public function testItIgnoresHowTheUserSpacedTheCode()
    {
        $timestamp = 1111111111;
        $counter = (int)floor($timestamp / TotpProvider::PERIOD);

        $this->assertSame($counter, TotpProvider::verify(self::RFC_SECRET, '050 471', 0, $timestamp));
        $this->assertSame($counter, TotpProvider::verify(self::RFC_SECRET, ' 050471 ', 0, $timestamp));
    }

    public function testItRejectsCodesOfTheWrongLength()
    {
        $this->assertFalse(TotpProvider::verify(self::RFC_SECRET, '05047', 0, 1111111111));
        $this->assertFalse(TotpProvider::verify(self::RFC_SECRET, '0504711', 0, 1111111111));
        $this->assertFalse(TotpProvider::verify(self::RFC_SECRET, '', 0, 1111111111));
    }

    public function testGeneratedSecretsAreUsableAndUnique()
    {
        $first = TotpProvider::generateSecret();
        $second = TotpProvider::generateSecret();

        $this->assertTrue(TotpProvider::isValidSecret($first));
        $this->assertNotSame($first, $second);

        // 160 bits in base32.
        $this->assertSame(32, strlen($first));
    }

    public function testASecretRoundTripsThroughTheAlgorithm()
    {
        $secret = TotpProvider::generateSecret();

        $code = TotpProvider::getCodeForCounter($secret, 42);

        $this->assertSame(6, strlen($code));
        $this->assertSame($code, TotpProvider::getCodeForCounter($secret, 42));
        $this->assertNotSame($code, TotpProvider::getCodeForCounter($secret, 43));
    }

    public function testInvalidSecretsAreRefusedRatherThanGuessedAt()
    {
        $this->assertFalse(TotpProvider::isValidSecret(''));
        $this->assertFalse(TotpProvider::isValidSecret('not base32 at all!'));
        $this->assertFalse(TotpProvider::isValidSecret('ABC'));           // too short
        $this->assertFalse(TotpProvider::isValidSecret('GEZDGNBVGY3TQOJ1')); // 1 is not in the alphabet
    }

    public function testTheProvisioningUriCarriesWhatAnAppNeeds()
    {
        $uri = TotpProvider::getProvisioningUri(self::RFC_SECRET, 'jane', 'Acme Store');

        $this->assertStringStartsWith('otpauth://totp/Acme%20Store:jane?', $uri);
        $this->assertStringContainsString('secret=' . self::RFC_SECRET, $uri);
        $this->assertStringContainsString('algorithm=SHA1', $uri);
        $this->assertStringContainsString('digits=6', $uri);
        $this->assertStringContainsString('period=30', $uri);
        $this->assertStringContainsString('issuer=Acme%20Store', $uri);
    }

    /**
     * The label is issuer:account, so a colon inside either half would split it in the
     * wrong place and some apps would show the account under the wrong name.
     */
    public function testAColonInTheSiteNameCannotBreakTheLabel()
    {
        $uri = TotpProvider::getProvisioningUri(self::RFC_SECRET, 'jane', 'Acme: The Store');

        $label = substr(explode('?', $uri)[0], strlen('otpauth://totp/'));

        $this->assertSame(1, substr_count($label, ':'), 'The label must have exactly one issuer/account separator.');
        $this->assertStringEndsWith(':jane', $label);
    }
}
