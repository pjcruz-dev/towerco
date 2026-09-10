<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Support\ModuleListSearchDsl;
use PHPUnit\Framework\TestCase;

final class ModuleListSearchDslTest extends TestCase
{
    public function test_parses_equality_and_residual(): void
    {
        $parsed = ModuleListSearchDsl::parse('status:ready invoice ACME priority=high');

        $this->assertSame([
            ['key' => 'status', 'op' => ModuleListSearchDsl::OP_EQ, 'value' => 'ready'],
            ['key' => 'priority', 'op' => ModuleListSearchDsl::OP_EQ, 'value' => 'high'],
        ], $parsed['clauses']);
        $this->assertSame('invoice ACME', $parsed['residual']);
    }

    public function test_parses_ne_and_contains(): void
    {
        $parsed = ModuleListSearchDsl::parse('status!=closed title~"network outage" hello');

        $this->assertSame(ModuleListSearchDsl::OP_NE, $parsed['clauses'][0]['op']);
        $this->assertSame('closed', $parsed['clauses'][0]['value']);
        $this->assertSame(ModuleListSearchDsl::OP_CONTAINS, $parsed['clauses'][1]['op']);
        $this->assertSame('network outage', $parsed['clauses'][1]['value']);
        $this->assertSame('hello', $parsed['residual']);
    }

    public function test_parses_comparison_operators(): void
    {
        $parsed = ModuleListSearchDsl::parse('created>=2026-01-01 updated<2026-09-01');

        $this->assertSame(ModuleListSearchDsl::OP_GTE, $parsed['clauses'][0]['op']);
        $this->assertSame('2026-01-01', $parsed['clauses'][0]['value']);
        $this->assertSame(ModuleListSearchDsl::OP_LT, $parsed['clauses'][1]['op']);
        $this->assertSame('2026-09-01', $parsed['clauses'][1]['value']);
        $this->assertSame('', $parsed['residual']);
    }

    public function test_parses_pipe_or_values(): void
    {
        $parsed = ModuleListSearchDsl::parse('status:pending|approved leftover');

        $this->assertSame([
            ['key' => 'status', 'op' => ModuleListSearchDsl::OP_EQ, 'value' => 'pending|approved'],
        ], $parsed['clauses']);
        $this->assertSame('leftover', $parsed['residual']);
    }

    public function test_parses_cross_key_or_groups(): void
    {
        $parsed = ModuleListSearchDsl::parse('status:open OR priority:high leftover');

        $this->assertCount(2, $parsed['groups']);
        $this->assertSame([
            ['key' => 'status', 'op' => ModuleListSearchDsl::OP_EQ, 'value' => 'open'],
        ], $parsed['groups'][0]['clauses']);
        $this->assertSame('', $parsed['groups'][0]['residual']);
        $this->assertSame([
            ['key' => 'priority', 'op' => ModuleListSearchDsl::OP_EQ, 'value' => 'high'],
        ], $parsed['groups'][1]['clauses']);
        $this->assertSame('leftover', $parsed['groups'][1]['residual']);
        $this->assertSame('leftover', $parsed['residual']);
    }

    public function test_cross_key_or_ignores_or_inside_quotes(): void
    {
        $parsed = ModuleListSearchDsl::parse('title~"a OR b" status:open');

        $this->assertCount(1, $parsed['groups']);
        $this->assertSame('a OR b', $parsed['clauses'][0]['value']);
        $this->assertSame('open', $parsed['clauses'][1]['value']);
    }
}
