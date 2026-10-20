<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\AdminOne\Services\TenantUserDepartmentDisplay;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Support\EApprovalDepartmentDocCode;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\DB;

final class EApprovalDocumentSequenceService
{
    public function __construct(
        private readonly TenantUserDepartmentDisplay $departmentDisplay,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public function nextDocumentNumber(EApprovalForm $form, array $values = [], ?TenantUser $submitter = null): string
    {
        if ($form->doc_no_custom_enabled && is_string($form->doc_no_template) && trim($form->doc_no_template) !== '') {
            return $this->nextFromTemplate($form, $values, $submitter);
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
    private function nextFromTemplate(EApprovalForm $form, array $values, ?TenantUser $submitter = null): string
    {
        $template = trim((string) $form->doc_no_template);
        $padding = 3;
        if (preg_match('/\{seq:(\d+)\}/', $template, $matches) === 1) {
            $padding = max(1, min(10, (int) $matches[1]));
        }

        $prefix = preg_replace_callback(
            '/\{([^}]+)\}/',
            function (array $matches) use ($form, $values, $submitter): string {
                $token = trim($matches[1]);
                if (str_starts_with($token, 'seq')) {
                    return '';
                }

                return $this->resolveTemplateToken($token, $form, $values, $submitter);
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
    private function resolveTemplateToken(string $token, EApprovalForm $form, array $values, ?TenantUser $submitter = null): string
    {
        $normalized = strtolower($token);

        $raw = match ($normalized) {
            'ownercode', 'owner_code' => (string) ($form->owner_code ?: 'GEN'),
            'doctypecode', 'doc_type_code' => (string) ($form->doc_type_code ?: 'F'),
            'department' => $this->resolveDepartment($values, $submitter),
            'subsidiary' => $this->resolveSubsidiary($values),
            'documenttype', 'document_type' => (string) ($values['document_type'] ?? ''),
            default => (string) ($values[$token] ?? $values[$normalized] ?? ''),
        };

        if ($normalized === 'department') {
            $code = EApprovalDepartmentDocCode::fromLabel($raw);

            return $code !== '' ? $code : 'X';
        }

        $sanitized = preg_replace('/[^A-Z0-9]/', '', strtoupper($raw)) ?? '';

        return $sanitized !== '' ? $sanitized : 'X';
    }

    /**
     * Prefer the form field value; else submitter display department (own or inherited up the manager chain).
     *
     * @param  array<string, mixed>  $values
     */
    private function resolveDepartment(array $values, ?TenantUser $submitter): string
    {
        $fromForm = trim((string) ($values['department'] ?? ''));
        if ($fromForm !== '') {
            return $fromForm;
        }

        if ($submitter === null) {
            return '';
        }

        $this->departmentDisplay->warm(collect([$submitter]));
        $resolved = $this->departmentDisplay->resolve($submitter);

        return (string) ($resolved['department_display'] ?? '');
    }

    /**
     * Resolve subsidiary code from the submission (field name: subsidiary).
     *
     * @param  array<string, mixed>  $values
     */
    private function resolveSubsidiary(array $values): string
    {
        $fromForm = trim((string) ($values['subsidiary'] ?? ''));
        if ($fromForm !== '') {
            return $fromForm;
        }

        foreach ($values as $key => $value) {
            if (strcasecmp((string) $key, 'subsidiary') === 0) {
                $candidate = trim((string) $value);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        return '';
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

    /**
     * Put an issued document code back in front of the counter.
     *
     * Only numbers this prefix has already handed out are accepted. The caller
     * must free the submission's document_no in the same transaction so the
     * unique key can be reused.
     */
    public function releaseDocumentCode(string $documentNo): bool
    {
        $documentNo = trim($documentNo);
        if ($documentNo === '' || preg_match('/-R\d+$/i', $documentNo) === 1) {
            return false;
        }

        if (preg_match('/^(.*)-(\d+)$/', $documentNo, $matches) !== 1) {
            return false;
        }

        $prefix = $matches[1];
        $number = (int) $matches[2];
        if ($prefix === '' || $number < 1) {
            return false;
        }

        $row = DB::connection('tenant')->table('e_approval_document_sequences')
            ->where('prefix', $prefix)
            ->lockForUpdate()
            ->first();

        if ($row === null || $number >= (int) $row->next_no) {
            return false;
        }

        $held = DB::connection('tenant')->table('e_approval_released_document_numbers')
            ->where('prefix', $prefix)
            ->where('seq_no', $number)
            ->lockForUpdate()
            ->exists();

        if (! $held) {
            DB::connection('tenant')->table('e_approval_released_document_numbers')->insert([
                'prefix' => $prefix,
                'seq_no' => $number,
            ]);
        }

        return true;
    }

    /**
     * Next integer for a private series key. The caller formats the visible number.
     */
    public function takeNext(string $prefix): int
    {
        return DB::connection('tenant')->transaction(
            fn (): int => $this->allocateSequence($prefix),
        );
    }

    /**
     * An imported number is already used. The next issue for this prefix must be higher,
     * and the same integer must not come back out of the released pool.
     */
    public function noteConsumed(string $prefix, int $seq): void
    {
        if ($seq < 1) {
            return;
        }

        DB::connection('tenant')->transaction(function () use ($prefix, $seq): void {
            $row = DB::connection('tenant')->table('e_approval_document_sequences')
                ->where('prefix', $prefix)
                ->lockForUpdate()
                ->first();

            $floor = $seq + 1;
            if ($row === null) {
                DB::connection('tenant')->table('e_approval_document_sequences')->insert([
                    'prefix' => $prefix,
                    'next_no' => $floor,
                ]);
            } elseif ((int) $row->next_no < $floor) {
                DB::connection('tenant')->table('e_approval_document_sequences')
                    ->where('prefix', $prefix)
                    ->update(['next_no' => $floor]);
            }

            DB::connection('tenant')->table('e_approval_released_document_numbers')
                ->where('prefix', $prefix)
                ->where('seq_no', $seq)
                ->delete();
        });
    }

    private function allocateSequence(string $prefix): int
    {
        $row = DB::connection('tenant')->table('e_approval_document_sequences')
            ->where('prefix', $prefix)
            ->lockForUpdate()
            ->first();

        $released = DB::connection('tenant')->table('e_approval_released_document_numbers')
            ->where('prefix', $prefix)
            ->orderBy('seq_no')
            ->lockForUpdate()
            ->first();

        if ($released !== null) {
            DB::connection('tenant')->table('e_approval_released_document_numbers')
                ->where('prefix', $prefix)
                ->where('seq_no', (int) $released->seq_no)
                ->delete();

            return (int) $released->seq_no;
        }

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
