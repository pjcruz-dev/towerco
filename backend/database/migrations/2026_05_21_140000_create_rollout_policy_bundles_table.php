<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rollout_policy_bundles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('status', 32)->default('draft');
            $table->foreignUuid('playbook_version_id')->constrained('rollout_playbook_versions')->cascadeOnDelete();
            $table->json('timeline_templates');
            $table->json('hidden_phases')->nullable();
            $table->json('gate_approval_policies')->nullable();
            $table->json('delivery_periods')->nullable();
            $table->text('changelog')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::table('tenant_playbook_bindings', function (Blueprint $table): void {
            $table->foreignUuid('rollout_policy_bundle_id')
                ->nullable()
                ->after('playbook_version_id')
                ->constrained('rollout_policy_bundles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_playbook_bindings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rollout_policy_bundle_id');
        });

        Schema::dropIfExists('rollout_policy_bundles');
    }
};
