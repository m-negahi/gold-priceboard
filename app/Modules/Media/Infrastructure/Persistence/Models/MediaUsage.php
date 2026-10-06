<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * «In rasane koja estefade shode»: yek radif be-ezaye (model, field). SyncMediaUsageAction
 * minevise, DeleteMediaAction mikhune va rad mikone.
 *
 * @property int $id
 * @property int $media_id
 * @property string $usable_type
 * @property int $usable_id
 * @property string $field
 */
class MediaUsage extends Model
{
    protected $fillable = ['media_id', 'usable_type', 'usable_id', 'field'];

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /** @return MorphTo<Model, $this> */
    public function usable(): MorphTo
    {
        return $this->morphTo();
    }
}
