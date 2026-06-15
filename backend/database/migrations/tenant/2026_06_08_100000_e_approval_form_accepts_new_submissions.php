<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('e_approval_forms', function (Blueprint $table) {
            $table->boolean('accepts_new_submissions')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('e_approval_forms', function (Blueprint $table) {
            $table->dropColumn('accepts_new_submissions');
        });
    }
};
