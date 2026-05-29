<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_approvals', function (Blueprint $table) {
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUuid('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_approvals', function (Blueprint $table) {
            $table->dropForeign(['resolved_by_id']);
            $table->dropColumn(['resolution_notes', 'resolved_at', 'resolved_by_id']);
        });
    }
};
