<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * DCF number is assigned when a controlled document is published.
 * The field sits on the ISO Approval form so the published request can show it,
 * and the request screen hides it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->tablesReady()) {
            return;
        }

        $forms = DB::table('e_approval_forms')->get(['id', 'metadata_json']);
        foreach ($forms as $form) {
            if (! $this->syncEnabled($form->metadata_json)) {
                continue;
            }

            $exists = DB::table('e_approval_form_fields')
                ->where('form_id', $form->id)
                ->where('name', 'dcf_number')
                ->exists();
            if ($exists) {
                continue;
            }

            $codeOrder = DB::table('e_approval_form_fields')
                ->where('form_id', $form->id)
                ->where('name', 'document_code')
                ->value('step_order');
            $order = $codeOrder === null
                ? ((int) DB::table('e_approval_form_fields')->where('form_id', $form->id)->max('step_order')) + 1
                : ((int) $codeOrder) + 1;

            DB::table('e_approval_form_fields')
                ->where('form_id', $form->id)
                ->where('step_order', '>=', $order)
                ->increment('step_order');

            DB::table('e_approval_form_fields')->insert([
                'id' => (string) Str::uuid(),
                'form_id' => $form->id,
                'type' => 'text',
                'name' => 'dcf_number',
                'label' => 'DCF number',
                'semantic_type' => null,
                'behavior' => null,
                'formula' => null,
                'validation' => json_encode([
                    'required' => false,
                    'help_text' => 'Assigned when the document is published.',
                ], JSON_THROW_ON_ERROR),
                'options' => json_encode([
                    'layout' => ['width' => 'half'],
                    'assigned_on_publish' => true,
                ], JSON_THROW_ON_ERROR),
                'step_order' => $order,
            ]);
        }
    }

    public function down(): void
    {
        if (! $this->tablesReady()) {
            return;
        }

        $rows = DB::table('e_approval_form_fields')->where('name', 'dcf_number')->get(['id', 'options']);
        foreach ($rows as $row) {
            $options = json_decode((string) $row->options, true);
            if (! is_array($options) || ($options['assigned_on_publish'] ?? false) !== true) {
                continue;
            }

            DB::table('e_approval_form_fields')->where('id', $row->id)->delete();
        }
    }

    private function syncEnabled(mixed $metadata): bool
    {
        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            $metadata = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($metadata)) {
            return false;
        }

        $sync = $metadata['controlledDocumentSync'] ?? $metadata['controlled_document_sync'] ?? null;

        return is_array($sync) && ($sync['enabled'] ?? false) === true;
    }

    private function tablesReady(): bool
    {
        return Schema::hasTable('e_approval_forms')
            && Schema::hasTable('e_approval_form_fields');
    }
};
