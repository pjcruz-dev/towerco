<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AiAssistant\Services\ConversationService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssistantConversationExportController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $conversation,
        ConversationService $conversations,
    ): JsonResponse|StreamedResponse {
        $user = $request->user();
        abort_unless($user instanceof TenantUser && $user->can('ai_assistant:use'), 403);

        $validated = $request->validate([
            'format' => ['sometimes', 'string', 'in:json,csv'],
        ]);

        $format = strtolower((string) ($validated['format'] ?? 'json'));
        $model = $conversations->findVisibleOrFail($user, $conversation);
        $slug = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) ($model->title ?: 'conversation')) ?: 'conversation';
        $filename = 'assistant-'.trim($slug, '-').'-'.$model->id;

        if ($format === 'csv') {
            $csv = $conversations->exportCsv($model);

            return response()->streamDownload(
                static function () use ($csv): void {
                    echo $csv;
                },
                $filename.'.csv',
                ['Content-Type' => 'text/csv; charset=UTF-8'],
            );
        }

        return $this->ok($conversations->exportPayload($model));
    }
}
