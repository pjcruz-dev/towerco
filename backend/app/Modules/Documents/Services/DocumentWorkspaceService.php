<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentSiteNode;
use App\Modules\Documents\Models\DocumentSiteWorkspace;
use App\Modules\Sites\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DocumentWorkspaceService
{
    public function __construct(
        private readonly DocumentBinderTemplateService $templates,
    ) {}

    public function ensureForSite(Site $site): DocumentSiteWorkspace
    {
        $existing = DocumentSiteWorkspace::query()->where('site_id', $site->id)->first();
        if ($existing instanceof DocumentSiteWorkspace) {
            return $existing;
        }

        return DB::connection('tenant')->transaction(function () use ($site): DocumentSiteWorkspace {
            $workspace = DocumentSiteWorkspace::query()->create([
                'id' => (string) Str::uuid(),
                'site_id' => $site->id,
            ]);

            $order = 0;
            foreach ($this->templates->effectiveTree() as $binder) {
                $this->seedNodeTree($workspace, null, $binder, $order);
            }

            return $workspace;
        });
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seedNodeTree(
        DocumentSiteWorkspace $workspace,
        ?string $parentId,
        array $definition,
        int &$order,
    ): DocumentSiteNode {
        $node = DocumentSiteNode::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'parent_id' => $parentId,
            'node_key' => (string) $definition['key'],
            'label' => (string) $definition['label'],
            'node_type' => (string) $definition['type'],
            'sort_order' => $order++,
        ]);

        foreach ($definition['children'] ?? [] as $child) {
            if (! is_array($child)) {
                continue;
            }
            if (($child['type'] ?? '') === 'fixed' && ($definition['type'] ?? '') === 'repeatable_container') {
                // Template child for lessor instances — seeded when lessor is added.
                continue;
            }
            $this->seedNodeTree($workspace, (string) $node->id, $child, $order);
        }

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    public function workspacePayload(Site $site): array
    {
        $workspace = $this->ensureForSite($site);
        $workspace->load(['nodes']);

        $counts = Document::query()
            ->where('site_id', $site->id)
            ->whereNull('deleted_at')
            ->selectRaw('site_node_id, count(*) as aggregate')
            ->groupBy('site_node_id')
            ->pluck('aggregate', 'site_node_id');

        $nodes = $workspace->nodes->map(static function (DocumentSiteNode $node) use ($counts): array {
            return [
                'id' => (string) $node->id,
                'parent_id' => $node->parent_id !== null ? (string) $node->parent_id : null,
                'node_key' => $node->node_key,
                'label' => $node->label,
                'node_type' => $node->node_type,
                'sort_order' => $node->sort_order,
                'lessor_name' => $node->lessor_name,
                'lessor_contact' => $node->lessor_contact,
                'document_count' => (int) ($counts[(string) $node->id] ?? 0),
            ];
        })->values()->all();

        $lastTouch = Document::query()
            ->where('site_id', $site->id)
            ->whereNull('deleted_at')
            ->orderByDesc('last_touched_at')
            ->with('lastTouchedBy:id,name')
            ->first();

        return [
            'workspace' => [
                'id' => (string) $workspace->id,
                'site_id' => (string) $site->id,
                'rollout_program_id' => $workspace->rollout_program_id,
            ],
            'nodes' => $nodes,
            'last_activity' => $lastTouch ? [
                'document_id' => (string) $lastTouch->id,
                'title' => $lastTouch->title,
                'at' => $lastTouch->last_touched_at?->toIso8601String(),
                'by' => $lastTouch->lastTouchedBy ? [
                    'id' => (string) $lastTouch->lastTouchedBy->id,
                    'name' => $lastTouch->lastTouchedBy->name,
                ] : null,
            ] : null,
        ];
    }

    public function updateWorkspace(Site $site, ?string $rolloutProgramId): DocumentSiteWorkspace
    {
        $workspace = $this->ensureForSite($site);
        $workspace->rollout_program_id = $rolloutProgramId;
        $workspace->save();

        return $workspace;
    }

    /**
     * @return array<string, mixed>
     */
    public function addLessor(Site $site, string $lessorName, ?string $lessorContact): array
    {
        $lessorName = trim($lessorName);
        if ($lessorName === '') {
            throw ValidationException::withMessages([
                'lessor_name' => [__('Lessor name is required.')],
            ]);
        }

        $workspace = $this->ensureForSite($site);
        $lessorsContainer = DocumentSiteNode::query()
            ->where('workspace_id', $workspace->id)
            ->where('node_key', 'lessors')
            ->first();

        if ($lessorsContainer === null) {
            throw ValidationException::withMessages([
                'lessors' => [__('Lessors folder is not configured for this site.')],
            ]);
        }

        $instanceCount = DocumentSiteNode::query()
            ->where('workspace_id', $workspace->id)
            ->where('node_type', 'repeatable_instance')
            ->where('parent_id', $lessorsContainer->id)
            ->count();

        $label = 'Lessor '.($instanceCount + 1).' — '.$lessorName;

        return DB::connection('tenant')->transaction(function () use (
            $workspace,
            $lessorsContainer,
            $label,
            $lessorName,
            $lessorContact,
            $instanceCount,
        ): array {
            $instance = DocumentSiteNode::query()->create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'parent_id' => $lessorsContainer->id,
                'node_key' => 'lessor_'.($instanceCount + 1),
                'label' => $label,
                'node_type' => 'repeatable_instance',
                'sort_order' => $instanceCount,
                'lessor_name' => $lessorName,
                'lessor_contact' => $lessorContact,
            ]);

            $documentsNode = DocumentSiteNode::query()->create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'parent_id' => $instance->id,
                'node_key' => 'lessor_documents',
                'label' => 'Documents',
                'node_type' => 'fixed',
                'sort_order' => 0,
            ]);

            return [
                'instance' => [
                    'id' => (string) $instance->id,
                    'label' => $instance->label,
                    'upload_node_id' => (string) $documentsNode->id,
                ],
            ];
        });
    }

    public function assertUploadNodeBelongsToSite(Site $site, string $nodeId): DocumentSiteNode
    {
        $workspace = $this->ensureForSite($site);
        /** @var DocumentSiteNode|null $node */
        $node = DocumentSiteNode::query()
            ->where('id', $nodeId)
            ->where('workspace_id', $workspace->id)
            ->first();

        if ($node === null) {
            throw ValidationException::withMessages([
                'site_node_id' => [__('Folder not found for this site.')],
            ]);
        }

        if (in_array($node->node_type, ['binder', 'folder', 'repeatable_container', 'repeatable_instance'], true)) {
            throw ValidationException::withMessages([
                'site_node_id' => [__('Upload to a document folder, not a binder container.')],
            ]);
        }

        return $node;
    }
}
