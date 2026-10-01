<?php

declare(strict_types=1);

namespace Tests\Unit\DynamicEntities;

use App\Modules\DynamicEntities\Support\DynEntityHookDsl;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DynEntityHookDslTest extends TestCase
{
    #[Test]
    public function it_normalizes_mirror_field_definition(): void
    {
        $definition = DynEntityHookDsl::normalize([
            'when' => [
                ['field' => 'subsidiary_id', 'op' => 'filled'],
                ['field' => 'bogus', 'op' => 'not-a-real-op'],
            ],
            'actions' => [
                ['type' => 'mirror_field', 'from' => 'subsidiary_id', 'to' => 'company_id'],
                ['type' => 'unknown', 'field' => 'x'],
                ['type' => 'mirror_field', 'from' => '', 'to' => 'company_id'],
            ],
        ]);

        $this->assertSame(
            [['field' => 'subsidiary_id', 'op' => 'filled']],
            $definition['when'],
        );
        $this->assertSame(
            [['type' => 'mirror_field', 'from' => 'subsidiary_id', 'to' => 'company_id']],
            $definition['actions'],
        );
    }

    #[Test]
    public function it_mirrors_subsidiary_id_onto_company_id(): void
    {
        $definition = DynEntityHookDsl::normalize([
            'when' => [['field' => 'subsidiary_id', 'op' => 'filled']],
            'actions' => [
                ['type' => 'mirror_field', 'from' => 'subsidiary_id', 'to' => 'company_id'],
            ],
        ]);

        $this->assertTrue(DynEntityHookDsl::matchesWhen($definition, [
            'subsidiary_id' => 'sub-1',
        ]));

        $applied = DynEntityHookDsl::apply($definition, [
            'subsidiary_id' => 'sub-1',
            'company_id' => null,
        ]);

        $this->assertSame('sub-1', $applied['company_id']);
        $this->assertSame('sub-1', $applied['subsidiary_id']);
    }

    #[Test]
    public function it_skips_when_condition_not_met(): void
    {
        $definition = DynEntityHookDsl::normalize([
            'when' => [['field' => 'subsidiary_id', 'op' => 'filled']],
            'actions' => [
                ['type' => 'mirror_field', 'from' => 'subsidiary_id', 'to' => 'company_id'],
            ],
        ]);

        $this->assertFalse(DynEntityHookDsl::matchesWhen($definition, [
            'subsidiary_id' => '',
        ]));
    }

    #[Test]
    public function it_resolves_set_field_tokens(): void
    {
        $definition = DynEntityHookDsl::normalize([
            'when' => [],
            'actions' => [
                ['type' => 'set_field', 'field' => 'owner_id', 'value' => '$actor_id'],
                ['type' => 'clear_field', 'field' => 'temp_flag'],
            ],
        ]);

        $applied = DynEntityHookDsl::apply($definition, [
            'temp_flag' => '1',
        ], [
            'actor_id' => 'user-99',
            'record_id' => 'rec-1',
        ]);

        $this->assertSame('user-99', $applied['owner_id']);
        $this->assertNull($applied['temp_flag']);
    }

    #[Test]
    public function it_normalizes_events(): void
    {
        $events = DynEntityHookDsl::normalizeEvents([
            'before_create',
            'BEFORE_UPDATE',
            'before_create',
            'not_real',
        ]);

        $this->assertSame(['before_create', 'before_update'], $events);
    }
}
