<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LinkGroupMode;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\LinkGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LinkGroup extends Model
{
    /** @use HasFactory<LinkGroupFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'mode',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'mode' => LinkGroupMode::class,
        ];
    }

    /**
     * @return BelongsToMany<Connection, $this>
     */
    public function connections(): BelongsToMany
    {
        return $this->belongsToMany(Connection::class, 'link_group_connection');
    }
}
