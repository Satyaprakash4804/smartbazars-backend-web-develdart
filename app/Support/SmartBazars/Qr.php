<?php

namespace App\Support\SmartBazars;

/**
 * Tiny QR helper (bundled kazuhikoarase/qrcode-generator, MIT).
 * svg() works everywhere; png() uses GD when present, else a pure-PHP encoder.
 */
class Qr
{
    protected static function matrix(string $text): array
    {
        class_exists(\QRCode::class); // loads bundled library (defines QR_* constants)
        $qr = \QRCode::getMinimumQRCode($text, QR_ERROR_CORRECT_LEVEL_M);
        $n = $qr->getModuleCount();
        $m = [];
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                $m[$r][$c] = $qr->isDark($r, $c);
            }
        }
        return $m;
    }

    public static function svg(string $text, int $size = 240, string $dark = '#0A2E73', string $light = '#FFFFFF'): string
    {
        $m = self::matrix($text);
        $n = count($m);
        $q = 2; // quiet zone (modules)
        $total = $n + 2 * $q;
        $path = '';
        foreach ($m as $r => $row) {
            foreach ($row as $c => $dk) {
                if ($dk) $path .= 'M' . ($c + $q) . ',' . ($r + $q) . 'h1v1h-1z';
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $total . ' ' . $total . '" shape-rendering="crispEdges">'
            . '<rect width="100%" height="100%" fill="' . $light . '"/><path d="' . $path . '" fill="' . $dark . '"/></svg>';
    }

    /**
     * PNG without the GD extension (pure PHP, 8-bit palette). dompdf and mail clients both handle PNG
     * reliably, unlike SVG, so this is what keeps the ID-card QR correct on servers without GD.
     */
    public static function pngNoGd(string $text, int $size = 360): string
    {
        $m = self::matrix($text);
        $n = count($m);
        $q = 2;
        $scale = max(2, intdiv($size, $n + 2 * $q));
        $total = ($n + 2 * $q) * $scale;
        $raw = '';
        for ($r = -$q; $r < $n + $q; $r++) {
            $line = '';
            for ($c = -$q; $c < $n + $q; $c++) {
                $line .= str_repeat(($r >= 0 && $r < $n && $c >= 0 && $c < $n && $m[$r][$c]) ? "\x01" : "\x00", $scale);
            }
            $raw .= str_repeat("\x00" . $line, $scale); // filter byte 0 + pixels, repeated per scaled row
        }
        $chunk = fn(string $type, string $data) => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
        return "\x89PNG\r\n\x1a\n"
            . $chunk('IHDR', pack('NNCCCCC', $total, $total, 8, 3, 0, 0, 0))
            . $chunk('PLTE', "\xFF\xFF\xFF" . "\x0A\x2E\x73")
            . $chunk('IDAT', gzcompress($raw, 9))
            . $chunk('IEND', '');
    }

    public static function png(string $text, int $size = 360): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return self::pngNoGd($text, $size);
        }
        $m = self::matrix($text);
        $n = count($m);
        $q = 2;
        $scale = max(2, intdiv($size, $n + 2 * $q));
        $px = ($n + 2 * $q) * $scale;
        $img = imagecreatetruecolor($px, $px);
        $white = imagecolorallocate($img, 255, 255, 255);
        $dark = imagecolorallocate($img, 10, 46, 115);
        imagefill($img, 0, 0, $white);
        foreach ($m as $r => $row) {
            foreach ($row as $c => $dk) {
                if ($dk) {
                    imagefilledrectangle($img, ($c + $q) * $scale, ($r + $q) * $scale, ($c + $q + 1) * $scale - 1, ($r + $q + 1) * $scale - 1, $dark);
                }
            }
        }
        ob_start();
        imagepng($img);
        imagedestroy($img);
        return ob_get_clean();
    }

    /** data: URI usable in <img src> — always PNG (GD when available, pure PHP otherwise). */
    public static function dataUri(string $text, int $size = 240): string
    {
        return 'data:image/png;base64,' . base64_encode(self::png($text, $size));
    }
}
