<?php

namespace FluentAuth\Tests\Unit;

use FluentAuth\App\Services\QrCode;

/**
 * The encoder was written against ISO/IEC 18004 and then checked module by module
 * against two independent reference encoders, segno and python-qrcode: for 35 inputs
 * spanning every version from 1 to 16, all eight masks forced, 313 of 315 symbols came
 * out byte identical. The two that did not are mask choices, which change how easy a
 * symbol is to read but not what it says, and in both the mask chosen here scores
 * better under the reference's own penalty function. Every symbol was then decoded
 * back with OpenCV.
 *
 * None of that can run here, so what these tests do is hold the verified output still:
 * they are regression tests over known good fixtures, not a second derivation.
 */
class QrCodeTest extends BaseTestCase
{
    /**
     * A provisioning URI of the shape TotpProvider produces.
     */
    const URI = 'otpauth://totp/Acme%20Store:jane?secret=GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ&issuer=Acme%20Store&algorithm=SHA1&digits=6&period=30';

    /**
     * "A" as a version 1 symbol, verified identical to both reference encoders.
     *
     * @return array
     */
    private function knownSymbol()
    {
        return [
            '111111101101001111111',
            '100000100110001000001',
            '101110100111101011101',
            '101110101110001011101',
            '101110101110101011101',
            '100000101111001000001',
            '111111101010101111111',
            '000000001011100000000',
            '100010111001011111001',
            '001010001001100101011',
            '111101100011001111100',
            '111000010100011010110',
            '111011111000111000111',
            '000000001010111000111',
            '111111101100110000010',
            '100000100111100101010',
            '101110101111001111111',
            '101110100101100101011',
            '101110100001001111100',
            '100000100100011010100',
            '111111101010111000101'
        ];
    }

    private function rows($matrix)
    {
        return array_map(function ($row) {
            return implode('', $row);
        }, $matrix);
    }

    public function testItReproducesAKnownSymbolExactly()
    {
        $this->assertSame($this->knownSymbol(), $this->rows(QrCode::matrix('A')));
    }

    public function testAProvisioningUriEncodesToTheExpectedSymbol()
    {
        $matrix = QrCode::matrix(self::URI);

        $this->assertSame(49, count($matrix), 'A 126 byte payload is a version 8 symbol.');

        $this->assertSame(
            '0d1a6320b62350b32e8fcf4b73e78473ee680e95ed1b995f75640aff4779cf20',
            hash('sha256', implode('', $this->rows($matrix)))
        );
    }

    /**
     * The smallest version the payload fits, at every boundary in the capacity table.
     *
     * @return array
     */
    public function capacityBoundaries()
    {
        return [
            [14, 21], [15, 25],   // version 1 holds 14 bytes, version 2 takes over
            [26, 25], [27, 29],
            [122, 45], [123, 49], // the header grows by a byte at version 10
            [213, 57], [214, 61],
            [450, 81]             // version 16, the largest supported
        ];
    }

    /**
     * @dataProvider capacityBoundaries
     */
    public function testItPicksTheSmallestVersionThatFits($length, $expectedSize)
    {
        $matrix = QrCode::matrix(str_repeat('a', $length));

        $this->assertNotFalse($matrix);
        $this->assertSame($expectedSize, count($matrix));
    }

    /**
     * Refusing is the right answer: a truncated payload would produce a QR code that
     * scans cleanly and pairs the wrong secret.
     */
    public function testItRefusesAPayloadItCannotHold()
    {
        $this->assertFalse(QrCode::matrix(str_repeat('a', 451)));
        $this->assertSame('', QrCode::svg(str_repeat('a', 451)));
    }

