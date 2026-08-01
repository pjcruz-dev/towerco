<?php

declare(strict_types=1);

namespace Tests\Unit\Rollout;

use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Services\TcoSiteIdGenerator;
use App\Modules\Rollout\Support\RolloutOpsGeography;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class RolloutOpsGeographyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        Schema::connection('tenant')->create('rollout_programs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('rollout_ref')->nullable();
            $table->string('mno')->nullable();
            $table->string('project_type')->nullable();
            $table->string('status')->nullable();
            $table->string('region')->nullable();
            $table->string('territory')->nullable();
            $table->string('tco_site_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_prefers_territory_over_region(): void
    {
        $this->assertSame('NCR', RolloutOpsGeography::scopeCode('ncr', '13'));
        $this->assertSame('13', RolloutOpsGeography::scopeCode(null, '13'));
        $this->assertNull(RolloutOpsGeography::scopeCode(null, null));
    }

    public function test_for_program_uses_territory_first(): void
    {
        $program = RolloutProgram::query()->create([
            'rollout_ref' => 'RP-GEO',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'region' => '13',
            'territory' => 'SLZ',
        ]);

        $this->assertSame('SLZ', RolloutOpsGeography::forProgram($program));
    }

    public function test_tco_prefix_from_territory_codes(): void
    {
        $generator = new TcoSiteIdGenerator;

        $id = $generator->generate('NCR', 'globe', 'A', 26);
        $this->assertStringStartsWith('NC-GLOA26-', $id);

        $luz = $generator->generate('LUZ', 'smart', 'A', 26);
        $this->assertStringStartsWith('LZ-SMTA26-', $luz);

        $psa = $generator->generate('13', 'dito', 'A', 26);
        $this->assertStringStartsWith('R13-DITA26-', $psa);
    }

    public function test_generate_for_program_prefers_territory(): void
    {
        $program = RolloutProgram::query()->create([
            'rollout_ref' => 'RP-TCO',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'region' => '13',
            'territory' => 'NLZ',
        ]);

        $id = (new TcoSiteIdGenerator)->generateForProgram($program, 'A', 26);
        $this->assertStringStartsWith('NL-GLOA26-', $id);
    }
}
