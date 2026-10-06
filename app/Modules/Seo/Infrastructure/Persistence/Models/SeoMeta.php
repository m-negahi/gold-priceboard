<?php

declare(strict_types=1);

namespace App\Modules\Seo\Infrastructure\Persistence\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Override e SEO e yek hadaf (P5-05) — polymorphic ba kelid e reshte-i.
 *
 * @property int $id
 * @property string $seoable_type masalan `page`، `instrument`
 * @property string $seoable_id masalan `home`، `xau-18k-g-irr`
 * @property string|null $title
 * @property string|null $description
 * @property string|null $canonical_url
 * @property string|null $robots
 * @property string|null $og_image
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class SeoMeta extends Model
{
    protected $table = 'seo_meta';

    protected $fillable = [
        'seoable_type', 'seoable_id', 'title', 'description',
        'canonical_url', 'robots', 'og_image',
    ];
}
