<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticketing_tickets') || Schema::hasColumn('ticketing_tickets', 'custom_values_json')) {
            return;
        }

        Schema::table('ticketing_tickets', function (Blueprint $table): void {
            $table->json('custom_values_json')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ticketing_tickets') || ! Schema::hasColumn('ticketing_tickets', 'custom_values_json')) {
            return;
        }

        Schema::table('ticketing_tickets', function (Blueprint $table): void {
            $table->dropColumn('custom_values_json');
        });
    }
};
