<?php

declare(strict_types=1);

namespace Tests\Unit\Rollout;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\TenantRolloutFile;
use App\Modules\Rollout\Services\TenantFileStorageService;
use App\Modules\Rollout\Support\RolloutFileContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class TenantFileStorageServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('tenant_files');
        Config::set('toweros.tenant_files.disk', 'tenant_files');
        Config::set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        Schema::connection('tenant')->create('rollout_programs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('rollout_ref')->unique();
            $table->string('mno', 32);
            $table->string('project_type', 32);
            $table->string('status', 32)->default('saq');
            $table->unsignedSmallInteger('sla_working_days')->default(120);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::connection('tenant')->create('tenant_rollout_files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rollout_program_id');
            $table->string('context', 64);
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->uuid('uploaded_by_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_store_persists_tenant_scoped_file_record(): void
    {
        $program = RolloutProgram::query()->create([
            'rollout_ref' => 'RP-FILE-001',
            'mno' => 'glo',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 120,
        ]);

        $user = TenantUser::withoutEvents(fn () => TenantUser::query()->create([
            'name' => 'Uploader',
            'email' => 'uploader@test.localhost',
            'password' => 'password',
        ]));

        $upload = UploadedFile::fake()->image('candidate.jpg');

        $service = app(TenantFileStorageService::class);
        $record = $service->store($upload, RolloutFileContext::CANDIDATE_PHOTO, $program, $user);

        $this->assertSame($program->id, $record->rollout_program_id);
        Storage::disk('tenant_files')->assertExists($record->stored_path);
        $this->assertDatabaseHas('tenant_rollout_files', [
            'id' => $record->id,
            'context' => RolloutFileContext::CANDIDATE_PHOTO,
        ], 'tenant');
    }

    public function test_assert_files_belong_to_rollout_rejects_foreign_file(): void
    {
        $programA = RolloutProgram::query()->create([
            'rollout_ref' => 'RP-A',
            'mno' => 'glo',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 120,
        ]);
        $programB = RolloutProgram::query()->create([
            'rollout_ref' => 'RP-B',
            'mno' => 'glo',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 120,
        ]);

        $file = TenantRolloutFile::query()->create([
            'rollout_program_id' => $programB->id,
            'context' => RolloutFileContext::CANDIDATE_PHOTO,
            'original_filename' => 'other.jpg',
            'stored_path' => 'tenant/b/other.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100,
        ]);

        $service = app(TenantFileStorageService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->assertFilesBelongToRollout([$file->id], $programA->id);
    }
}
