<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Modules\Identity\Models\ModuleListSharedLayout;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Models\UserUiPreference;
use App\Modules\Identity\Services\UserUiPreferenceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PublishDashboardLayoutClearsPersonalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set([
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'toweros.logging.audit_structured_enabled' => false,
        ]);

        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');

        Schema::create('module_list_shared_layouts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('storage_key', 160)->unique();
            $table->json('layouts_json');
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('user_ui_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('preference_key', 160);
            $table->json('value_json');
            $table->timestamps();
        });
    }

    public function test_publishing_a_dashboard_layout_replaces_personal_copies(): void
    {
        $key = 'dashboard-layout.toweros.ticketing.dashboard.layout';
        UserUiPreference::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => (string) Str::uuid(),
            'preference_key' => $key,
            'value_json' => ['enabledWidgetIds' => ['kpis']],
        ]);
        UserUiPreference::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => (string) Str::uuid(),
            'preference_key' => 'dashboard-layout.toweros.workspace.dashboard.layout',
            'value_json' => ['enabledWidgetIds' => ['table']],
        ]);

        $actor = new class extends TenantUser
        {
            public function can($abilities, $arguments = []): bool
            {
                return true;
            }
        };
        $actor->id = (string) Str::uuid();

        $shared = app(UserUiPreferenceService::class)->putShared($actor, $key, [
            'layout' => ['enabledWidgetIds' => ['recent_tickets']],
        ]);

        $this->assertSame(['recent_tickets'], $shared['layout']['enabledWidgetIds']);
        $this->assertSame(0, UserUiPreference::query()->where('preference_key', $key)->count());
        $this->assertSame(1, UserUiPreference::query()->where('preference_key', 'dashboard-layout.toweros.workspace.dashboard.layout')->count());
        $this->assertSame(1, ModuleListSharedLayout::query()->where('storage_key', $key)->count());
    }
}