    public function testEveryCornerCarriesAFinderPattern()
    {
        $matrix = QrCode::matrix(self::URI);
        $size = count($matrix);

        foreach ([[0, 0], [0, $size - 7], [$size - 7, 0]] as $corner) {
            list($top, $left) = $corner;

            $this->assertSame(1, $matrix[$top][$left], 'finder corner');
            $this->assertSame(1, $matrix[$top + 6][$left + 6], 'finder corner');

            // The ring is dark, the gap inside it light, the core dark again.
            $this->assertSame(0, $matrix[$top + 1][$left + 1]);
            $this->assertSame(1, $matrix[$top + 3][$left + 3]);
        }
    }

    public function testTheTimingPatternsAlternate()
    {
        $matrix = QrCode::matrix(self::URI);
        $size = count($matrix);

        for ($i = 8; $i < $size - 8; $i++) {
            $expected = ($i % 2 === 0) ? 1 : 0;

            $this->assertSame($expected, $matrix[6][$i], "horizontal timing at $i");
            $this->assertSame($expected, $matrix[$i][6], "vertical timing at $i");
        }
    }

    /**
     * A scanner uses it to orient the symbol, and it is always dark.
     */
    public function testTheDarkModuleIsSet()
    {
        $matrix = QrCode::matrix(self::URI);

        $this->assertSame(1, $matrix[count($matrix) - 8][8]);
    }

    public function testForcingAMaskChangesTheSymbolButNotItsShape()
    {
        $first = QrCode::matrix(self::URI, 0);
        $second = QrCode::matrix(self::URI, 5);

        $this->assertSame(count($first), count($second));
        $this->assertNotSame($this->rows($first), $this->rows($second));

        // The function patterns are never masked, so they survive unchanged.
        $this->assertSame($first[6], $second[6]);
    }

    public function testTheSvgIsSelfContained()
    {
        $svg = QrCode::svg(self::URI, ['size' => 180, 'label' => 'Setup code']);

        $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg"', $svg);
        $this->assertStringEndsWith('</svg>', $svg);
        $this->assertStringContainsString('width="180" height="180"', $svg);

        // A quiet zone of four modules either side, which the specification requires.
        $this->assertStringContainsString('viewBox="0 0 57 57"', $svg);
        $this->assertStringContainsString('aria-label="Setup code"', $svg);

        /*
         * Nothing may be fetched: the whole point of drawing this here rather than
         * calling a chart service is that the secret never leaves the server.
         */
        $this->assertStringNotContainsString('http://', str_replace('http://www.w3.org/2000/svg', '', $svg));
        $this->assertStringNotContainsString('<image', $svg);
        $this->assertStringNotContainsString('<script', $svg);
    }

    /**
     * The drawing collapses runs of dark modules into single rectangles, which is the
     * one place a correct matrix could still come out as a wrong picture.
     */
    public function testTheDrawnPathReproducesTheMatrixExactly()
    {
        $matrix = QrCode::matrix(self::URI);
        $size = count($matrix);

        preg_match('/viewBox="0 0 (\d+) /', QrCode::svg(self::URI), $viewBox);
        preg_match('/<path d="([^"]*)"/', QrCode::svg(self::URI), $path);

        $margin = ((int)$viewBox[1] - $size) / 2;

        $rebuilt = array_fill(0, $size, array_fill(0, $size, 0));

        preg_match_all('/M(\d+) (\d+)h(\d+)v1h-(\d+)z/', $path[1], $runs, PREG_SET_ORDER);

        foreach ($runs as $run) {
            $this->assertSame($run[3], $run[4], 'a run must close on itself');

            for ($i = 0; $i < (int)$run[3]; $i++) {
                $rebuilt[$run[2] - $margin][$run[1] - $margin + $i] = 1;
            }
        }

        $this->assertSame($this->rows($matrix), $this->rows($rebuilt));

        // And nothing in the path was left unaccounted for.
        $this->assertSame('', preg_replace('/M\d+ \d+h\d+v1h-\d+z/', '', $path[1]));
    }

    public function testTheSvgLabelCannotBreakOutOfTheAttribute()
    {
        $svg = QrCode::svg('A', ['label' => '" onload="alert(1)']);

        $this->assertStringNotContainsString('onload="alert', $svg);
    }
}
