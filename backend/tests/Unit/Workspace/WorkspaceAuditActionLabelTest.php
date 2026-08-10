<?php

declare(strict_types=1);

namespace Tests\Unit\Workspace;

use App\Modules\Workspace\Support\WorkspaceAuditActionLabel;
use PHPUnit\Framework\TestCase;

final class WorkspaceAuditActionLabelTest extends TestCase
{
    public function test_known_actions_have_human_labels(): void
    {
        $this->assertSame('Submission created', WorkspaceAuditActionLabel::label('submission_created'));
        $this->assertSame('Signed in', WorkspaceAuditActionLabel::label('auth.login.success'));
        $this->assertSame('Request approved', WorkspaceAuditActionLabel::label('request_approved_final'));
    }

    public function test_unknown_actions_are_title_cased(): void
    {
        $this->assertSame('Custom Event Name', WorkspaceAuditActionLabel::label('custom.event_name'));
    }
}
