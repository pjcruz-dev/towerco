<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rollout_playbook_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('version', 32)->unique();
            $table->string('name');
            $table->string('status', 32)->default('draft');
            $table->boolean('sla_working_days_only')->default(true);
            $table->json('delivery_periods');
            $table->json('timeline_templates');
            $table->json('milestone_cycle_targets');
            $table->json('form_schemas')->nullable();
            $table->text('changelog')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_playbook_bindings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreignUuid('playbook_version_id')->constrained('rollout_playbook_versions')->cascadeOnDelete();
            $table->string('upgrade_policy', 64)->default('new_rollouts_only');
            $table->timestamp('assigned_at');
            $table->timestamps();

            $table->unique('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_playbook_bindings');
        Schema::dropIfExists('rollout_playbook_versions');
    }
};
