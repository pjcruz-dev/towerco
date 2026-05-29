<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiber_routes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('status', 32)->default('planned');
            $table->foreignUuid('from_site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignUuid('to_site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->decimal('length_km', 10, 3)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiber_routes');
    }
};
