<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenants', 'coming_soon_enabled')) {
                $table->boolean('coming_soon_enabled')->default(false)->after('environment');
            }
            if (! Schema::hasColumn('tenants', 'coming_soon_message')) {
                $table->string('coming_soon_message', 500)->nullable()->after('coming_soon_enabled');
            }
            if (! Schema::hasColumn('tenants', 'coming_soon_contact')) {
                $table->string('coming_soon_contact', 255)->nullable()->after('coming_soon_message');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $cols = array_values(array_filter([
                Schema::hasColumn('tenants', 'coming_soon_enabled') ? 'coming_soon_enabled' : null,
                Schema::hasColumn('tenants', 'coming_soon_message') ? 'coming_soon_message' : null,
                Schema::hasColumn('tenants', 'coming_soon_contact') ? 'coming_soon_contact' : null,
            ]));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
