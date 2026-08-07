<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalExternalDownloadToken;
use App\Modules\EApproval\Models\EApprovalFormOutboundFile;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalOutboundPackageConfig;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Tenancy\Support\TenantAppUrlResolver;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EApprovalExternalPackageService
{
    /**
     * @return array{links: list<array{file_name: string, url: string}>, note: string|null}
     */
    public function mintPackageLinks(EApprovalSubmission $submission): array
    {
        $submission->loadMissing(['form.outboundFiles']);
        $config = EApprovalOutboundPackageConfig::fromFormMetadata(
            is_array($submission->form?->metadata_json) ? $submission->form->metadata_json : [],
        );

        if (! $config['email_package_on_approve']) {
            return ['links' => [], 'note' => null];
        }

        /** @var list<EApprovalFormOutboundFile> $files */
        $files = $submission->form?->outboundFiles?->values()->all() ?? [];

        if ($files === []) {
            return [
                'links' => [],
                'note' => __('No deliverable files were available for this approval.'),
            ];
        }

        $maxFiles = max(1, (int) config('e_approval.external_package.max_files', 25));
        $maxBytes = max(1, (int) config('e_approval.external_package.max_total_bytes', 52428800));
        $ttlMinutes = max(1, (int) config('e_approval.external_package.download_token_minutes', 10080));

        $selected = [];
        $totalBytes = 0;
        $disk = Storage::disk((string) config('toweros.tenant_files.disk', 'local'));
        $capped = false;

        foreach ($files as $file) {
            if (count($selected) >= $maxFiles) {
                $capped = true;
                break;
            }

            $size = (int) $file->byte_size;
            if ($size <= 0 && $disk->exists((string) $file->file_path)) {
                $size = (int) $disk->size((string) $file->file_path);
            }

            if ($totalBytes + $size > $maxBytes) {
                $capped = true;
                break;
            }

            $selected[] = $file;
            $totalBytes += $size;
        }

        $resolver = app(TenantAppUrlResolver::class);
        $links = [];

        foreach ($selected as $file) {
            $plain = Str::random(48);
            EApprovalExternalDownloadToken::query()->create([
                'id' => (string) Str::uuid(),
                'submission_id' => $submission->id,
                'attachment_id' => null,
                'form_outbound_file_id' => $file->id,
                'token_hash' => hash('sha256', $plain),
                'expires_at' => now()->addMinutes($ttlMinutes),
            ]);

            $links[] = [
                'file_name' => (string) $file->file_name,
                'url' => $resolver->urlForCurrentTenant(
                    '/public/e-approval/package-downloads/'.$plain,
                ),
            ];
        }

        $note = null;
        if ($capped) {
            $note = __('Some files were omitted because the package exceeded configured size or file limits. Contact the organization if you need the full set.');
        } elseif ($links !== []) {
            $note = __('Use the secure links below to download your files. Links expire after a limited time.');
        }

        return ['links' => $links, 'note' => $note];
    }

    public function streamDownload(string $plainToken): StreamedResponse
    {
        $hash = hash('sha256', $plainToken);
        $row = EApprovalExternalDownloadToken::query()
            ->where('token_hash', $hash)
            ->first();

        if ($row === null || $row->expires_at === null || $row->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => [__('This download link is invalid or has expired.')],
            ]);
        }

        $row->loadMissing(['submission.form', 'formOutboundFile', 'attachment']);
        $submission = $row->submission;

        if ($submission === null) {
            throw ValidationException::withMessages([
                'token' => [__('This download link is invalid or has expired.')],
            ]);
        }

        if ($submission->status !== EApprovalSubmissionStatus::APPROVED) {
            throw ValidationException::withMessages([
                'token' => [__('Files are only available after approval.')],
            ]);
        }

        $config = EApprovalOutboundPackageConfig::fromFormMetadata(
            is_array($submission->form?->metadata_json) ? $submission->form->metadata_json : [],
        );
        if (! $config['email_package_on_approve']) {
            throw ValidationException::withMessages([
                'token' => [__('This file is not available for download.')],
            ]);
        }

        if ($row->downloaded_at === null) {
            $row->downloaded_at = now();
            $row->save();
        }

        $outbound = $row->formOutboundFile;
        if ($outbound !== null) {
            if ((string) $outbound->form_id !== (string) $submission->form_id) {
                throw ValidationException::withMessages([
                    'token' => [__('This file is not available for download.')],
                ]);
            }

            return app(EApprovalFileStorageService::class)->downloadFormOutboundFile($outbound);
        }

        // Legacy tokens minted against submission attachments (pre-outbound-upload).
        $attachment = $row->attachment;
        if ($attachment === null) {
            throw ValidationException::withMessages([
                'token' => [__('This download link is invalid or has expired.')],
            ]);
        }

        return app(EApprovalFileStorageService::class)->download($attachment);
    }
}
