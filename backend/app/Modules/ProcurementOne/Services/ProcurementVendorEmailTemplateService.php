<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

final class ProcurementVendorEmailTemplateService
{
    public const SETTINGS_KEY = 'vendor_email_templates';

    public function __construct(
        private readonly ProcurementOneSettingsService $settings,
    ) {}

    /**
     * @return array{
     *     auto_on_approve: bool,
     *     auto_on_sent: bool,
     *     auto_on_rfq_invite: bool,
     *     auto_on_rfq_publish: bool,
     *     auto_on_rfq_reminder: bool,
     *     auto_on_rfq_close: bool,
     *     po_approved: array{enabled: bool, subject: string, body: string},
     *     po_sent: array{enabled: bool, subject: string, body: string},
     *     po_cancelled: array{enabled: bool, subject: string, body: string},
     *     po_voided: array{enabled: bool, subject: string, body: string},
     *     rfq_invited: array{enabled: bool, subject: string, body: string},
     *     rfq_published: array{enabled: bool, subject: string, body: string},
     *     rfq_reminder: array{enabled: bool, subject: string, body: string},
     *     rfq_closed: array{enabled: bool, subject: string, body: string}
     * }
     */
    public function templates(): array
    {
        $raw = $this->settings->getJson(self::SETTINGS_KEY);
        $defaults = $this->defaults();

        return [
            'auto_on_approve' => (bool) ($raw['auto_on_approve'] ?? $defaults['auto_on_approve']),
            'auto_on_sent' => (bool) ($raw['auto_on_sent'] ?? $defaults['auto_on_sent']),
            'auto_on_rfq_invite' => (bool) ($raw['auto_on_rfq_invite'] ?? $defaults['auto_on_rfq_invite']),
            'auto_on_rfq_publish' => (bool) ($raw['auto_on_rfq_publish'] ?? $defaults['auto_on_rfq_publish']),
            'auto_on_rfq_reminder' => (bool) ($raw['auto_on_rfq_reminder'] ?? $defaults['auto_on_rfq_reminder']),
            'auto_on_rfq_close' => (bool) ($raw['auto_on_rfq_close'] ?? $defaults['auto_on_rfq_close']),
            'po_approved' => $this->normalizeTemplate($raw['po_approved'] ?? null, $defaults['po_approved']),
            'po_sent' => $this->normalizeTemplate($raw['po_sent'] ?? null, $defaults['po_sent']),
            'po_cancelled' => $this->normalizeTemplate($raw['po_cancelled'] ?? null, $defaults['po_cancelled']),
            'po_voided' => $this->normalizeTemplate($raw['po_voided'] ?? null, $defaults['po_voided']),
            'rfq_invited' => $this->normalizeTemplate($raw['rfq_invited'] ?? null, $defaults['rfq_invited']),
            'rfq_published' => $this->normalizeTemplate($raw['rfq_published'] ?? null, $defaults['rfq_published']),
            'rfq_reminder' => $this->normalizeTemplate($raw['rfq_reminder'] ?? null, $defaults['rfq_reminder']),
            'rfq_closed' => $this->normalizeTemplate($raw['rfq_closed'] ?? null, $defaults['rfq_closed']),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     auto_on_approve: bool,
     *     auto_on_sent: bool,
     *     po_approved: array{enabled: bool, subject: string, body: string},
     *     po_sent: array{enabled: bool, subject: string, body: string},
     *     po_cancelled: array{enabled: bool, subject: string, body: string},
     *     po_voided: array{enabled: bool, subject: string, body: string}
     * }
     */
    public function validateAndNormalize(array $input): array
    {
        $defaults = $this->defaults();

        return [
            'auto_on_approve' => (bool) ($input['auto_on_approve'] ?? false),
            'auto_on_sent' => (bool) ($input['auto_on_sent'] ?? true),
            'auto_on_rfq_invite' => (bool) ($input['auto_on_rfq_invite'] ?? true),
            'auto_on_rfq_publish' => (bool) ($input['auto_on_rfq_publish'] ?? true),
            'auto_on_rfq_reminder' => (bool) ($input['auto_on_rfq_reminder'] ?? true),
            'auto_on_rfq_close' => (bool) ($input['auto_on_rfq_close'] ?? true),
            'po_approved' => $this->normalizeTemplate($input['po_approved'] ?? null, $defaults['po_approved']),
            'po_sent' => $this->normalizeTemplate($input['po_sent'] ?? null, $defaults['po_sent']),
            'po_cancelled' => $this->normalizeTemplate($input['po_cancelled'] ?? null, $defaults['po_cancelled']),
            'po_voided' => $this->normalizeTemplate($input['po_voided'] ?? null, $defaults['po_voided']),
            'rfq_invited' => $this->normalizeTemplate($input['rfq_invited'] ?? null, $defaults['rfq_invited']),
            'rfq_published' => $this->normalizeTemplate($input['rfq_published'] ?? null, $defaults['rfq_published']),
            'rfq_reminder' => $this->normalizeTemplate($input['rfq_reminder'] ?? null, $defaults['rfq_reminder']),
            'rfq_closed' => $this->normalizeTemplate($input['rfq_closed'] ?? null, $defaults['rfq_closed']),
        ];
    }

    public function isEnabledForEvent(string $event): bool
    {
        $templates = $this->templates();

        return match ($event) {
            'po_approved' => $templates['po_approved']['enabled'],
            'po_sent' => $templates['po_sent']['enabled'],
            'po_cancelled' => $templates['po_cancelled']['enabled'],
            'po_voided' => $templates['po_voided']['enabled'],
            'rfq_invited' => $templates['rfq_invited']['enabled'],
            'rfq_published' => $templates['rfq_published']['enabled'],
            'rfq_reminder' => $templates['rfq_reminder']['enabled'],
            'rfq_closed' => $templates['rfq_closed']['enabled'],
            default => false,
        };
    }

    /**
     * @param  array<string, string>  $variables
     */
    public function render(string $event, string $field, array $variables): string
    {
        $templates = $this->templates();
        $template = $templates[$event] ?? null;
        if (! is_array($template)) {
            return '';
        }

        $text = (string) ($template[$field] ?? '');

        foreach ($variables as $key => $value) {
            $text = str_replace('{{'.$key.'}}', $value, $text);
        }

        return trim($text);
    }

    /**
     * @return array{
     *     auto_on_approve: bool,
     *     auto_on_sent: bool,
     *     po_approved: array{enabled: bool, subject: string, body: string},
     *     po_sent: array{enabled: bool, subject: string, body: string},
     *     po_cancelled: array{enabled: bool, subject: string, body: string},
     *     po_voided: array{enabled: bool, subject: string, body: string}
     * }
     */
    private function defaults(): array
    {
        return [
            'auto_on_approve' => false,
            'auto_on_sent' => true,
            'auto_on_rfq_invite' => true,
            'auto_on_rfq_publish' => true,
            'auto_on_rfq_reminder' => true,
            'auto_on_rfq_close' => true,
            'po_approved' => [
                'enabled' => false,
                'subject' => 'Purchase order {{document_no}} — approved',
                'body' => "Dear {{supplier}},\n\nYour purchase order {{document_no}} has been approved for {{grand_total}}.\n\nView the printable PO: {{print_url}}\n\nRegards,\n{{brand}}",
            ],
            'po_sent' => [
                'enabled' => true,
                'subject' => 'Purchase order {{document_no}}',
                'body' => "Dear {{supplier}},\n\nPlease find our purchase order {{document_no}} for {{grand_total}}.\n\nPrint / PDF: {{print_url}}\n\nRegards,\n{{brand}}",
            ],
            'po_cancelled' => [
                'enabled' => true,
                'subject' => 'Purchase order {{document_no}} — cancelled',
                'body' => "Dear {{supplier}},\n\nPurchase order {{document_no}} has been cancelled.\n\nReason: {{reason}}\n\nRegards,\n{{brand}}",
            ],
            'po_voided' => [
                'enabled' => true,
                'subject' => 'Purchase order {{document_no}} — voided',
                'body' => "Dear {{supplier}},\n\nPurchase order {{document_no}} has been voided and is no longer valid.\n\nReason: {{reason}}\n\nRegards,\n{{brand}}",
            ],
            'rfq_invited' => [
                'enabled' => true,
                'subject' => 'Request for quotation {{rfq_document_no}} — {{rfq_title}}',
                'body' => "Dear {{vendor_name}},\n\nYou are invited to submit a quotation for {{rfq_document_no}} — {{rfq_title}}.\n\nBidding closes: {{closes_at}}\n\nSubmit your quote online: {{quote_url}}\n\nView all your RFQs: {{inbox_url}}\n\nRegards,\n{{brand}}",
            ],
            'rfq_published' => [
                'enabled' => true,
                'subject' => 'Bidding open — {{rfq_document_no}}',
                'body' => "Dear {{vendor_name}},\n\nBidding is now open for {{rfq_document_no}} — {{rfq_title}}.\n\nPlease submit your quotation before {{closes_at}}.\n\nSubmit online: {{quote_url}}\n\nView all your RFQs: {{inbox_url}}\n\nRegards,\n{{brand}}",
            ],
            'rfq_reminder' => [
                'enabled' => true,
                'subject' => 'Reminder — RFQ {{rfq_document_no}} closes in {{days_until_close}} day(s)',
                'body' => "Dear {{vendor_name}},\n\nThis is a reminder that bidding for {{rfq_document_no}} — {{rfq_title}} closes on {{closes_at}}.\n\nPlease submit your quotation: {{quote_url}}\n\nView all your RFQs: {{inbox_url}}\n\nRegards,\n{{brand}}",
            ],
            'rfq_closed' => [
                'enabled' => true,
                'subject' => 'Bidding closed — {{rfq_document_no}}',
                'body' => "Dear {{vendor_name}},\n\nBidding has closed for {{rfq_document_no}} — {{rfq_title}}.\n\nThank you for your participation.\n\nRegards,\n{{brand}}",
            ],
        ];
    }

    /**
     * @param  array{enabled: bool, subject: string, body: string}  $fallback
     * @return array{enabled: bool, subject: string, body: string}
     */
    private function normalizeTemplate(mixed $input, array $fallback): array
    {
        if (! is_array($input)) {
            return $fallback;
        }

        $subject = trim((string) ($input['subject'] ?? $fallback['subject']));
        $body = trim((string) ($input['body'] ?? $fallback['body']));

        return [
            'enabled' => (bool) ($input['enabled'] ?? $fallback['enabled']),
            'subject' => $subject !== '' ? $subject : $fallback['subject'],
            'body' => $body !== '' ? $body : $fallback['body'],
        ];
    }
}
