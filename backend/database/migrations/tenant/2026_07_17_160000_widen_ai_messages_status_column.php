<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // insufficient_context is 21 chars; original column was VARCHAR(16).
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE ai_messages MODIFY status VARCHAR(32) NOT NULL DEFAULT \'completed\'');

            return;
        }

        Schema::table('ai_messages', function ($table): void {
            $table->string('status', 32)->default('completed')->change();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE ai_messages MODIFY status VARCHAR(16) NOT NULL DEFAULT \'completed\'');

            return;
        }

        Schema::table('ai_messages', function ($table): void {
            $table->string('status', 16)->default('completed')->change();
        });
    }
};
