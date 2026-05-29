<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('asset_code')->unique();
            $table->string('name');
            $table->string('category', 64);
            $table->string('status', 32)->default('in_warehouse');
            $table->string('rfid_tag')->nullable();
            $table->string('location_type', 32)->nullable();
            $table->uuid('location_id')->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->decimal('purchase_value', 14, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
