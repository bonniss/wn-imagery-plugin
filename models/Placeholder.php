<?php namespace Bonniss26\Imagery\Models;

use Winter\Storm\Database\Model;

/**
 * One cached placeholder - or one recorded failure - per cache key.
 *
 * @property string $cache_key See Classes\CacheKey.
 * @property string $source A Classes\Source key: "media:/a.jpg", "file:42", "path:/abs".
 * @property string $identity What the source looked like when this was written.
 * @property string $mode
 * @property string $status "ok" or "failed".
 * @property string|null $kind LqipResult::KIND_*.
 * @property string|null $value
 * @property string|null $property
 * @property int $attempts
 * @property \Carbon\Carbon|null $retry_at
 */
class Placeholder extends Model
{
    public $table = 'bonniss26_imagery_placeholders';

    protected $guarded = ['id'];

    protected $dates = ['retry_at'];

    protected $casts = [
        'attempts' => 'integer',
    ];
}
