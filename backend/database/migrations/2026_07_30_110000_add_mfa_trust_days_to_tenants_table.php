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
            if (! Schema::hasColumn('tenants', 'mfa_trust_days')) {
                $table->unsignedSmallInteger('mfa_trust_days')->default(7)->after('mfa_required');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'mfa_trust_days')) {
                $table->dropColumn('mfa_trust_days');
            }
        });
    }
};
