<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Providers;

use App\Modules\AiAssistant\Contracts\LlmProviderInterface;
use App\Modules\AiAssistant\Services\Actions\AssistantActionRegistry;
use App\Modules\AiAssistant\Services\Actions\CancelEApprovalSubmissionAction;
use App\Modules\AiAssistant\Services\Actions\ChangeItAssetStatusAction;
use App\Modules\AiAssistant\Services\Actions\ChangeTicketStatusAction;
use App\Modules\AiAssistant\Services\Actions\ConfirmTenantSqlAction;
use App\Modules\AiAssistant\Services\Actions\CreateHtmlReportFromPromptAction;
use App\Modules\AiAssistant\Services\Actions\CreateHtmlReportFromTemplateAction;
use App\Modules\AiAssistant\Services\Actions\CreateTenantUserAction;
use App\Modules\AiAssistant\Services\Actions\DeleteEApprovalFormAction;
use App\Modules\AiAssistant\Services\Actions\DraftEApprovalSubmissionAction;
use App\Modules\AiAssistant\Services\Actions\DraftTicketAction;
use App\Modules\AiAssistant\Services\Actions\PinHtmlReportToDashboardAction;
use App\Modules\AiAssistant\Services\Actions\RejectEApprovalSubmissionAction;
use App\Modules\AiAssistant\Services\Actions\RevertLastAdminChangeAction;
use App\Modules\AiAssistant\Services\Actions\SeedSampleItAssetsAction;
use App\Modules\AiAssistant\Services\Actions\SetUserActiveAction;
use App\Modules\AiAssistant\Services\Actions\SuggestDocumentMetadataAction;
use App\Modules\AiAssistant\Services\Actions\UpdateControlledDocumentAction;
use App\Modules\AiAssistant\Services\Actions\UpdateItAssetAction;
use App\Modules\AiAssistant\Services\Actions\UpdateUserRolesAction;
use App\Modules\AiAssistant\Services\TenantLlmCredentialService;
use App\Modules\AiAssistant\Services\Tools\AssistantToolRegistry;
use App\Modules\AiAssistant\Services\Tools\ExecuteSqlTool;
use App\Modules\AiAssistant\Services\Tools\FindItAssetTool;
use App\Modules\AiAssistant\Services\Tools\GetControlledDocumentByCodeTool;
use App\Modules\AiAssistant\Services\Tools\GetEApprovalSubmissionByDocumentNoTool;
use App\Modules\AiAssistant\Services\Tools\GetSiteByCodeTool;
use App\Modules\AiAssistant\Services\Tools\GetTicketByNumberTool;
use App\Modules\AiAssistant\Services\Tools\ListExpiringDocumentsTool;
use App\Modules\AiAssistant\Services\Tools\ListMyEApprovalSubmissionsTool;
use App\Modules\AiAssistant\Services\Tools\ListMyOpenTicketsTool;
use App\Modules\AiAssistant\Services\Tools\ListMyPendingApprovalsTool;
use App\Modules\AiAssistant\Services\Tools\SearchWorkspaceEntitiesTool;
use App\Modules\AiAssistant\Support\BedrockLlmProvider;
use App\Modules\AiAssistant\Support\CursorLlmProvider;
use App\Modules\AiAssistant\Support\GeminiLlmProvider;
use App\Modules\AiAssistant\Support\OpenAiLlmProvider;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class AiAssistantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LlmProviderInterface::class, static function ($app): LlmProviderInterface {
            $credentials = $app->make(TenantLlmCredentialService::class);
            $driver = $credentials->effectiveProvider();

            return match ($driver) {
                'local', 'fake', '' => throw new InvalidArgumentException('The local assistant model was removed. Set AI_ASSISTANT_LLM_PROVIDER to cursor, gemini, openai, or bedrock.'),
                'bedrock' => new BedrockLlmProvider(
                    region: (string) config('ai_assistant.bedrock.region'),
                    modelId: (string) config('ai_assistant.bedrock.chat_model_id'),
                    maxTokens: (int) config('ai_assistant.bedrock.max_tokens', 1024),
                    temperature: (float) config('ai_assistant.bedrock.temperature', 0.2),
                ),
                'openai', 'chatgpt' => new OpenAiLlmProvider(
                    apiKey: $credentials->apiKeyFor('openai'),
                    baseUrl: (string) config('ai_assistant.openai.base_url', 'https://api.openai.com/v1'),
                    modelId: (string) config('ai_assistant.openai.chat_model', 'gpt-4o-mini'),
                    maxTokens: (int) config('ai_assistant.openai.max_tokens', 1024),
                    temperature: (float) config('ai_assistant.openai.temperature', 0.2),
                    timeoutSeconds: (int) config('ai_assistant.openai.timeout', 60),
                    allowedModels: array_values(array_filter(
                        (array) config('ai_assistant.openai.chat_models', []),
                        static fn ($m): bool => is_string($m) && trim($m) !== '',
                    )),
                ),
                'cursor', 'cursor_ai' => new CursorLlmProvider(
                    apiKey: $credentials->apiKeyFor('cursor'),
                    baseUrl: (string) config('ai_assistant.cursor.base_url', 'https://api.cursor.com/v1'),
                    modelId: (string) config('ai_assistant.cursor.model', 'composer-2'),
                    allowedModels: array_values(array_filter(
                        (array) config('ai_assistant.cursor.chat_models', []),
                        static fn ($m): bool => is_string($m) && trim($m) !== '',
                    )),
                    maxWaitSeconds: (int) config('ai_assistant.cursor.max_wait_seconds', 120),
                    pollIntervalMs: (int) config('ai_assistant.cursor.poll_interval_ms', 1500),
                    requestTimeoutSeconds: (int) config('ai_assistant.cursor.timeout', 30),
                ),
                'gemini', 'google', 'google_ai', 'ai_studio' => new GeminiLlmProvider(
                    apiKey: $credentials->apiKeyFor('gemini'),
                    baseUrl: (string) config('ai_assistant.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'),
                    modelId: (string) config('ai_assistant.gemini.chat_model', 'gemini-3.6-flash'),
                    maxTokens: (int) config('ai_assistant.gemini.max_tokens', 2048),
                    temperature: (float) config('ai_assistant.gemini.temperature', 0.2),
                    timeoutSeconds: (int) config('ai_assistant.gemini.timeout', 60),
                    allowedModels: array_values(array_filter(
                        (array) config('ai_assistant.gemini.chat_models', []),
                        static fn ($m): bool => is_string($m) && trim($m) !== '',
                    )),
                ),
                default => throw new InvalidArgumentException("Unsupported AI LLM provider [{$driver}]."),
            };
        });
        $this->app->singleton(AssistantToolRegistry::class, static function ($app): AssistantToolRegistry {
            return new AssistantToolRegistry([
                $app->make(ListMyPendingApprovalsTool::class),
                $app->make(ListMyEApprovalSubmissionsTool::class),
                $app->make(GetEApprovalSubmissionByDocumentNoTool::class),
                $app->make(GetTicketByNumberTool::class),
                $app->make(GetControlledDocumentByCodeTool::class),
                $app->make(GetSiteByCodeTool::class),
                $app->make(ListMyOpenTicketsTool::class),
                $app->make(ListExpiringDocumentsTool::class),
                $app->make(SearchWorkspaceEntitiesTool::class),
                $app->make(FindItAssetTool::class),
                $app->make(ExecuteSqlTool::class),
            ]);
        });

        $this->app->singleton(AssistantActionRegistry::class, static function ($app): AssistantActionRegistry {
            return new AssistantActionRegistry([
                $app->make(DraftTicketAction::class),
                $app->make(DraftEApprovalSubmissionAction::class),
                $app->make(SuggestDocumentMetadataAction::class),
                $app->make(CreateHtmlReportFromPromptAction::class),
                $app->make(CreateHtmlReportFromTemplateAction::class),
                $app->make(PinHtmlReportToDashboardAction::class),
                $app->make(UpdateUserRolesAction::class),
                $app->make(SetUserActiveAction::class),
                $app->make(CreateTenantUserAction::class),
                $app->make(ChangeItAssetStatusAction::class),
                $app->make(UpdateItAssetAction::class),
                $app->make(ChangeTicketStatusAction::class),
                $app->make(UpdateControlledDocumentAction::class),
                $app->make(SeedSampleItAssetsAction::class),
                $app->make(RejectEApprovalSubmissionAction::class),
                $app->make(CancelEApprovalSubmissionAction::class),
                $app->make(DeleteEApprovalFormAction::class),
                $app->make(ConfirmTenantSqlAction::class),
                $app->make(RevertLastAdminChangeAction::class),
            ]);
        });
    }
}
