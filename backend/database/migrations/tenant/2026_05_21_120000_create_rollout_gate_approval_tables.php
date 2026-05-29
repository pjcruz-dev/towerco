<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_rollout_playbook_config', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenant_rollout_playbook_config', 'gate_approval_policies')) {
                $table->json('gate_approval_policies')->nullable()->after('day_overrides');
            }
        });

        if (! Schema::hasTable('rollout_gate_approval_requests')) {
            Schema::create('rollout_gate_approval_requests', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('rollout_program_id')->constrained('rollout_programs')->cascadeOnDelete();
                $table->foreignUuid('rollout_timeline_phase_id')->constrained('rollout_timeline_phases')->cascadeOnDelete();
                $table->string('phase_key', 64);
                $table->string('gate_label')->nullable();
                $table->string('status', 32)->default('in_review');
                $table->unsignedSmallInteger('current_step')->default(0);
                $table->json('approval_chain');
                $table->json('step_log')->nullable();
                $table->text('request_notes')->nullable();
                $table->text('rejection_notes')->nullable();
                $table->foreignUuid('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'submitted_at'], 'rgate_req_status_submitted_idx');
                $table->index(['rollout_program_id', 'phase_key'], 'rgate_req_program_phase_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rollout_gate_approval_requests');

        Schema::table('tenant_rollout_playbook_config', function (Blueprint $table): void {
            if (Schema::hasColumn('tenant_rollout_playbook_config', 'gate_approval_policies')) {
                $table->dropColumn('gate_approval_policies');
            }
        });
    }
};
