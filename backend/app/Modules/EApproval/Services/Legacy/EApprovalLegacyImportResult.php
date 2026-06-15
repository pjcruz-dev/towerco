<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services\Legacy;

final class EApprovalLegacyImportResult
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public int $usersMapped = 0,
        public int $formsImported = 0,
        public int $submissionsImported = 0,
        public int $submissionsSkipped = 0,
        public int $masterDataSetsImported = 0,
        public int $delegationsImported = 0,
        public int $settingsImported = 0,
        public array $warnings = [],
        public bool $dryRun = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dry_run' => $this->dryRun,
            'users_mapped' => $this->usersMapped,
            'forms_imported' => $this->formsImported,
            'submissions_imported' => $this->submissionsImported,
            'submissions_skipped' => $this->submissionsSkipped,
            'master_data_sets_imported' => $this->masterDataSetsImported,
            'delegations_imported' => $this->delegationsImported,
            'settings_imported' => $this->settingsImported,
            'warnings' => $this->warnings,
        ];
    }
}
