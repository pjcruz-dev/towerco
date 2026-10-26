<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\EApproval\Models\EApprovalWorkflowTemplate;
use App\Modules\EApproval\Notifications\EApprovalSubmissionNotification;
use App\Modules\EApproval\Support\EApprovalFormMailConfig;
use App\Modules\EApproval\Support\EApprovalWorkflowStepDefinitionSupport;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class EApprovalFormMailConfigTest extends TestCase
{
    public function test_missing_config_keeps_the_standard_email(): void
    {
        $this->assertTrue(EApprovalFormMailConfig::eventEnabled([], 'approved'));
        $this->assertSame('Approved', EApprovalFormMailConfig::statusWord([], 'approved'));
        $this->assertTrue(EApprovalFormMailConfig::isDefaultMail(EApprovalFormMailConfig::defaultMail()));

        $mail = $this->render('approved');

        $this->assertStringContainsString('Request approved', $mail->subject);
        $this->assertStringNotContainsString('Status:', implode("\n", $mail->introLines));
    }

    public function test_custom_status_and_note_are_added_to_the_email(): void
    {
        $form = new EApprovalForm([
            'name' => 'Leave',
            'metadata_json' => [
                'notifications' => [
                    'statuses' => ['approved' => 'Cleared'],
                    'events' => [
                        'approved' => ['enabled' => true, 'note' => 'File {{document_no}} with {{requestor_name}}.'],
                    ],
                ],
            ],
        ]);

        $mail = $this->render('approved', $form, 'Ada Lovelace');
        $body = implode("\n", $mail->introLines);

        $this->assertStringContainsString('Cleared — GEN-1', $mail->subject);
        $this->assertStringContainsString('Status: Cleared', $body);
        $this->assertStringContainsString('File GEN-1 with Ada Lovelace.', $body);
        $this->assertTrue(EApprovalFormMailConfig::eventEnabled($form->metadata_json, 'rejected'));
    }

    public function test_disabled_event_is_off_and_step_label_stays_on_the_standard_subject(): void
    {
        $metadata = [
            'notifications' => [
                'events' => ['submitted' => ['enabled' => false, 'note' => '']],
            ],
        ];
        $this->assertFalse(EApprovalFormMailConfig::eventEnabled($metadata, 'submitted'));
        $this->assertTrue(EApprovalFormMailConfig::eventEnabled($metadata, 'approved'));

        $form = new EApprovalForm(['name' => 'ISO', 'metadata_json' => []]);
        $template = new EApprovalWorkflowTemplate;
        $step = new EApprovalWorkflowStep([
            'step_order' => 1,
            'label' => 'Department',
            'condition' => ['mail' => ['note' => 'Check the quote.']],
        ]);
        $template->setRelation('steps', new Collection([$step]));
        $form->setRelation('workflowTemplate', $template);

        $submission = $this->submission($form);
        $notification = new EApprovalSubmissionNotification($submission, 'approval_assigned', null, null, null, 1);
        $mail = $notification->toMail(new TenantUser);
        $body = implode("\n", $mail->introLines);

        $this->assertStringContainsString('Approval required', $mail->subject);
        $this->assertStringContainsString('Status: Pending — Department', $body);
        $this->assertStringContainsString('Check the quote.', $body);
    }

    public function test_custom_subject_body_and_button_replace_the_standard_mail(): void
    {
        $form = new EApprovalForm([
            'name' => 'Leave',
            'metadata_json' => [
                'notifications' => [
                    'events' => [
                        'approved' => [
                            'enabled' => true,
                            'subject' => 'Done {{document_no}}',
                            'body' => 'Hello {{requestor_name}}',
                            'buttons' => [
                                ['label' => 'Open the file', 'target' => 'open_submission'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $mail = $this->render('approved', $form, 'Ada Lovelace');
        $body = implode("\n", $mail->introLines);

        $this->assertStringContainsString('Done GEN-1', $mail->subject);
        $this->assertStringContainsString('Hello Ada Lovelace', $body);
        $this->assertStringNotContainsString('Request approved', $mail->subject);
        $this->assertSame('Open the file', $mail->actionText);
        $this->assertStringContainsString('/e-approval/submissions/sub-id?tab=approvals', (string) $mail->actionUrl);
    }

    public function test_html_body_keeps_formatting_and_drops_scripts(): void
    {
        $form = new EApprovalForm([
            'name' => 'Leave',
            'metadata_json' => [
                'notifications' => [
                    'events' => [
                        'approved' => [
                            'body' => '<p style="font-family: Verdana; font-size: 11pt">Hello <strong>{{requestor_name}}</strong></p><script>alert(1)</script><p><a href="javascript:alert(1)">Bad</a> <a href="https://example.com/file">Open</a></p>',
                        ],
                    ],
                ],
            ],
        ]);

        $mail = $this->render('approved', $form, 'Ada Lovelace');
        $body = implode("\n", array_map(
            static fn (mixed $line): string => $line instanceof Htmlable ? $line->toHtml() : (string) $line,
            $mail->introLines,
        ));

        $this->assertStringContainsString('<strong>Ada Lovelace</strong>', $body);
        $this->assertStringContainsString('font-family: verdana', strtolower($body));
        $this->assertStringContainsString('https://example.com/file', $body);
        $this->assertStringNotContainsString('<script', strtolower($body));
        $this->assertStringNotContainsString('javascript:', strtolower($body));
    }

    public function test_approved_files_mail_uses_the_standard_copy_until_it_is_set(): void
    {
        $form = new EApprovalForm(['name' => 'ISO Approval DRAFT TEST 1']);
        $submission = new EApprovalSubmission(['document_no' => 'ATC-F-FINANCE-001']);
        $submission->setRelation('form', $form);

        $copy = EApprovalFormMailConfig::approvedFilesCopy(
            $submission,
            'MYAPP',
            'ATC-F-FINANCE-001',
            'ISO Approval DRAFT TEST 1',
            'Ada',
        );

        $this->assertTrue($copy['send']);
        $this->assertSame('MYAPP Approved files — ATC-F-FINANCE-001', $copy['subject']);
        $this->assertSame(['This request is fully approved. The current files are attached.'], $copy['lines']);
        $this->assertNull($copy['buttons']);

        $form->metadata_json = [
            'notifications' => [
                'events' => [
                    'approved_files' => [
                        'subject' => 'Files for {{document_no}}',
                        'body' => 'Please file {{form_name}}.',
                        'buttons' => [['label' => 'Open the file', 'target' => 'open_submission']],
                    ],
                ],
            ],
        ];

        $custom = EApprovalFormMailConfig::approvedFilesCopy(
            $submission,
            'MYAPP',
            'ATC-F-FINANCE-001',
            'ISO Approval DRAFT TEST 1',
            'Ada',
        );

        $this->assertSame('MYAPP Files for ATC-F-FINANCE-001', $custom['subject']);
        $this->assertSame(['Please file ISO Approval DRAFT TEST 1.'], $custom['lines']);

        $form->metadata_json = [
            'notifications' => [
                'events' => [
                    'approved_files' => [
                        'body' => 'TESTING{{document_no}}',
                    ],
                ],
            ],
        ];
        $glued = EApprovalFormMailConfig::approvedFilesCopy(
            $submission,
            'MYAPP',
            'ATC-F-FINANCE-001',
            'ISO Approval DRAFT TEST 1',
            'Ada',
        );
        $this->assertSame(['TESTING ATC-F-FINANCE-001'], $glued['lines']);
        $this->assertSame([['label' => 'Open the file', 'target' => 'open_submission']], $custom['buttons']);

        $form->metadata_json = [
            'notifications' => [
                'events' => ['approved_files' => ['enabled' => false]],
            ],
        ];
        $this->assertFalse(EApprovalFormMailConfig::approvedFilesCopy(
            $submission,
            'MYAPP',
            'ATC-F-FINANCE-001',
            'ISO Approval DRAFT TEST 1',
            'Ada',
        )['send']);
    }

    public function test_clearing_step_mail_removes_it_from_the_stored_condition(): void
    {
        $stored = EApprovalWorkflowStepDefinitionSupport::buildStoredCondition([
            'type' => 'user',
            'mail' => null,
            'condition' => ['mail' => ['note' => 'Old'], 'finance_action' => 'release'],
        ], 'user');

        $this->assertIsArray($stored);
        $this->assertArrayNotHasKey('mail', $stored);
        $this->assertSame('release', $stored['finance_action']);
    }

    private function render(string $event, ?EApprovalForm $form = null, ?string $actor = null): MailMessage
    {
        $notification = new EApprovalSubmissionNotification($this->submission($form), $event, $actor, null);

        return $notification->toMail(new TenantUser);
    }

    private function submission(?EApprovalForm $form = null): EApprovalSubmission
    {
        $form ??= new EApprovalForm(['name' => 'Leave']);
        $submission = new EApprovalSubmission([
            'document_no' => 'GEN-1',
        ]);
        $submission->id = 'sub-id';
        $requestor = new TenantUser(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
        $submission->setRelation('form', $form);
        $submission->setRelation('requestor', $requestor);

        return $submission;
    }
}
