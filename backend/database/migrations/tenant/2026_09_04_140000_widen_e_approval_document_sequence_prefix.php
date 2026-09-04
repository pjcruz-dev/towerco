<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Custom doc-no templates can expand department names into long prefixes
 * (e.g. ATC-TECHNOLOGYANDQUALITYGOVERNANCE-DA). The original varchar(30)
 * primary key truncates and fails submit with SQLSTATE 22001.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('e_approval_document_sequences')) {
            DB::statement('ALTER TABLE e_approval_document_sequences MODIFY prefix VARCHAR(128) NOT NULL');
        }

        if (Schema::hasTable('e_approval_submissions')) {
            DB::statement('ALTER TABLE e_approval_submissions MODIFY document_no VARCHAR(128) NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('e_approval_document_sequences')) {
            DB::statement('ALTER TABLE e_approval_document_sequences MODIFY prefix VARCHAR(30) NOT NULL');
        }

        if (Schema::hasTable('e_approval_submissions')) {
            DB::statement('ALTER TABLE e_approval_submissions MODIFY document_no VARCHAR(50) NOT NULL');
        }
    }
};
