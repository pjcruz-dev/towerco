<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use Illuminate\Support\Facades\Schema;

final class EApprovalHealthService
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $requiredTables = [
            'e_approval_forms',
            'e_approval_submissions',
            'e_approval_request_approvals',
            'tenant_notifications',
        ];

        $tables = [];
        $ready = true;

        foreach ($requiredTables as $table) {
            $exists = Schema::hasTable($table);
            $tables[$table] = $exists;
            if (! $exists) {
                $ready = false;
            }
        }

        return [
            'module' => 'e-approval',
            'phase' => 'P2',
            'status' => $ready ? 'ready' : 'degraded',
            'schema_ready' => $ready,
            'tables' => $tables,
        ];
    }
}
