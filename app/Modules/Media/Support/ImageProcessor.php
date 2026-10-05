<?php

declare(strict_types=1);

namespace App\Modules\Media\Support;

use GdImage;
use RuntimeException;

/**
 * Moshtaghat-e yek tasvir-e raster ba GD (container Imagick nadare, GD ba WebP dare):
 * yek WebP-e ham-andaze, yek thumbnail-e 400px, va nosakh-haye srcset (400/800/1200 — faghat
 * unhayi ke az asl kuchik-tar-and, chon bozorg kardan chizi be kasi nemide). Stateless-e;
 * masir-e mutlagh migire, file minevise, va masir-haye nesbi bar migardoone.
 */
final class ImageProcessor
{
    public const THUMB_WIDTH = 400;

    /** @var list<int> */
    public const SRCSET_WIDTHS = [400, 800, 1200];

    private const WEBP_QUALITY = 82;

    /** Saghf-e pixel (arz × ertefa') baraye decode: 25 MP ≈ 100 MB RAM tu GD. Form request ham 8000px/zel' ro rad mikone. */
    public const MAX_PIXELS = 25_000_000;

    /** Mime-hayi ke GD mitoone bekhune; SVG amdan nist — vector be WebP tabdil nemishe. */
    public const RASTER_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /** @return array{width: int, height: int}|null */
    public static function dimensions(string $absolutePath, string $mime): ?array
    {
        if ($mime === 'image/svg+xml') {
            return self::svgDimensions($absolutePath);
        }

        $info = @getimagesize($absolutePath);

        if ($info === false) {
            return null;
        }

        return ['width' => (int) $info[0], 'height' => (int) $info[1]];
    }

    /**
     * Moshtaghat ro kenar-e asl minevise. `$relativeBase` masir-e nesbi-ye asl bedoone pasvand-e
     * (masalan `media/2026/09/01abc`); khorooji hamun shekl-e sotoon-e `derivatives`-e.
     *
     * @return array{webp: string, thumb: string, srcset: list<array{w: int, path: string}>}
     */
    public static function derivatives(string $absolutePath, string $mime, string $absoluteBase, string $relativeBase): array
    {
        // Ab'ad az header, ghabl az decode: tasvir-e gonde nabayad be imagecreatefrom* berese.
        $claimed = self::dimensions($absolutePath, $mime);

        if ($claimed === null || $claimed['width'] * $claimed['height'] > self::MAX_PIXELS) {
            throw new RuntimeException(sprintf('Image exceeds the %d megapixel limit or has no readable header.', intdiv(self::MAX_PIXELS, 1_000_000)));
        }

        $source = self::open($absolutePath, $mime);
        $width = imagesx($source);
        $height = imagesy($source);

        imagepalettetotruecolor($source);
        imagealphablending($source, false);
        imagesavealpha($source, true);

        self::writeWebp($source, $absoluteBase.'.webp');

        $srcset = [];

        foreach (self::SRCSET_WIDTHS as $target) {
            if ($target >= $width) {
                continue;
            }

            self::writeWebp(self::resize($source, $width, $height, $target), $absoluteBase.'-'.$target.'.webp');
            $srcset[] = ['w' => $target, 'path' => $relativeBase.'-'.$target.'.webp'];
        }

        // Thumb hamun nosakh-e 400-e; asl-e kuchik-tar az 400 khodesh thumb-esh-e.
        $thumb = $width > self::THUMB_WIDTH ? $relativeBase.'-'.self::THUMB_WIDTH.'.webp' : $relativeBase.'.webp';

        return ['webp' => $relativeBase.'.webp', 'thumb' => $thumb, 'srcset' => $srcset];
    }

    private static function open(string $path, string $mime): GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };

        if ($image === false) {
            throw new RuntimeException("GD could not decode {$mime}.");
        }

        return $image;
    }

    private static function resize(GdImage $source, int $width, int $height, int $targetWidth): GdImage
    {
        $targetHeight = max(1, (int) round($height * $targetWidth / $width));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($target === false) {
            throw new RuntimeException('GD could not allocate the resized image.');
        }

        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $target;
    }

    private static function writeWebp(GdImage $image, string $path): void
    {
        if (! imagewebp($image, $path, self::WEBP_QUALITY)) {
            throw new RuntimeException("GD could not write {$path}.");
        }
    }

    /**
     * SVG andaze-ye pixel nadare; `width`/`height`-e root ya viewBox agar bashan khunde mishan,
     * vagarna null — ke UI mige «برداری».
     *
     * @return array{width: int, height: int}|null
     */
    private static function svgDimensions(string $path): ?array
    {
        $head = (string) file_get_contents($path, false, null, 0, 4096);

        if (preg_match('/<svg\b[^>]*>/i', $head, $tag) !== 1) {
            return null;
        }

        $w = preg_match('/\swidth="([\d.]+)(px)?"/i', $tag[0], $m) === 1 ? (int) round((float) $m[1]) : null;
        $h = preg_match('/\sheight="([\d.]+)(px)?"/i', $tag[0], $m) === 1 ? (int) round((float) $m[1]) : null;

        if (($w === null || $h === null) && preg_match('/viewBox="[\d.\-]+\s+[\d.\-]+\s+([\d.]+)\s+([\d.]+)"/i', $tag[0], $m) === 1) {
            $w ??= (int) round((float) $m[1]);
            $h ??= (int) round((float) $m[2]);
        }

        return ($w !== null && $h !== null && $w > 0 && $h > 0) ? ['width' => $w, 'height' => $h] : null;
    }
}
