<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('e_approval_publication_trainings')) {
            return;
        }

        Schema::create('e_approval_publication_trainings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')
                ->constrained('e_approval_submissions')
                ->cascadeOnDelete();
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignUuid('invited_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('status', 32)->default('invited');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['submission_id', 'user_id'], 'ea_pub_train_sub_user_uq');
            $table->index(['submission_id', 'status'], 'ea_pub_train_sub_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('e_approval_publication_trainings');
    }
};
