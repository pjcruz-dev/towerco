<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services;

use App\Modules\AiAssistant\DTOs\CatalogRunResult;
use App\Modules\AiAssistant\DTOs\ConversationTurn;
use App\Modules\AiAssistant\DTOs\ToolCallRequest;
use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\AiAssistant\Services\Actions\AssistantActionService;
use App\Modules\AiAssistant\Services\Tools\AssistantToolExecutor;
use App\Modules\AiAssistant\Services\Tools\AssistantToolRegistry;
use App\Modules\DynamicEntities\Support\DynHtmlReportTemplateCatalog;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Catalog loop: the model picks a read or a write. Reads run now. A matching write is saved now.
 */
final class AssistantCatalogRunner
{
    public function __construct(
        private readonly AssistantOperationCatalog $catalog,
        private readonly AssistantOperationPlanner $planner,
        private readonly AssistantToolRegistry $tools,
        private readonly AssistantToolExecutor $executor,
        private readonly AssistantActionService $actions,
    ) {}

    /**
     * @param  list<ConversationTurn>  $history
     * @param  (callable(array{id: string, kind: string, title: string, status: string, detail: string|null}): void)|null  $onProgress
     * @param  (callable(array{kind: string, text: string, name?: string, status?: string}): void)|null  $onDelta
     */
    public function run(
        TenantUser $viewer,
        string $question,
        ?string $moduleContext,
        ?string $conversationId,
        array $history,
        ?string $preferredModel,
        ?callable $onProgress = null,
        ?callable $onDelta = null,
        string $roleNote = '',
    ): CatalogRunResult {
        $catalog = $this->catalog->forViewer($viewer);
        $installed = $this->installMatchedTemplate(
            viewer: $viewer,
            question: $question,
            moduleContext: $moduleContext,
            conversationId: $conversationId,
            preferredModel: $preferredModel,
            onProgress: $onProgress,
        );
        if ($installed !== null) {
            return $installed;
        }

        $toolResults = [];
        $seen = [];

        for ($step = 0; $step < 3; $step++) {
            $this->progress($onProgress, 'plan-'.$step, 'model', 'Choosing an operation', 'running');
            $decision = $this->planner->decide(
                question: $question,
                moduleContext: $moduleContext,
                catalog: $catalog,
                history: $history,
                toolResults: $toolResults,
                preferredModel: $preferredModel,
                roleNote: $roleNote,
                onWaiting: function (string $status) use ($onProgress, $step): void {
                    $this->progress($onProgress, 'plan-'.$step, 'model', 'Asking the model', 'running');
                },
                onDelta: $onDelta,
            );
            $this->progress($onProgress, 'plan-'.$step, 'model', 'Chose an operation', 'done', $decision->decision);

            if ($decision->decision === 'read' && is_string($decision->name)) {
                $signature = $decision->name.':'.json_encode($decision->arguments);
                if (isset($seen[$signature])) {
                    break;
                }
                $seen[$signature] = true;

                $note = trim((string) $decision->note);
                if ($note !== '') {
                    $this->progress($onProgress, 'thought-'.$step, 'thought', 'Thinking', 'done', $note);
                }

                $title = $this->stepTitle($decision->name, $decision->purpose);
                $this->progress($onProgress, 'tool-'.$step, 'tool', $title, 'running');
                $result = $this->read($viewer, $decision->name, $decision->arguments);
                $toolResults[] = $result;
                $this->progress(
                    $onProgress,
                    'tool-'.$step,
                    'tool',
                    $title,
                    $result->ok ? 'done' : 'failed',
                    $result->ok ? ($result->summary ?? 'Finished.') : ($result->error ?? 'The read did not finish.'),
                );

                if ($result->ok && ($step > 0 || ! $this->isBareCount($result))) {
                    break;
                }

                continue;
            }

            if ($decision->decision === 'write' && is_string($decision->name)) {
                if (! $this->writeMatchesQuestion($question, $decision->name)) {
                    break;
                }

                $args = $decision->arguments;
                if (is_string($conversationId) && $conversationId !== '') {
                    $args['conversation_id'] = $conversationId;
                }
                if (is_string($moduleContext) && $moduleContext !== '' && ! isset($args['module_context'])) {
                    $args['module_context'] = $moduleContext;
                }
                if (is_string($preferredModel) && trim($preferredModel) !== '') {
                    $args['preferred_model'] = trim($preferredModel);
                }

                try {
                    $proposal = $this->actions->propose(
                        viewer: $viewer,
                        actionName: $decision->name,
                        question: $question,
                        args: $args,
                        conversationId: $conversationId,
                    );
                } catch (ValidationException $e) {
                    throw $e;
                } catch (Throwable) {
                    return new CatalogRunResult(null, $toolResults);
                }

                $confirmed = $this->actions->confirm($viewer, (string) $proposal->id);
                $label = $confirmed['result']->entityLabel;

                return new CatalogRunResult(
                    null,
                    $toolResults,
                    is_string($label) && $label !== '' ? $label : 'Saved.',
                );
            }

            if ($toolResults === []) {
                $pending = $this->actions->continuePending(
                    viewer: $viewer,
                    question: $question,
                    conversationId: $conversationId,
                    messageId: null,
                    preferredModel: $preferredModel,
                );
                if ($pending !== null) {
                    return new CatalogRunResult($pending, $toolResults);
                }
            }

            break;
        }

        return new CatalogRunResult(null, $toolResults);
    }

