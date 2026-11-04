<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('e_approval_comments', function (Blueprint $table): void {
            $table->string('field_name', 64)->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('e_approval_comments', function (Blueprint $table): void {
            $table->dropColumn('field_name');
        });
    }
};
