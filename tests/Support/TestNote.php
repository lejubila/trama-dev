<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Thin Eloquent model used only by FASE 1 multi-tenancy tests.
 * The matching schema lives in tests/Database/migrations/.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $body
 */
class TestNote extends Model
{
    use BelongsToTenant;

    protected $table = 'test_notes';

    protected $fillable = ['body', 'tenant_id'];
}
