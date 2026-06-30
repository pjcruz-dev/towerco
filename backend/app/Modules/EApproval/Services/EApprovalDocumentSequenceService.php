<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use Illuminate\Support\Facades\DB;

final class EApprovalDocumentSequenceService
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function nextDocumentNumber(EApprovalForm $form, array $values = []): string
    {
        if ($form->doc_no_custom_enabled && is_string($form->doc_no_template) && trim($form->doc_no_template) !== '') {
            return $this->nextFromTemplate($form, $values);
        }

        $owner = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($form->owner_code ?: 'GEN')) ?: 'GEN');
        $docType = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($form->doc_type_code ?: 'F')) ?: 'F');
        $prefix = "{$owner}-{$docType}";

        return DB::connection('tenant')->transaction(function () use ($prefix): string {
            $n = $this->allocateSequence($prefix);

            return sprintf('%s-%05d', $prefix, $n);
        });
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function nextFromTemplate(EApprovalForm $form, array $values): string
    {
        $template = trim((string) $form->doc_no_template);
        $padding = 3;
        if (preg_match('/\{seq:(\d+)\}/', $template, $matches) === 1) {
            $padding = max(1, min(10, (int) $matches[1]));
        }

        $prefix = preg_replace_callback(
            '/\{([^}]+)\}/',
            function (array $matches) use ($form, $values): string {
                $token = trim($matches[1]);
                if (str_starts_with($token, 'seq')) {
                    return '';
                }

                return $this->resolveTemplateToken($token, $form, $values);
            },
            $template,
        ) ?? $template;

        $prefix = rtrim($prefix, '-');
        if ($prefix === '') {
            $prefix = 'DOC';
        }

        return DB::connection('tenant')->transaction(function () use ($prefix, $padding): string {
            $n = $this->allocateSequence($prefix);

            return sprintf('%s-%0'.$padding.'d', $prefix, $n);
        });
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function resolveTemplateToken(string $token, EApprovalForm $form, array $values): string
    {
        $normalized = strtolower($token);

        $raw = match ($normalized) {
            'ownercode', 'owner_code' => (string) ($form->owner_code ?: 'GEN'),
            'doctypecode', 'doc_type_code' => (string) ($form->doc_type_code ?: 'F'),
            'department' => (string) ($values['department'] ?? ''),
            'documenttype', 'document_type' => (string) ($values['document_type'] ?? ''),
            default => (string) ($values[$token] ?? $values[$normalized] ?? ''),
        };

        $sanitized = strtoupper(preg_replace('/[^A-Z0-9]/', '', $raw) ?? '');

        return $sanitized !== '' ? $sanitized : 'X';
    }

    /**
     * Allocate the next revision-submission number for a controlled document.
     *
     * The first revision request for ATC-P-SCM-001 yields ATC-P-SCM-001-R001,
     * the second ATC-P-SCM-001-R002, etc.  The counter is per-document-code and
     * is stored in the shared e_approval_document_sequences table using the
     * "{documentCode}-R" prefix so it never collides with primary document numbers.
     */
    public function nextRevisionNumber(string $documentCode): string
    {
        $prefix = $documentCode.'-R';

        return DB::connection('tenant')->transaction(function () use ($prefix): string {
            $n = $this->allocateSequence($prefix);

            return sprintf('%s%03d', $prefix, $n);
        });
    }

    private function allocateSequence(string $prefix): int
    {
        $row = DB::connection('tenant')->table('e_approval_document_sequences')
            ->where('prefix', $prefix)
            ->lockForUpdate()
            ->first();

        $n = $row ? max(1, (int) $row->next_no) : 1;

        if ($row) {
            DB::connection('tenant')->table('e_approval_document_sequences')
                ->where('prefix', $prefix)
                ->update(['next_no' => $n + 1]);
        } else {
            DB::connection('tenant')->table('e_approval_document_sequences')->insert([
                'prefix' => $prefix,
                'next_no' => $n + 1,
            ]);
        }

        return $n;
    }
}
