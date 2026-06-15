<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('e_approval_submissions', function (Blueprint $table) {
            $table->longText('schema_snapshot_json')->nullable()->after('parent_submission_id');
            $table->longText('workflow_snapshot_json')->nullable()->after('schema_snapshot_json');
            $table->string('workflow_version_id', 64)->nullable()->after('workflow_snapshot_json');
        });
    }

    public function down(): void
    {
        Schema::table('e_approval_submissions', function (Blueprint $table) {
            $table->dropColumn(['schema_snapshot_json', 'workflow_snapshot_json', 'workflow_version_id']);
        });
    }
};
