<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Modules\Notifications\Mail\MicrosoftGraphMailTokenProvider;
use App\Modules\Notifications\Mail\MicrosoftGraphTransport;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

final class MicrosoftGraphTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cache.default', 'array');
        Config::set('mail.default', 'microsoft-graph');
        Config::set('mail.from.address', 'noreply@alliancetowers.com');
        Config::set('mail.from.name', 'TowerOS');
        Config::set('mail.mailers.microsoft-graph', [
            'transport' => 'microsoft-graph',
            'save_to_sent_items' => false,
        ]);
        Config::set('services.microsoft_graph_mail', [
            'client_id' => 'mail-client',
            'client_secret' => 'mail-secret',
            'tenant' => '11111111-1111-1111-1111-111111111111',
            'save_to_sent_items' => false,
        ]);
        Cache::flush();
        Mail::forgetMailers();
    }

    public function test_send_posts_graph_send_mail_with_html_body(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'graph-token',
                'expires_in' => 3600,
            ], 200),
            'graph.microsoft.com/*' => Http::response(null, 202),
        ]);

        Mail::mailer('microsoft-graph')->html('<p>Hello</p>', function ($message): void {
            $message->to('prcruz@alliancetowers.com', 'PJ')
                ->subject('TowerOS Graph mail test');
        });

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), 'login.microsoftonline.com')) {
                return false;
            }

            return $request['client_id'] === 'mail-client'
                && $request['scope'] === 'https://graph.microsoft.com/.default';
        });

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/users/') || ! str_contains($request->url(), '/sendMail')) {
                return false;
            }

            $data = $request->data();

            return str_contains($request->url(), rawurlencode('noreply@alliancetowers.com'))
                && ($data['message']['subject'] ?? null) === 'TowerOS Graph mail test'
                && ($data['message']['body']['contentType'] ?? null) === 'HTML'
                && str_contains((string) ($data['message']['body']['content'] ?? ''), 'Hello')
                && ($data['message']['toRecipients'][0]['emailAddress']['address'] ?? null) === 'prcruz@alliancetowers.com'
                && ($data['saveToSentItems'] ?? null) === false
                && $request->hasHeader('Authorization', 'Bearer graph-token');
        });
    }

    public function test_send_includes_file_attachments(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'graph-token',
                'expires_in' => 3600,
            ], 200),
            'graph.microsoft.com/*' => Http::response(null, 202),
        ]);

        $email = (new Email)
            ->from('noreply@alliancetowers.com')
            ->to('ops@example.com')
            ->subject('With attachment')
            ->text('See attach')
            ->attach('report-bytes', 'report.txt', 'text/plain');

        $transport = new MicrosoftGraphTransport(
            app(MicrosoftGraphMailTokenProvider::class),
            saveToSentItems: true,
        );
        $transport->send($email);

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/sendMail')) {
                return false;
            }

            $attachment = $request->data()['message']['attachments'][0] ?? null;

            return ($request->data()['saveToSentItems'] ?? null) === true
                && ($attachment['@odata.type'] ?? null) === '#microsoft.graph.fileAttachment'
                && ($attachment['name'] ?? null) === 'report.txt'
                && ($attachment['contentBytes'] ?? null) === base64_encode('report-bytes');
        });
    }

    public function test_token_provider_rejects_common_tenant(): void
    {
        Config::set('services.microsoft_graph_mail.tenant', 'common');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('directory GUID');

        app(MicrosoftGraphMailTokenProvider::class)->getAccessToken();
    }

    public function test_graph_error_becomes_transport_exception(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'graph-token',
                'expires_in' => 3600,
            ], 200),
            'graph.microsoft.com/*' => Http::response([
                'error' => [
                    'code' => 'ErrorAccessDenied',
                    'message' => 'Access is denied. Check credentials and try again.',
                ],
            ], 403),
        ]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Access is denied');

        Mail::mailer('microsoft-graph')->raw('fail', function ($message): void {
            $message->to('ops@example.com')->subject('Fail');
        });
    }

    public function test_retries_send_once_after_unauthorized(): void
    {
        $sendAttempts = 0;

        Http::fake(function (Request $request) use (&$sendAttempts) {
            if (str_contains($request->url(), 'login.microsoftonline.com')) {
                return Http::response([
                    'access_token' => 'fresh-token',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($request->url(), '/sendMail')) {
                $sendAttempts++;
                if ($sendAttempts === 1) {
                    return Http::response(['error' => ['message' => 'expired']], 401);
                }

                return Http::response(null, 202);
            }

            return Http::response('not found', 404);
        });

        Cache::put(
            'microsoft_graph_mail_token:11111111-1111-1111-1111-111111111111:mail-client',
            'stale-token',
            3600,
        );

        Mail::mailer('microsoft-graph')->raw('retry', function ($message): void {
            $message->to('ops@example.com')->subject('Retry');
        });

        $this->assertSame(2, $sendAttempts);
        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), '/sendMail')
                && $request->hasHeader('Authorization', 'Bearer fresh-token');
        });
    }
}
