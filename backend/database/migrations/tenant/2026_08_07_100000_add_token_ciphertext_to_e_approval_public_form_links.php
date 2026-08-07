<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('e_approval_public_form_links', function (Blueprint $table): void {
            $table->text('token_ciphertext')->nullable()->after('token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('e_approval_public_form_links', function (Blueprint $table): void {
            $table->dropColumn('token_ciphertext');
        });
    }
};
