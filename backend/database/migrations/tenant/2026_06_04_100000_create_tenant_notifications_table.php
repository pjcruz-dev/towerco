<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 120)->nullable();
            $table->string('module', 32);
            $table->string('type', 80);
            $table->string('category', 16)->default('update');
            $table->string('subject_type', 64)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->string('context_primary', 128)->nullable();
            $table->string('context_secondary', 255)->nullable();
            $table->text('message')->nullable();
            $table->text('body_preview')->nullable();
            $table->string('href', 512)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_read', 'created_at']);
            $table->index(['user_id', 'module', 'category', 'is_read']);
            $table->index(['user_id', 'module', 'created_at']);
        });

        if (Schema::hasTable('e_approval_notifications')) {
            $rows = DB::table('e_approval_notifications')->orderBy('created_at')->get();
            foreach ($rows as $row) {
                DB::table('tenant_notifications')->insert([
                    'id' => $row->id,
                    'user_id' => $row->user_id,
                    'actor_user_id' => $row->actor_user_id ?? null,
                    'actor_name' => $row->actor_name ?? null,
                    'module' => 'e_approval',
                    'type' => $row->type,
                    'category' => $row->category ?? 'update',
                    'subject_type' => $row->submission_id ? 'submission' : null,
                    'subject_id' => $row->submission_id,
                    'context_primary' => $row->document_no ?? null,
                    'context_secondary' => $row->form_name ?? null,
                    'message' => $row->message,
                    'body_preview' => $row->body_preview ?? null,
                    'href' => $row->href ?? null,
                    'is_read' => (bool) $row->is_read,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_notifications');
    }
};
