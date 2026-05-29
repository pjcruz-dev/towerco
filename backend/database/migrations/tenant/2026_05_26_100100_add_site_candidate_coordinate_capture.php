<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_candidates', function (Blueprint $table): void {
            $table->string('coordinate_capture_method', 16)->nullable()->after('longitude');
            $table->decimal('coordinate_accuracy_m', 10, 2)->nullable()->after('coordinate_capture_method');
            $table->timestamp('coordinates_captured_at')->nullable()->after('coordinate_accuracy_m');
        });
    }

    public function down(): void
    {
        Schema::table('site_candidates', function (Blueprint $table): void {
            $table->dropColumn([
                'coordinate_capture_method',
                'coordinate_accuracy_m',
                'coordinates_captured_at',
            ]);
        });
    }
};
