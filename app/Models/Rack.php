<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RackNumbering;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\RackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $room_id
 * @property string $name
 * @property int $height_units
 * @property RackNumbering $numbering
 */
class Rack extends Model
{
    /** @use HasFactory<RackFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'room_id',
        'name',
        'height_units',
        'width_mm',
        'depth_mm',
        'position_x',
        'position_y',
        'numbering',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'numbering' => RackNumbering::class,
            'height_units' => 'integer',
            'width_mm' => 'integer',
            'depth_mm' => 'integer',
            'position_x' => 'decimal:2',
            'position_y' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return HasMany<Equipment, $this>
     */
    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }
}
