<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('tenant_database_backups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->string('status', 32);
            $table->string('storage_path')->nullable();
            $table->unsignedBigInteger('byte_size')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->string('database_name')->nullable();
            $table->string('triggered_by', 32);
            $table->string('actor_user_id', 36)->nullable();
            $table->string('actor_email')->nullable();
            $table->string('reason', 500)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('tenant_database_backups');
    }
};
