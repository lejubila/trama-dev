<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'color',
    ];

    /**
     * @return MorphToMany<Equipment, $this>
     */
    public function equipment(): MorphToMany
    {
        return $this->morphedByMany(Equipment::class, 'taggable');
    }

    /**
     * @return MorphToMany<Connection, $this>
     */
    public function connections(): MorphToMany
    {
        return $this->morphedByMany(Connection::class, 'taggable');
    }
}
