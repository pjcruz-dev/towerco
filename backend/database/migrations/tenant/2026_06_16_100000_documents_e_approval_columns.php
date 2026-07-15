<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->uuid('e_approval_submission_id')->nullable()->after('last_touched_at');
            $table->string('approval_status', 20)->default('none')->after('e_approval_submission_id');

            $table->index('e_approval_submission_id');
            $table->index('approval_status');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['e_approval_submission_id']);
            $table->dropIndex(['approval_status']);
            $table->dropColumn(['e_approval_submission_id', 'approval_status']);
        });
    }
};
