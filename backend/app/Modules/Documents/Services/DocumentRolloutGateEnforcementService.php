<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\DocumentSiteWorkspace;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Sites\Models\Site;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Illuminate\Validation\ValidationException;

final class DocumentRolloutGateEnforcementService
{
    public function __construct(
        private readonly DocumentBinderGateCheckService $gateCheck,
        private readonly TenantEnabledModulesResolver $enabledModules,
    ) {}

    public function isEnabled(): bool
    {
        if (! in_array('documents', $this->enabledModules->resolveForCurrentTenant(), true)) {
            return false;
        }

        return (bool) config('toweros.documents.gate_enforcement.enabled', true);
    }

    public function appliesToPhase(RolloutTimelinePhase $phase): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $phaseKeys = config('toweros.documents.gate_enforcement.phase_keys', []);

        return in_array((string) $phase->phase_key, $phaseKeys, true);
    }

    public function assertCanPassGate(RolloutProgram $program, RolloutTimelinePhase $phase): void
    {
        if (! $this->appliesToPhase($phase)) {
            return;
        }

        $site = $this->resolveSite($program);
        if ($site === null) {
            throw ValidationException::withMessages([
                'gate_status' => [
                    __('Link this rollout to a site binder (Sites → Documents → Linked rollout) before marking this gate passed.'),
                ],
            ]);
        }

        $checklist = $this->gateCheck->checklistForSite($site);
        if (($checklist['summary']['complete'] ?? false) === true) {
            return;
        }

        $missing = collect($checklist['items'] ?? [])
            ->filter(static fn (array $item): bool => ($item['met'] ?? false) === false)
            ->pluck('label')
            ->values()
            ->all();

        throw ValidationException::withMessages([
            'gate_status' => [
                __('Site binder checklist is incomplete. Upload final documents to: :folders.', [
                    'folders' => $missing !== [] ? implode(', ', $missing) : __('required folders'),
                ]),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function phaseSummary(RolloutProgram $program, RolloutTimelinePhase $phase): ?array
    {
        if (! $this->appliesToPhase($phase)) {
            return null;
        }

        $site = $this->resolveSite($program);
        if ($site === null) {
            return [
                'applies' => true,
                'site_linked' => false,
                'complete' => false,
                'site_id' => null,
                'missing_labels' => [],
                'checklist_href' => null,
            ];
        }

        $checklist = $this->gateCheck->checklistForSite($site);
        $missing = collect($checklist['items'] ?? [])
            ->filter(static fn (array $item): bool => ($item['met'] ?? false) === false)
            ->pluck('label')
            ->values()
            ->all();

        return [
            'applies' => true,
            'site_linked' => true,
            'complete' => (bool) ($checklist['summary']['complete'] ?? false),
            'site_id' => (string) $site->id,
            'missing_labels' => $missing,
            'checklist_href' => '/sites/'.$site->id,
            'summary' => $checklist['summary'],
        ];
    }

    private function resolveSite(RolloutProgram $program): ?Site
    {
        if ($program->site_id !== null) {
            $site = Site::query()->find($program->site_id);
            if ($site instanceof Site) {
                return $site;
            }
        }

        $workspace = DocumentSiteWorkspace::query()
            ->where('rollout_program_id', $program->id)
            ->first();

        if ($workspace === null) {
            return null;
        }

        return Site::query()->find($workspace->site_id);
    }
}
