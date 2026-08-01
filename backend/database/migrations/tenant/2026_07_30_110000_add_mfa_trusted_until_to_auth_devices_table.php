<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_devices', function (Blueprint $table) {
            if (! Schema::hasColumn('auth_devices', 'mfa_trusted_until')) {
                $table->timestamp('mfa_trusted_until')->nullable()->after('trust_level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('auth_devices', function (Blueprint $table) {
            if (Schema::hasColumn('auth_devices', 'mfa_trusted_until')) {
                $table->dropColumn('mfa_trusted_until');
            }
        });
    }
};