    /**
     * A named HTML report template is installed as that template.
     * The model does not get to rewrite it as a Report Builder definition.
     *
     * @param  (callable(array{id: string, kind: string, title: string, status: string, detail: string|null}): void)|null  $onProgress
     */
    private function installMatchedTemplate(
        TenantUser $viewer,
        string $question,
        ?string $moduleContext,
        ?string $conversationId,
        ?string $preferredModel,
        ?callable $onProgress,
    ): ?CatalogRunResult {
        $template = DynHtmlReportTemplateCatalog::match($question);
        if ($template === null) {
            return null;
        }

        $this->progress($onProgress, 'template', 'action', 'Installing '.$template['name'], 'running');
        $args = ['template_key' => $template['key']];
        if (is_string($conversationId) && $conversationId !== '') {
            $args['conversation_id'] = $conversationId;
        }
        if (is_string($moduleContext) && $moduleContext !== '') {
            $args['module_context'] = $moduleContext;
        }
        if (is_string($preferredModel) && trim($preferredModel) !== '') {
            $args['preferred_model'] = trim($preferredModel);
        }

        $proposal = $this->actions->propose(
            viewer: $viewer,
            actionName: 'create_html_report_from_template',
            question: $question,
            args: $args,
            conversationId: $conversationId,
        );
        $confirmed = $this->actions->confirm($viewer, (string) $proposal->id);
        $label = $confirmed['result']->entityLabel;
        $this->progress($onProgress, 'template', 'action', 'Installed '.$template['name'], 'done', is_string($label) ? $label : null);

        return new CatalogRunResult(
            null,
            [],
            is_string($label) && $label !== '' ? $label : $template['name'],
        );
    }

    /**
     * A write is kept only when the question uses that operation's verb.
     * Otherwise the ask falls through to the model instead of a nearby card.
     */
    public function writeMatchesQuestion(string $question, string $action): bool
    {
        $q = mb_strtolower($question);
        $verbs = [
            'reject_eapproval_submission' => '/\breject\b/u',
            'cancel_eapproval_submission' => '/\b(cancel|canceled|cancelled|withdraw)\b/u',
            'seed_sample_it_assets' => '/\b(dummy|sample|seed|fake|demo)\b/u',
            'set_user_active' => '/\b(inactive|deactivate|deactivated|disable|disabled|reactivate|activate|active|enable|enabled)\b/u',
            'create_tenant_user' => '/\b(create|add|invite)\b/u',
            'update_it_asset' => '/\b(notes|manufacturer|hostname|product)\b/u',
            'change_ticket_status' => '/\b(status|progress|resolved|resolve|closed|close|reopen|open)\b/u',
            'update_controlled_document' => '/\b(title|department)\b/u',
        ];

        if (! isset($verbs[$action])) {
            return true;
        }

        if (preg_match($verbs[$action], $q) !== 1) {
            return false;
        }

        if ($action === 'cancel_eapproval_submission' && preg_match('/\breject\b/u', $q) === 1) {
            return false;
        }

        if ($action === 'seed_sample_it_assets' && preg_match('/\bassets?\b/u', $q) !== 1) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function read(TenantUser $viewer, string $name, array $arguments): ToolResult
    {
        if (! $this->tools->has($name)) {
            return new ToolResult(tool: $name, ok: false, error: 'That read is not in the catalog.');
        }

        $tool = $this->tools->get($name);
        $validator = Validator::make($arguments, $tool->argumentRules());
        if ($validator->fails()) {
            $message = (string) collect($validator->errors()->all())->first();

            return new ToolResult(
                tool: $name,
                ok: false,
                error: $message !== '' ? $message : 'The arguments for that read are not valid.',
            );
        }

        return $this->executor->executeOne(
            $viewer,
            new ToolCallRequest($name, $validator->validated()),
        );
    }

    private function isBareCount(ToolResult $result): bool
    {
        $rows = $result->data['rows'] ?? null;
        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0]) || $rows[0] === []) {
            return false;
        }

        foreach ($rows[0] as $value) {
            if (is_array($value) || ! is_numeric($value)) {
                return false;
            }
        }

        return true;
    }

    private function label(string $name): string
    {
        $label = trim(str_replace('_', ' ', $name));

        return $label !== '' ? ucwords($label) : $name;
    }

    private function stepTitle(string $name, ?string $purpose): string
    {
        $phrase = trim((string) $purpose);
        if ($name === 'execute_sql') {
            return $phrase !== '' ? 'Running database query: "'.$phrase.'"' : 'Running database query';
        }

        $label = $this->label($name);

        return $phrase !== '' ? 'Running '.$label.': "'.$phrase.'"' : 'Running '.$label;
    }

    /**
     * @param  (callable(array{id: string, kind: string, title: string, status: string, detail: string|null}): void)|null  $onProgress
     */
    private function progress(
        ?callable $onProgress,
        string $id,
        string $kind,
        string $title,
        string $status,
        ?string $detail = null,
    ): void {
        if ($onProgress === null) {
            return;
        }

        $onProgress([
            'id' => $id,
            'kind' => $kind,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ]);
    }
}
