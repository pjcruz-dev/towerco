<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Documents\Services\DocumentRolloutLeasePackageMigrationService;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\SiteCandidate;
use App\Modules\Rollout\Support\RolloutFieldCreateResult;
use App\Modules\Sites\Models\Site;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Illuminate\Validation\ValidationException;

final class SiteCandidateService
{
    public function __construct(
        private readonly RolloutCanonicalSiteService $canonicalSites,
        private readonly RolloutMediaAttachmentService $media,
        private readonly RolloutAuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{record: SiteCandidate, created: bool}
     */
    public function create(RolloutProgram $program, array $input): array
    {
        if (! empty($input['client_draft_id'])) {
            $existing = SiteCandidate::query()
                ->where('rollout_program_id', $program->id)
                ->where('client_draft_id', $input['client_draft_id'])
                ->first();

            if ($existing !== null) {
                return RolloutFieldCreateResult::of($existing, false);
            }
        }

        $nextNumber = (int) SiteCandidate::query()
            ->where('rollout_program_id', $program->id)
            ->max('candidate_number') + 1;

        /** @var SiteCandidate $candidate */
        $candidate = SiteCandidate::query()->create([
            'rollout_program_id' => $program->id,
            'client_draft_id' => $input['client_draft_id'] ?? null,
            'candidate_number' => $nextNumber,
            'status' => 'scouted',
            'label' => $input['label'] ?? "Candidate {$nextNumber}",
            'latitude' => $input['latitude'] ?? null,
            'longitude' => $input['longitude'] ?? null,
            'coordinate_capture_method' => $input['coordinate_capture_method'] ?? null,
            'coordinate_accuracy_m' => $input['coordinate_accuracy_m'] ?? null,
            'coordinates_captured_at' => $input['coordinates_captured_at'] ?? (
                isset($input['latitude'], $input['longitude']) ? now() : null
            ),
            'lessor_name' => $input['lessor_name'] ?? null,
            'lessor_contact' => $input['lessor_contact'] ?? null,
            'proposed_lease_rate_php' => $input['proposed_lease_rate_php'] ?? null,
            'row_notes' => $input['row_notes'] ?? null,
            'power_notes' => $input['power_notes'] ?? null,
            'hazard_notes' => $input['hazard_notes'] ?? null,
            'photo_links' => $this->media->normalizePhotoLinks(
                isset($input['photo_links']) && is_array($input['photo_links']) ? $input['photo_links'] : null,
                $program->id,
            ),
            'lease_package' => $this->media->normalizeLeasePackage(
                isset($input['lease_package']) && is_array($input['lease_package']) ? $input['lease_package'] : null,
                $program->id,
            ),
        ]);

        return RolloutFieldCreateResult::of($candidate, true);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(SiteCandidate $candidate, array $input): SiteCandidate
    {
        if (in_array($candidate->status, ['selected', 'superseded'], true)) {
            throw ValidationException::withMessages([
                'candidate' => [__('Selected candidates cannot be edited.')],
            ]);
        }

        $candidate->fill(array_intersect_key($input, array_flip([
            'label', 'latitude', 'longitude', 'coordinate_capture_method', 'coordinate_accuracy_m',
            'coordinates_captured_at', 'lessor_name', 'lessor_contact',
            'proposed_lease_rate_php', 'row_notes', 'power_notes', 'hazard_notes',
            'status',
        ])));

        if (array_key_exists('photo_links', $input)) {
            $candidate->photo_links = $this->media->normalizePhotoLinks(
                is_array($input['photo_links']) ? $input['photo_links'] : null,
                $candidate->rollout_program_id,
            );
        }

        if (array_key_exists('lease_package', $input)) {
            $candidate->lease_package = $this->media->normalizeLeasePackage(
                is_array($input['lease_package']) ? $input['lease_package'] : null,
                $candidate->rollout_program_id,
            );
        }

        $candidate->save();

        return $candidate->fresh();
    }

    public function reject(SiteCandidate $candidate, TenantUser $actor, string $reasonCode, ?string $notes): SiteCandidate
    {
        if ($candidate->status === 'selected') {
            throw ValidationException::withMessages([
                'candidate' => [__('Cannot reject the selected candidate.')],
            ]);
        }

        $candidate->status = 'rejected';
        $candidate->rejection_reason_code = $reasonCode;
        $candidate->rejection_notes = $notes;
        $candidate->rejected_at = now();
        $candidate->rejected_by_id = $actor->id;
        $candidate->save();

        return $candidate->fresh();
    }

    public function select(SiteCandidate $candidate): RolloutProgram
    {
        if ($candidate->status === 'rejected') {
            throw ValidationException::withMessages([
                'candidate' => [__('Rejected candidates cannot be selected.')],
            ]);
        }

        $program = $candidate->rolloutProgram;
        if ($program === null) {
            throw ValidationException::withMessages(['rollout' => [__('Rollout not found.')]]);
        }

        SiteCandidate::query()
            ->where('rollout_program_id', $program->id)
            ->where('id', '!=', $candidate->id)
            ->where('status', 'selected')
            ->update(['status' => 'superseded']);

        $candidate->status = 'selected';
        $candidate->selected_at = now();
        $candidate->save();

        $site = $this->canonicalSites->enrichFromCandidate($candidate, $program->fresh());

        $this->migrateLeasePackageToDocuments($candidate, $site);

        $updated = $program->fresh(['site', 'candidates', 'timelinePhases', 'profitability']);

        $this->audit->log('rollout.candidate_selected', $updated, [
            'candidate_number' => $candidate->candidate_number,
            'tco_site_id' => $updated->tco_site_id,
        ]);

        return $updated;
    }

    private function migrateLeasePackageToDocuments(SiteCandidate $candidate, Site $site): void
    {
        $enabled = app(TenantEnabledModulesResolver::class)
            ->resolveForCurrentTenant();

        if (! in_array('documents', $enabled, true) || ! in_array('sites', $enabled, true)) {
            return;
        }

        try {
            app(DocumentRolloutLeasePackageMigrationService::class)
                ->migrateCandidate($candidate, $site, null);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
