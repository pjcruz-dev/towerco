<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Notifications\EApprovalSubmissionNotification;
use App\Modules\EApproval\Support\EApprovalSubmissionSource;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

final class EApprovalSubmissionNotificationMailTest extends TestCase
{
    public function test_approval_assigned_mail_shows_external_submitter_not_sponsor(): void
    {
        $sponsor = new TenantUser(['name' => 'Tenant administrator', 'email' => 'admin@atc.localhost']);
        $sponsor->id = 'sponsor-id';

        $form = new EApprovalForm(['name' => 'Document Approval']);
        $form->id = 'form-id';

        $submission = new EApprovalSubmission([
            'document_no' => 'GEN-F-00004',
            'submission_source' => EApprovalSubmissionSource::EXTERNAL,
            'external_submitter_name' => 'PJ TEST VENDOR',
            'external_submitter_email' => 'vendor@example.com',
        ]);
        $submission->id = 'sub-id';
        $submission->setRelation('form', $form);
        $submission->setRelation('requestor', $sponsor);

        $mail = $this->renderMail($submission, 'approval_assigned');

        $this->assertStringContainsString('PJ TEST VENDOR', implode("\n", $mail->introLines));
        $this->assertStringContainsString('vendor@example.com', implode("\n", $mail->introLines));
        $this->assertStringContainsString('Tenant administrator', implode("\n", $mail->introLines));
        $this->assertStringNotContainsString('Requestor: Tenant administrator', implode("\n", $mail->introLines));
        $this->assertSame(
            'http://localhost/e-approval/submissions/sub-id?tab=workflow',
            $mail->actionUrl,
        );
    }

    public function test_external_received_mail_uses_public_submission_copy(): void
    {
        $submission = new EApprovalSubmission([
            'document_no' => 'GEN-F-00005',
            'submission_source' => EApprovalSubmissionSource::EXTERNAL,
            'external_submitter_name' => 'Acme Vendor',
            'external_submitter_email' => 'acme@example.com',
        ]);
        $submission->id = 'sub-id';
        $submission->setRelation('form', new EApprovalForm(['name' => 'Vendor intake']));
        $submission->setRelation('requestor', new TenantUser(['name' => 'Sponsor User', 'email' => 'sponsor@test.localhost']));

        $mail = $this->renderMail($submission, 'external_received');

        $this->assertStringContainsString('External submission received', $mail->subject);
        $this->assertStringContainsString('public form link', implode("\n", $mail->introLines));
    }

    public function test_manual_follow_up_mail_prompts_approver_to_review(): void
    {
        $submission = new EApprovalSubmission(['document_no' => 'GEN-F-00006']);
        $submission->id = 'sub-id';
        $submission->setRelation('form', new EApprovalForm(['name' => 'Policy approval']));
        $submission->setRelation('requestor', new TenantUser(['name' => 'Requestor User', 'email' => 'requestor@test.localhost']));

        $mail = $this->renderMail($submission, 'manual_follow_up', 'Requestor User');

        $this->assertStringContainsString('Follow-up reminder', $mail->subject);
        $this->assertStringContainsString('follow-up reminder', implode("\n", $mail->introLines));
        $this->assertStringContainsString('Requestor User', implode("\n", $mail->introLines));
        $this->assertSame(
            'http://localhost/e-approval/submissions/sub-id?tab=workflow',
            $mail->actionUrl,
        );
    }

    private function renderMail(EApprovalSubmission $submission, string $event, ?string $actorName = null): MailMessage
    {
        $notification = new EApprovalSubmissionNotification($submission, $event, $actorName, null);

        return $notification->toMail(new TenantUser());
    }
}
