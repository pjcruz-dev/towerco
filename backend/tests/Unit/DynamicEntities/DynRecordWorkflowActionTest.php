<?php

declare(strict_types=1);

namespace Tests\Unit\DynamicEntities;

use App\Modules\DynamicEntities\Services\DynEntityAdminService;
use App\Modules\DynamicEntities\Services\DynRecordService;
use App\Modules\DynamicEntities\Services\DynRecordWorkflowActionService;
use App\Modules\DynamicEntities\Support\DynEntityWorkflowActions;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DynRecordWorkflowActionTest extends TestCase
{
    private TenantUser $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $sqlite = [
            'driver' => 'sqlite',
            'database' => 'file:dyn_wf_'.uniqid('', true).'?mode=memory&cache=shared',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ];
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => $sqlite,
            'database.connections.tenant' => $sqlite,
            'toweros.logging.audit_structured_enabled' => false,
        ]);
        DB::purge('sqlite');
        DB::purge('tenant');
        DB::setDefaultConnection('sqlite');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_activity_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('module', 50);
            $table->string('action', 80);
            $table->string('category', 32)->nullable();
            $table->string('severity', 16)->nullable();
            $table->string('summary')->nullable();
            $table->string('reason')->nullable();
            $table->string('entity_type', 50)->nullable();
            $table->string('entity_id', 64)->nullable();
            $table->string('entity_label')->nullable();
            $table->uuid('actor_user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        $migration = require database_path('migrations/tenant/2026_08_29_140000_create_dynamic_entities_tables.php');
        $migration->up();
        foreach ([
            '2026_08_29_170000_add_dyn_field_group_layout_options.php',
            '2026_08_29_180000_add_dyn_entity_print_settings.php',
            '2026_08_29_190000_add_dyn_field_key_and_totals.php',
        ] as $file) {
            $step = require database_path('migrations/tenant/'.$file);
            $step->up();
        }

        $this->actor = new TenantUser([
            'id' => (string) Str::uuid(),
            'name' => 'Actor',
            'email' => 'actor@test.local',
        ]);
        $this->actor->id = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $this->actor->id,
            'name' => 'Actor',
            'email' => 'actor@test.local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function bir_2307_registry_exposes_post_and_cancel(): void
    {
        $actions = DynEntityWorkflowActions::builtinForSlug('bir_form_2307_certificates');
        $this->assertCount(2, $actions);
        $this->assertSame('post_certificate', $actions[0]['id']);
        $this->assertSame('cancel_certificate', $actions[1]['id']);

        $draft = DynEntityWorkflowActions::availableForStatus('bir_form_2307_certificates', 'Draft');
        $this->assertCount(1, $draft);
        $this->assertSame('post_certificate', $draft[0]['id']);

        $posted = DynEntityWorkflowActions::availableForStatus('bir_form_2307_certificates', 'Posted');
        $this->assertCount(1, $posted);
        $this->assertSame('cancel_certificate', $posted[0]['id']);
    }

    #[Test]
    public function configured_workflow_buttons_override_builtin(): void
    {
        $admin = app(DynEntityAdminService::class);
        $entity = $admin->createEntity([
            'name' => 'Bank Accounts',
            'slug' => 'bank_accounts_wf',
            'module_pack' => 'finance',
        ], $this->actor);

        $wf = $admin->createField($entity, [
            'label' => 'Workflows',
            'name' => 'workflows',
            'type' => 'text',
            'is_system_field' => true,
            'show_in_table' => false,
            'options_json' => [
                'buttons' => [
                    [
                        'id' => 'activate',
                        'label' => 'Activate',
                        'from_status' => 'Open',
                        'to_status' => 'Active',
                    ],
                ],
            ],
        ]);

        $entity->refresh()->load('fields');
        $actions = DynEntityWorkflowActions::forEntity($entity);
        $this->assertCount(1, $actions);
        $this->assertSame('activate', $actions[0]['id']);
        $this->assertSame('Activate', $actions[0]['label']);
        $this->assertNotNull($wf->id);
    }

    #[Test]
    public function posts_draft_then_cancels_posted_certificate(): void
    {
        $admin = app(DynEntityAdminService::class);
        $records = app(DynRecordService::class);
        $workflows = app(DynRecordWorkflowActionService::class);

        $entity = $admin->createEntity([
            'name' => 'BIR Form 2307 Certificates',
            'slug' => 'bir_form_2307_certificates',
            'module_pack' => 'finance',
        ], $this->actor);

        $admin->createField($entity, [
            'label' => 'Control Number',
            'name' => 'control_number',
            'type' => 'text',
            'is_required' => true,
            'show_in_table' => true,
        ]);

        $entity->refresh();
        $record = $records->create($entity, [
            'values' => ['control_number' => '2307-001'],
            'status' => 'Draft',
        ], $this->actor);

        $posted = $workflows->run($record->fresh(['entity']), 'post_certificate', $this->actor);
        $this->assertSame('Posted', $posted['status']);

        $cancelled = $workflows->run(
            \App\Modules\DynamicEntities\Models\DynRecord::query()->findOrFail($record->id)->load('entity'),
            'cancel_certificate',
            $this->actor,
        );
        $this->assertSame('Cancelled', $cancelled['status']);
        $this->assertSame([], $cancelled['workflow_actions'] ?? []);
    }

    #[Test]
    public function rejects_post_when_not_draft(): void
    {
        $admin = app(DynEntityAdminService::class);
        $records = app(DynRecordService::class);
        $workflows = app(DynRecordWorkflowActionService::class);

        $entity = $admin->createEntity([
            'name' => 'BIR Form 2307 Certificates',
            'slug' => 'bir_form_2307_certificates',
            'module_pack' => 'finance',
        ], $this->actor);

        $admin->createField($entity, [
            'label' => 'Control Number',
            'name' => 'control_number',
            'type' => 'text',
            'show_in_table' => true,
        ]);

        $entity->refresh();
        $record = $records->create($entity, [
            'values' => ['control_number' => '2307-002'],
            'status' => 'Posted',
        ], $this->actor);

        $this->expectException(ValidationException::class);
        $workflows->run($record->fresh(['entity']), 'post_certificate', $this->actor);
    }
}
