<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\RackPhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $rack_id
 * @property string $photo_path
 * @property string|null $caption
 * @property int|null $created_by
 */
class RackPhoto extends Model
{
    /** @use HasFactory<RackPhotoFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'rack_id',
        'photo_path',
        'caption',
        'created_by',
    ];

    /**
     * @return BelongsTo<Rack, $this>
     */
    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
