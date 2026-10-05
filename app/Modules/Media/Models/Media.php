<?php

declare(strict_types=1);

namespace App\Modules\Media\Models;

use App\Modules\Media\Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Yek file-e ketabkhane-ye rasane. `path` nesbi be `disk`-e; URL-e omoomi az Storage sakhte mishe
 * ta ja-be-ja kardan-e disk (local → S3) hich radif-i ro avaz nakone.
 *
 * @property int $id
 * @property string $disk
 * @property string $path
 * @property string $name
 * @property string $mime
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property string $alt
 * @property string|null $title
 * @property array{webp?: string, thumb?: string, srcset?: list<array{w: int, path: string}>}|null $derivatives
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $usages_count
 */
final class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    protected $table = 'media';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'derivatives' => 'array',
        ];
    }

    /** @return HasMany<MediaUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(MediaUsage::class);
    }

    public function isSvg(): bool
    {
        return $this->mime === 'image/svg+xml';
    }

    /** URL-e omoomi-ye file-e asli. */
    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /** URL-e thumbnail-e 400px (WebP); SVG hamun asl-e. */
    public function thumbUrl(): string
    {
        $thumb = $this->derivatives['thumb'] ?? null;

        return $thumb === null ? $this->url() : Storage::disk($this->disk)->url($thumb);
    }

    /** URL-e WebP-e ham-andaze ba asl, ya khod-e asl vaghti moshtagh nadare. */
    public function webpUrl(): string
    {
        $webp = $this->derivatives['webp'] ?? null;

        return $webp === null ? $this->url() : Storage::disk($this->disk)->url($webp);
    }

    /** `srcset` amade baraye `<img>`: `url 400w, url 800w, …` ya khali baraye SVG. */
    public function srcset(): string
    {
        $parts = [];

        foreach ($this->derivatives['srcset'] ?? [] as $entry) {
            $parts[] = Storage::disk($this->disk)->url($entry['path']).' '.$entry['w'].'w';
        }

        return implode(', ', $parts);
    }

    /**
     * Hame-ye masir-hayi ke rooye disk be in radif ta'alogh daran (asl + moshtaghat).
     *
     * @return list<string>
     */
    public function allPaths(): array
    {
        $paths = [$this->path];

        foreach (['webp', 'thumb'] as $key) {
            if (isset($this->derivatives[$key])) {
                $paths[] = $this->derivatives[$key];
            }
        }

        foreach ($this->derivatives['srcset'] ?? [] as $entry) {
            $paths[] = $entry['path'];
        }

        return array_values(array_unique($paths));
    }

    protected static function newFactory(): MediaFactory
    {
        return MediaFactory::new();
    }
}
