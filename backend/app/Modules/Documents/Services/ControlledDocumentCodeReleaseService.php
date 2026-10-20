<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Support\ControlledDocumentStatus;
use App\Modules\Documents\Support\ControlledDocumentSyncConfig;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalDocumentSequenceService;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A cancelled controlled-document request gives its document code back.
 * ATC-BDD-P-001 cancelled, then the next new request is ATC-BDD-P-001 again.
 */
final class ControlledDocumentCodeReleaseService
{
    public function __construct(
        private readonly EApprovalDocumentSequenceService $sequences,
    ) {}

    public function releaseCancelled(EApprovalSubmission $submission): void
    {
        if ((string) $submission->status !== EApprovalSubmissionStatus::CANCELLED) {
            return;
        }

        $submission->loadMissing('form');
        $form = $submission->form;
        if (! $form instanceof EApprovalForm || $this->syncConfig($form) === null) {
            return;
        }

        $documentNo = trim((string) $submission->document_no);
        if ($documentNo === '' || preg_match('/-R\d+$/i', $documentNo) === 1) {
            return;
        }

        $live = ControlledDocument::query()->where('document_code', $documentNo)->first();
        if ($live instanceof ControlledDocument && $live->status !== ControlledDocumentStatus::PENDING) {
            return;
        }

        DB::connection('tenant')->transaction(function () use ($submission, $documentNo, $live): void {
            if ($live instanceof ControlledDocument && $live->status === ControlledDocumentStatus::PENDING) {
                $live->delete();
            }

            if (! $this->sequences->releaseDocumentCode($documentNo)) {
                return;
            }

            $submission->document_no = $this->vacatedDocumentNo($documentNo);
            $submission->save();
        });
    }

    private function syncConfig(EApprovalForm $form): ?ControlledDocumentSyncConfig
    {
        $meta = $form->metadata_json;
        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            $meta = is_array($decoded) ? $decoded : null;
        }

        return ControlledDocumentSyncConfig::parse(is_array($meta) ? $meta : null);
    }

    private function vacatedDocumentNo(string $documentNo): string
    {
        $base = $documentNo.'-CANCELLED';
        if (strlen($base) > 120) {
            $base = substr($documentNo, 0, 108).'-CANCELLED';
        }

        $candidate = $base;
        $suffix = 2;
        while (EApprovalSubmission::query()->where('document_no', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
            if ($suffix > 20) {
                return $base.'-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8));
            }
        }

        return $candidate;
    }
}
