<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\WorkspaceAuditIndexService;
use App\Modules\Workspace\Support\WorkspaceAuditTaxonomy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class WorkspaceAuditExportController extends AbstractApiController
{
    public function __invoke(Request $request, WorkspaceAuditIndexService $audit): StreamedResponse
    {
        /** @var TenantUser|null $user */
        $user = $request->user();
        abort_unless($user?->can('workspace:audit:view'), 403);

        $validated = $request->validate([
            'module' => ['sometimes', 'nullable', 'string', 'max:50'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'actor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
            'category' => ['sometimes', 'nullable', 'string', Rule::in([...WorkspaceAuditTaxonomy::categories(), 'all'])],
            'severity' => ['sometimes', 'nullable', 'string', Rule::in([...WorkspaceAuditTaxonomy::severities(), 'all'])],
            'action_family' => ['sometimes', 'nullable', 'string', 'max:50'],
            'entity_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'entity_id' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $rows = $audit->forExport($user, [
            'module' => $validated['module'] ?? null,
            'search' => $validated['search'] ?? null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'actor' => $validated['actor'] ?? null,
            'category' => $validated['category'] ?? null,
            'severity' => $validated['severity'] ?? null,
            'action_family' => $validated['action_family'] ?? null,
            'entity_type' => $validated['entity_type'] ?? null,
            'entity_id' => $validated['entity_id'] ?? null,
        ]);

        $filename = 'workspace-audit-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'created_at',
                'module',
                'category',
                'severity',
                'action',
                'action_label',
                'action_family',
                'summary',
                'reason',
                'actor_name',
                'actor_email',
                'entity_type',
                'entity_id',
                'entity_label',
                'ip_address',
                'user_agent',
                'source',
                'changes',
            ]);

            foreach ($rows as $row) {
                $actor = is_array($row['actor'] ?? null) ? $row['actor'] : null;
                $changes = $row['changes'] ?? null;
                fputcsv($handle, [
                    (string) ($row['created_at'] ?? ''),
                    (string) ($row['module'] ?? ''),
                    (string) ($row['category'] ?? ''),
                    (string) ($row['severity'] ?? ''),
                    (string) ($row['action'] ?? ''),
                    (string) ($row['action_label'] ?? ''),
                    (string) ($row['action_family'] ?? ''),
                    (string) ($row['summary'] ?? ''),
                    (string) ($row['reason'] ?? ''),
                    (string) ($actor['name'] ?? ''),
                    (string) ($actor['email'] ?? ''),
                    (string) ($row['entity_type'] ?? ''),
                    (string) ($row['entity_id'] ?? ''),
                    (string) ($row['entity_label'] ?? ''),
                    (string) ($row['ip_address'] ?? ''),
                    (string) ($row['user_agent'] ?? ''),
                    (string) ($row['source'] ?? ''),
                    is_array($changes) ? (string) json_encode($changes, JSON_UNESCAPED_UNICODE) : '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
