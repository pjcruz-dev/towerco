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
        $owner = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($form->owner_code ?: 'GEN')) ?: 'GEN');
        $docType = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($form->doc_type_code ?: 'F')) ?: 'F');
        $prefix = "{$owner}-{$docType}";

        return DB::connection('tenant')->transaction(function () use ($prefix): string {
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

            return sprintf('%s-%05d', $prefix, $n);
        });
    }
}
