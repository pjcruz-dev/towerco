<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_binder_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->json('tree_json');
            $table->foreignUuid('updated_by_id')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_binder_templates');
    }
};
