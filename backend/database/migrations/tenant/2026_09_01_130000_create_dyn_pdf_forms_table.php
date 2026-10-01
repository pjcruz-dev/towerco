<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dyn_pdf_forms')) {
            return;
        }

        Schema::create('dyn_pdf_forms', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 64);
            $table->string('name');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type', 128)->default('application/pdf');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('catalog_code', 64)->nullable();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('code');
            $table->index('catalog_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dyn_pdf_forms');
    }
};
