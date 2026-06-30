<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentSiteNode;
use App\Modules\Documents\Support\DocumentStatus;
use App\Modules\Sites\Models\Site;

final class DocumentBinderGateCheckService
{
    public function __construct(
        private readonly DocumentWorkspaceService $workspace,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function checklistForSite(Site $site): array
    {
        $workspace = $this->workspace->ensureForSite($site);
        $requiredKeys = config('toweros.documents.gate_required_node_keys', []);

        $nodes = DocumentSiteNode::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('node_key', $requiredKeys)
            ->get()
            ->keyBy(static fn (DocumentSiteNode $n) => $n->node_key);

        $items = [];
        $passed = 0;

        foreach ($requiredKeys as $key) {
            $node = $nodes->get($key);
            if ($node === null) {
                $items[] = [
                    'node_key' => $key,
                    'label' => $key,
                    'required' => true,
                    'met' => false,
                    'final_document_count' => 0,
                ];

                continue;
            }

            $finalCount = Document::query()
                ->where('site_id', $site->id)
                ->where('site_node_id', $node->id)
                ->where('status', DocumentStatus::FINAL)
                ->whereNull('deleted_at')
                ->count();

            $met = $finalCount > 0;
            if ($met) {
                $passed++;
            }

            $items[] = [
                'node_key' => $key,
                'label' => $node->label,
                'required' => true,
                'met' => $met,
                'final_document_count' => $finalCount,
            ];
        }

        $total = count($requiredKeys);

        return [
            'site_id' => (string) $site->id,
            'rollout_program_id' => $workspace->rollout_program_id,
            'summary' => [
                'required' => $total,
                'met' => $passed,
                'complete' => $total > 0 && $passed === $total,
            ],
            'items' => $items,
        ];
    }
}
