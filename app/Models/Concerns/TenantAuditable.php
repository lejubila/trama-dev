<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Tenancy\TenantContext;
use OwenIt\Auditing\Auditable as BaseAuditable;
use OwenIt\Auditing\Exceptions\AuditingException;

/**
 * Drop-in replacement for OwenIt\Auditing\Auditable that snapshots the active
 * tenant id onto every audit row. The audits.tenant_id column is added by the
 * 2026_05_09_002237_add_tenant_id_to_audits_table migration.
 *
 * Models that use this trait must implement OwenIt\Auditing\Contracts\Auditable
 * (see Equipment, NetworkInterface, Connection in FASE 2).
 */
trait TenantAuditable
{
    use BaseAuditable {
        toAudit as protected baseToAudit;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws AuditingException
     */
    public function toAudit(): array
    {
        $audit = $this->baseToAudit();

        // Prefer the model's own tenant_id (the row being audited belongs to
        // exactly one tenant); fall back to the request-scoped TenantContext.
        $audit['tenant_id'] = $this->getAttribute('tenant_id') ?? TenantContext::id();

        return $audit;
    }
}
