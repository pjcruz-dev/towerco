<?php

declare(strict_types=1);

namespace Tests\Unit\Workspace;

use App\Modules\Workspace\Support\WorkspaceAuditTaxonomy;
use PHPUnit\Framework\TestCase;

final class WorkspaceAuditTaxonomyTest extends TestCase
{
    public function test_classifies_security_events(): void
    {
        $result = WorkspaceAuditTaxonomy::classify('team_access', 'auth.impersonation.started');
        $this->assertSame('security', $result['category']);
        $this->assertSame('critical', $result['severity']);
    }

    public function test_classifies_access_events(): void
    {
        $result = WorkspaceAuditTaxonomy::classify('team_access', 'rbac.role_deleted');
        $this->assertSame('access', $result['category']);
        $this->assertSame('high', $result['severity']);
    }

    public function test_classifies_lifecycle_and_data_change(): void
    {
        $lifecycle = WorkspaceAuditTaxonomy::classify('e_approval', 'request_approved_final');
        $this->assertSame('lifecycle', $lifecycle['category']);
        $this->assertSame('medium', $lifecycle['severity']);

        $data = WorkspaceAuditTaxonomy::classify('e_approval', 'form_updated');
        $this->assertSame('data_change', $data['category']);
    }

    public function test_action_family(): void
    {
        $this->assertSame('auth', WorkspaceAuditTaxonomy::actionFamily('auth.login.success'));
        $this->assertSame('ticket', WorkspaceAuditTaxonomy::actionFamily('ticket.created'));
        $this->assertSame('submission', WorkspaceAuditTaxonomy::actionFamily('submission_cancelled'));
    }
}
