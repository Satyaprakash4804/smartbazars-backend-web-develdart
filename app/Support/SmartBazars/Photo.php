<?php

namespace App\Support\SmartBazars;

use App\Enums\SpatieMediaCollectionName;
use App\Models\User;

/** Profile photo helpers for the Agent / Worker ID card. */
class Photo
{
    /**
     * Re-encode an uploaded photo as a JPEG (max 640px, phone-rotation fixed) when GD exists.
     * Returns the path to use (a temp file, or the original when GD is missing).
     */
    public static function normalize(string $path): string
    {
        if (!function_exists('imagecreatetruecolor') || !($info = @getimagesize($path))) {
            return $path;
        }
        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
        if (!$img) return $path;

        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $rot = match ((int)($exif['Orientation'] ?? 1)) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
            if ($rot) $img = imagerotate($img, $rot, 0) ?: $img;
        }

        $w = imagesx($img); $h = imagesy($img);
        $max = 640;
        if (max($w, $h) > $max) {
            $r = $max / max($w, $h);
            $img = imagescale($img, (int)round($w * $r), (int)round($h * $r)) ?: $img;
        }
        // flatten transparency onto white (PNG / WEBP)
        $flat = imagecreatetruecolor(imagesx($img), imagesy($img));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));

        $tmp = tempnam(sys_get_temp_dir(), 'sbph') . '.jpg';
        imagejpeg($flat, $tmp, 86);
        return $tmp;
    }

    /** Same-origin URL for the logged-in partner's photo (works regardless of APP_URL / storage symlink). */
    public static function url(?User $user): ?string
    {
        $media = $user?->getFirstMedia(SpatieMediaCollectionName::PROFILE_IMAGE());
        return $media ? route('partner.photo', ['v' => $media->id]) : null;
    }

    /** @return array{uri: string, w: int, h: int}|null  data URI + size, read through the media disk */
    public static function forUser(?User $user): ?array
    {
        $media = $user?->getFirstMedia(SpatieMediaCollectionName::PROFILE_IMAGE());
        if (!$media) return null;
        try {
            $bin = stream_get_contents($media->stream());
            $info = $bin ? @getimagesizefromstring($bin) : false;
            if (!$info) return null;
            return ['uri' => 'data:' . $info['mime'] . ';base64,' . base64_encode($bin), 'w' => (int)$info[0], 'h' => (int)$info[1]];
        } catch (\Throwable) {
            return null;
        }
    }

    /** "object-fit: cover" for dompdf (which has no object-fit): image size + offsets inside a $bw x $bh box. */
    public static function cover(array $photo, int $bw, int $bh): array
    {
        $scale = max($bw / $photo['w'], $bh / $photo['h']);
        $iw = $photo['w'] * $scale;
        $ih = $photo['h'] * $scale;
        return $photo + ['iw' => round($iw, 2), 'ih' => round($ih, 2), 'left' => round(($bw - $iw) / 2, 2), 'top' => round(($bh - $ih) / 2, 2)];
    }
}
