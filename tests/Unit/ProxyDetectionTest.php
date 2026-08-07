<?php

namespace FluentAuth\Tests\Unit;

use FluentAuth\App\Helpers\Helper;
use FluentAuth\App\Services\ProxyDetection;

/**
 * Detection decides whether a settings panel is shown, never who is trusted, so being
 * wrong is a presentation problem rather than a security one - but only in one
 * direction. Claiming a proxy that is not there costs a panel nobody needed. Missing
 * one that is there hides the only screen that can fix a site where every visitor is
 * being recorded as the same address, so the tests lean on that side.
 */
class ProxyDetectionTest extends BaseTestCase
{
    private $serverBackup;

    public function setUp(): void
    {
        parent::setUp();

        $this->serverBackup = $_SERVER;

        foreach (array_keys($_SERVER) as $key) {
            if (strpos($key, 'HTTP_') === 0) {
                unset($_SERVER[$key]);
            }
        }

        $this->setProxies('');
    }

    public function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        parent::tearDown();
    }

    private function setProxies($value)
    {
        $settings = Helper::getAuthSettings();
        $settings['trusted_proxies'] = $value;
        update_option('__fls_auth_settings', $settings);
        Helper::resetStatics();
    }

    private function detectFrom($remoteAddr, $headers = [])
    {
        $_SERVER['REMOTE_ADDR'] = $remoteAddr;

        foreach ($headers as $key => $value) {
            $_SERVER[$key] = $value;
        }

        Helper::resetStatics();

        return ProxyDetection::detect();
    }

    /* ------------------------------------------------------------------
     * What counts as an address from inside the network
     * --------------------------------------------------------------- */

    public function privateAddresses()
    {
        return [
            ['127.0.0.1'], ['10.0.0.5'], ['172.16.4.9'], ['172.31.255.254'],
            ['192.168.1.1'], ['169.254.10.10'],
            ['100.64.0.1'], ['100.127.255.255'], // carrier grade NAT, used by containers
            ['::1'], ['fc00::1'], ['fd12:3456::1'], ['fe80::1']
        ];
    }

    /**
     * @dataProvider privateAddresses
     */
    public function testAddressesInsideTheNetworkAreRecognised($ip)
    {
        $this->assertTrue(ProxyDetection::isPrivateOrLocal($ip), $ip . ' should count as internal');
    }

    public function publicAddresses()
    {
        return [
            ['8.8.8.8'], ['1.1.1.1'], ['203.0.113.9'],
            ['100.63.255.255'], ['100.128.0.0'], // either side of the CGNAT block
            ['172.15.0.1'], ['172.32.0.1'],      // either side of the private block
            ['2606:4700:4700::1111']
        ];
    }

    /**
     * @dataProvider publicAddresses
     */
    public function testRoutableAddressesAreNotMistakenForInternalOnes($ip)
    {
        $this->assertFalse(ProxyDetection::isPrivateOrLocal($ip), $ip . ' should count as public');
    }

    public function testGarbageIsNotInternal()
    {
        $this->assertFalse(ProxyDetection::isPrivateOrLocal(''));
        $this->assertFalse(ProxyDetection::isPrivateOrLocal('not-an-ip'));
        $this->assertFalse(ProxyDetection::isPrivateOrLocal('10.0.0'));
    }

    /* ------------------------------------------------------------------
     * The verdict
     * --------------------------------------------------------------- */

    /**
     * The source address of the connection is the one thing a remote visitor cannot
     * write, so a private one means something local relayed the request.
     */
    public function testAPrivateConnectionAddressIsConclusive()
    {
        $result = $this->detectFrom('10.0.0.7');

        $this->assertSame(ProxyDetection::STATUS_DETECTED, $result['status']);
        $this->assertTrue($result['remote_is_private']);
    }

    public function testADirectPublicRequestReportsNoProxy()
    {
        $result = $this->detectFrom('203.0.113.9');

        $this->assertSame(ProxyDetection::STATUS_NONE, $result['status']);
        $this->assertFalse($result['needs_attention']);
        $this->assertSame('', $result['suggested_proxy']);
    }

    /**
     * Anyone can send this header, so on its own it is a hint. Reporting it as a
     * finding would let a visitor decide what an administrator's settings screen says.
     */
    public function testAForwardedHeaderOverAPublicConnectionIsOnlyAHint()
    {
        $result = $this->detectFrom('203.0.113.9', ['HTTP_X_FORWARDED_FOR' => '1.2.3.4']);

        $this->assertSame(ProxyDetection::STATUS_POSSIBLE, $result['status']);
        $this->assertFalse($result['needs_attention'], 'A hint must not raise an alarm.');
    }

    public function testCloudflareIsRecognisedAndNeedsNoConfiguration()
    {
        $result = $this->detectFrom('173.245.48.1', ['HTTP_CF_CONNECTING_IP' => '1.2.3.4']);

        $this->assertSame(ProxyDetection::STATUS_CLOUDFLARE, $result['status']);
        $this->assertFalse($result['needs_attention']);
        $this->assertSame('', $result['suggested_header'], 'Cloudflare needs no header setting.');
    }

    /**
     * The header alone proves nothing about Cloudflare either - the connection has to
     * have come from one of their edges.
     */
    public function testTheCloudflareHeaderFromElsewhereIsNotCloudflare()
    {
        $result = $this->detectFrom('203.0.113.9', ['HTTP_CF_CONNECTING_IP' => '1.2.3.4']);

        $this->assertNotSame(ProxyDetection::STATUS_CLOUDFLARE, $result['status']);
    }

    /* ------------------------------------------------------------------
     * The warning worth interrupting somebody for
     * --------------------------------------------------------------- */

    public function testAnUndeclaredProxyRaisesTheAlarm()
    {
        $result = $this->detectFrom('10.0.0.7', ['HTTP_X_FORWARDED_FOR' => '1.2.3.4']);

        $this->assertTrue(
            $result['needs_attention'],
            'A proxy nobody declared means every visitor is recorded as one address.'
        );
        $this->assertSame('10.0.0.7', $result['suggested_proxy']);
        $this->assertSame('X-Forwarded-For', $result['suggested_header']);
    }

    public function testDeclaringTheProxyClearsTheAlarm()
    {
        $this->setProxies('10.0.0.7');

        $result = $this->detectFrom('10.0.0.7', ['HTTP_X_FORWARDED_FOR' => '1.2.3.4']);

        $this->assertSame(ProxyDetection::STATUS_DETECTED, $result['status']);
        $this->assertTrue($result['configured']);
        $this->assertFalse($result['needs_attention']);
    }

    /* ------------------------------------------------------------------
     * Reporting attacker supplied values back to an admin screen
     * --------------------------------------------------------------- */

    public function testHeaderValuesAreCutShortBeforeBeingReported()
    {
        $result = $this->detectFrom('10.0.0.7', [
            'HTTP_X_FORWARDED_FOR' => str_repeat('9.9.9.9, ', 200)
        ]);

        $this->assertNotEmpty($result['headers']);
        $this->assertLessThanOrEqual(123, strlen($result['headers'][0]['value']));
    }

    public function testHeaderValuesAreSanitisedBeforeBeingReported()
    {
        $result = $this->detectFrom('10.0.0.7', [
            'HTTP_X_FORWARDED_FOR' => "1.2.3.4<script>alert(1)</script>"
        ]);

        $this->assertStringNotContainsString('<script>', $result['headers'][0]['value']);
    }

    public function testAKnownVendorHeaderIsNamed()
    {
        $result = $this->detectFrom('10.0.0.7', ['HTTP_X_SUCURI_CLIENTIP' => '1.2.3.4']);

        $this->assertSame('Sucuri', $result['vendor']);
    }

    /**
     * Detection is advice. If it ever started writing to the trusted list, a visitor
     * able to influence these headers would be choosing who the site believes.
     */
    public function testDetectingAProxyDoesNotTrustIt()
    {
        $this->detectFrom('10.0.0.7', ['HTTP_X_FORWARDED_FOR' => '1.2.3.4']);

        $this->assertSame([], Helper::getTrustedProxies());
        $this->assertSame('10.0.0.7', Helper::getIp(), 'The connection address still wins.');
    }
}
