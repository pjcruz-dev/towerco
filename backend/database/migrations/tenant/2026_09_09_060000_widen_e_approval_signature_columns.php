<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drawn/uploaded signature data URLs routinely exceed MySQL TEXT (64KB).
        if (Schema::hasTable('e_approval_request_approvals') && Schema::hasColumn('e_approval_request_approvals', 'signature')) {
            DB::statement('ALTER TABLE e_approval_request_approvals MODIFY signature LONGTEXT NULL');
        }

        if (Schema::hasTable('e_approval_settings') && Schema::hasColumn('e_approval_settings', 'value')) {
            DB::statement('ALTER TABLE e_approval_settings MODIFY value LONGTEXT NULL');
        }

        if (Schema::hasTable('e_approval_form_values') && Schema::hasColumn('e_approval_form_values', 'value')) {
            DB::statement('ALTER TABLE e_approval_form_values MODIFY value LONGTEXT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('e_approval_request_approvals') && Schema::hasColumn('e_approval_request_approvals', 'signature')) {
            DB::statement('ALTER TABLE e_approval_request_approvals MODIFY signature TEXT NULL');
        }

        if (Schema::hasTable('e_approval_settings') && Schema::hasColumn('e_approval_settings', 'value')) {
            DB::statement('ALTER TABLE e_approval_settings MODIFY value TEXT NULL');
        }

        if (Schema::hasTable('e_approval_form_values') && Schema::hasColumn('e_approval_form_values', 'value')) {
            DB::statement('ALTER TABLE e_approval_form_values MODIFY value TEXT NULL');
        }
    }
};
