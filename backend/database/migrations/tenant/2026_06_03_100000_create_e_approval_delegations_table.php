<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('e_approval_delegations')) {
            return;
        }

        Schema::create('e_approval_delegations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('delegator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('delegate_id')->constrained('users')->cascadeOnDelete();
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['delegate_id', 'is_active'], 'eappr_del_delegate_active_idx');
            $table->index(['delegator_id', 'is_active'], 'eappr_del_delegator_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('e_approval_delegations');
    }
};
