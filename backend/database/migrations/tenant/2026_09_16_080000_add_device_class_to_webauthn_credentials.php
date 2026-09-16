<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webauthn_credentials', function (Blueprint $table): void {
            if (! Schema::hasColumn('webauthn_credentials', 'device_class')) {
                $table->string('device_class', 32)->nullable()->after('label');
            }
            if (! Schema::hasColumn('webauthn_credentials', 'authenticator_attachment')) {
                $table->string('authenticator_attachment', 32)->nullable()->after('device_class');
            }
        });
    }

    public function down(): void
    {
        Schema::table('webauthn_credentials', function (Blueprint $table): void {
            if (Schema::hasColumn('webauthn_credentials', 'authenticator_attachment')) {
                $table->dropColumn('authenticator_attachment');
            }
            if (Schema::hasColumn('webauthn_credentials', 'device_class')) {
                $table->dropColumn('device_class');
            }
        });
    }
};
