<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_rollout_playbook_config')
            && ! Schema::hasColumn('tenant_rollout_playbook_config', 'gate_approval_escalation_working_days')) {
            Schema::table('tenant_rollout_playbook_config', function (Blueprint $table): void {
                $table->unsignedSmallInteger('gate_approval_escalation_working_days')
                    ->default(3)
                    ->after('gate_approval_policies');
            });
        }

        if (Schema::hasTable('rollout_gate_approval_requests')) {
            Schema::table('rollout_gate_approval_requests', function (Blueprint $table): void {
                if (! Schema::hasColumn('rollout_gate_approval_requests', 'current_step_started_at')) {
                    $table->timestamp('current_step_started_at')->nullable()->after('submitted_at');
                }
                if (! Schema::hasColumn('rollout_gate_approval_requests', 'last_escalated_at')) {
                    $table->timestamp('last_escalated_at')->nullable()->after('current_step_started_at');
                }
            });
        }

        if (! Schema::hasTable('rollout_gate_approval_delegations')) {
            Schema::create('rollout_gate_approval_delegations', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('delegator_id')->constrained('users')->cascadeOnDelete();
                $table->foreignUuid('delegate_id')->constrained('users')->cascadeOnDelete();
                $table->string('role_key', 64)->nullable();
                $table->date('valid_from');
                $table->date('valid_until')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['delegate_id', 'is_active'], 'rgate_del_delegate_active_idx');
                $table->index(['delegator_id', 'is_active'], 'rgate_del_delegator_active_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rollout_gate_approval_delegations');

        if (Schema::hasTable('rollout_gate_approval_requests')) {
            Schema::table('rollout_gate_approval_requests', function (Blueprint $table): void {
                if (Schema::hasColumn('rollout_gate_approval_requests', 'last_escalated_at')) {
                    $table->dropColumn('last_escalated_at');
                }
                if (Schema::hasColumn('rollout_gate_approval_requests', 'current_step_started_at')) {
                    $table->dropColumn('current_step_started_at');
                }
            });
        }

        if (Schema::hasTable('tenant_rollout_playbook_config')
            && Schema::hasColumn('tenant_rollout_playbook_config', 'gate_approval_escalation_working_days')) {
            Schema::table('tenant_rollout_playbook_config', function (Blueprint $table): void {
                $table->dropColumn('gate_approval_escalation_working_days');
            });
        }
    }
};
