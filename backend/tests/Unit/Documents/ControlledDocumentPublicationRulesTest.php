<?php

declare(strict_types=1);

namespace Tests\Unit\Documents;

use App\Modules\Documents\Support\ControlledDocumentPublicationRules;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

final class ControlledDocumentPublicationRulesTest extends TestCase
{
    public function test_only_policy_and_work_instruction_types_wait_for_training(): void
    {
        $this->assertTrue(ControlledDocumentPublicationRules::requiresTraining('P', 'Policies and Procedures'));
        $this->assertTrue(ControlledDocumentPublicationRules::requiresTraining('W', 'Work Instructions'));
        $this->assertTrue(ControlledDocumentPublicationRules::requiresTraining('Policies and Procedures', 'Policies and Procedures'));
        $this->assertTrue(ControlledDocumentPublicationRules::requiresTraining('Work Instruction', 'Work Instruction'));
        $this->assertTrue(ControlledDocumentPublicationRules::requiresTraining('P', 'P'));
        $this->assertFalse(ControlledDocumentPublicationRules::requiresTraining('P', 'Purchase order'));
        $this->assertFalse(ControlledDocumentPublicationRules::requiresTraining('M', 'Manual'));
        $this->assertFalse(ControlledDocumentPublicationRules::requiresTraining('F', 'Forms'));
        $this->assertFalse(ControlledDocumentPublicationRules::requiresTraining('', null));
    }

    public function test_effective_date_cannot_precede_the_release_day(): void
    {
        $published = Carbon::parse('2026-11-05 14:30:00');

        $future = ControlledDocumentPublicationRules::alignEffectiveDate(Carbon::parse('2026-12-01'), $published);
        $this->assertSame('2026-12-01', $future->toDateString());

        $sameDay = ControlledDocumentPublicationRules::alignEffectiveDate(Carbon::parse('2026-11-05'), $published);
        $this->assertSame('2026-11-05', $sameDay->toDateString());

        $lapsed = ControlledDocumentPublicationRules::alignEffectiveDate(Carbon::parse('2026-10-01'), $published);
        $this->assertSame('2026-11-05', $lapsed->toDateString());

        $blank = ControlledDocumentPublicationRules::alignEffectiveDate(null, $published);
        $this->assertSame('2026-11-05', $blank->toDateString());
    }

    public function test_listed_document_types_are_optional(): void
    {
        $this->assertTrue(ControlledDocumentPublicationRules::matchesListedType('P', 'Policies and Procedures', []));
        $this->assertTrue(ControlledDocumentPublicationRules::matchesListedType('P', 'Policies and Procedures', ['Policies and Procedures']));
        $this->assertTrue(ControlledDocumentPublicationRules::matchesListedType('W', 'Work Instructions', ['Work Instructions']));
        $this->assertFalse(ControlledDocumentPublicationRules::matchesListedType('F', 'Forms', ['Policies and Procedures', 'Work Instructions']));
        $this->assertFalse(ControlledDocumentPublicationRules::matchesListedType('P', 'Purchase order', ['Policies and Procedures']));
    }
}
