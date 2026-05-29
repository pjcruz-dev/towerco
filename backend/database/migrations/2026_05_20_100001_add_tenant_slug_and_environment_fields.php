<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('slug', 64)->nullable()->after('id');
            $table->string('brand_domain', 255)->nullable()->after('slug');
            $table->string('environment', 32)->default('production')->after('brand_domain');
            $table->string('tco_sequence_prefix', 8)->nullable()->after('environment');
            $table->string('parent_tenant_id')->nullable()->after('tco_sequence_prefix');

            $table->unique('slug');
            $table->foreign('parent_tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });

        Schema::create('tenant_domain_endpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->string('purpose', 32);
            $table->string('hostname', 255);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'purpose']);
            $table->unique('hostname');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_domain_endpoints');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['parent_tenant_id']);
            $table->dropUnique(['slug']);
            $table->dropColumn([
                'slug',
                'brand_domain',
                'environment',
                'tco_sequence_prefix',
                'parent_tenant_id',
            ]);
        });
    }
};
