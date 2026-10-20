<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_assistant_settings')) {
            Schema::create('ai_assistant_settings', function (Blueprint $table): void {
                $table->unsignedTinyInteger('id')->primary();
                $table->string('llm_provider', 32)->nullable();
                $table->text('cursor_api_key')->nullable();
                $table->text('gemini_api_key')->nullable();
                $table->text('openai_api_key')->nullable();
                $table->uuid('updated_by_user_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sidebar_nav_items')) {
            return;
        }

        if (DB::table('sidebar_nav_items')->where('key', 'group.administration.settings.ai-model')->exists()) {
            return;
        }

        $parentId = DB::table('sidebar_nav_items')->where('key', 'group.administration.settings')->value('id');
        if (! is_string($parentId) || $parentId === '') {
            return;
        }

        $now = now();
        DB::table('sidebar_nav_items')->insert([
            'id' => (string) Str::uuid(),
            'parent_id' => $parentId,
            'key' => 'group.administration.settings.ai-model',
            'type' => 'internal_page',
            'title' => 'AI model',
            'icon' => null,
            'href' => '/settings/ai-assistant/model',
            'entity_slug' => null,
            'permission_key' => null,
            'required_permissions' => json_encode(['tenant:manage']),
            'permissions_match' => 'all',
            'module' => 'ai_assistant',
            'sort_order' => 8,
            'is_system' => true,
            'is_visible' => true,
            'is_global_default' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('sidebar_nav_items')) {
            DB::table('sidebar_nav_items')->where('key', 'group.administration.settings.ai-model')->delete();
        }

        Schema::dropIfExists('ai_assistant_settings');
    }
};
