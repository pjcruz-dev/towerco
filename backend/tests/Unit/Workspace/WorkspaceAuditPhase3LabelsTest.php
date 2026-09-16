<?php

declare(strict_types=1);

namespace Tests\Unit\Workspace;

use App\Modules\Workspace\Support\WorkspaceAuditActionLabel;
use PHPUnit\Framework\TestCase;

final class WorkspaceAuditPhase3LabelsTest extends TestCase
{
    public function test_dual_write_action_labels(): void
    {
        $this->assertSame('Purchase requisition cancelled', WorkspaceAuditActionLabel::label('purchase_requisition.cancelled'));
        $this->assertSame('Ticket created', WorkspaceAuditActionLabel::label('ticket.created'));
        $this->assertSame('Ticket auto-closed', WorkspaceAuditActionLabel::label('ticket.auto_closed'));
        $this->assertSame('Passkey enrolled', WorkspaceAuditActionLabel::label('auth.webauthn.register'));
        $this->assertSame('Signed in with passkey', WorkspaceAuditActionLabel::label('auth.webauthn.login'));
        $this->assertSame('Role created', WorkspaceAuditActionLabel::label('rbac.role_created'));
        $this->assertSame('Rollout program created', WorkspaceAuditActionLabel::label('rollout.created'));
        $this->assertSame('Signed in', WorkspaceAuditActionLabel::label('auth.login.success'));
    }
}
