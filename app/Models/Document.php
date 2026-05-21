<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $title
 * @property string|null $description
 * @property Carbon $document_date
 * @property array<string, mixed> $parameters
 * @property string|null $pdf_path
 * @property Carbon|null $generated_at
 * @property int|null $created_by
 */
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'document_date',
        'parameters',
        'pdf_path',
        'generated_at',
        'created_by',
    ];

    protected $casts = [
        'document_date' => 'date',
        'parameters' => 'array',
        'generated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
