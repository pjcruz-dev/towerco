<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_sso_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->string('provider', 50)->default('azure');
            $table->string('issuer')->nullable();
            $table->string('client_id');
            $table->text('client_secret_encrypted');
            $table->string('tenant_identifier')->default('common');
            $table->json('group_mapping_rules')->nullable();
            $table->boolean('auto_provision_users')->default(true);
            $table->boolean('enabled')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'provider']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_sso_configs');
    }
};

