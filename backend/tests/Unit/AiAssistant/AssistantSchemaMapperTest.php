<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\Contracts\LlmProviderInterface;
use App\Modules\AiAssistant\DTOs\LlmCompletionResult;
use App\Modules\AiAssistant\DTOs\LlmPrompt;
use App\Modules\AiAssistant\Services\AssistantSchemaMapper;
use App\Modules\AiAssistant\Services\Tools\AssistantTenantSql;
use App\Modules\AiAssistant\Support\AssistantTokenBudget;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AssistantSchemaMapperTest extends TestCase
{
    public function test_mapper_does_not_call_the_model_without_an_api_provider(): void
    {
        config(['ai_assistant.llm_provider' => '']);
        $this->app->instance(LlmProviderInterface::class, new readonly class implements LlmProviderInterface
        {
            public function complete(LlmPrompt $prompt): LlmCompletionResult
            {
                throw new \RuntimeException('The model should not be called.');
            }

            public function modelName(): string
            {
                return 'unused';
            }
        });

        $user = new TenantUser;
        $user->id = 'user-1';
        $this->assertFalse(app(AssistantTokenBudget::class)->spend($user));

        $suggested = ['Serial No' => '__ignore__', 'Tag' => 'asset_tag'];
        $mapped = app(AssistantSchemaMapper::class)->fillColumnMap(
            $user,
            ['Serial No', 'Tag'],
            [['name' => 'serial_number', 'label' => 'Serial'], ['name' => 'asset_tag', 'label' => 'Tag']],
            $suggested,
            [],
        );

        $this->assertSame($suggested, $mapped);
    }

    public function test_sql_wipe_is_rejected(): void
    {
        $sql = app(AssistantTenantSql::class);

        try {
            $sql->guard('DELETE FROM it_assets', 'tenant_a', ['mysql', 'toweros']);
            $this->fail('A delete with no WHERE was accepted.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('WHERE', (string) collect($e->errors())->flatten()->first());
        }

        try {
            $sql->guard('DROP TABLE it_assets', 'tenant_a', ['mysql']);
            $this->fail('DROP TABLE was accepted.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        try {
            $sql->guard('ALTER TABLE it_assets DROP COLUMN asset_tag', 'tenant_a', ['mysql']);
            $this->fail('DROP COLUMN was accepted.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Schema changes', (string) collect($e->errors())->flatten()->first());
        }

        try {
            $sql->guard('DELETE FROM it_assets WHERE id IS NOT NULL', 'tenant_a', ['mysql']);
            $this->fail('A delete of every row was accepted.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Name the rows', (string) collect($e->errors())->flatten()->first());
        }

        $kept = $sql->guard(
            "DELETE FROM it_assets WHERE asset_tag = 'ATC-LAP-000001'",
            'tenant_a',
            ['mysql'],
        );
        $this->assertStringContainsString('WHERE', $kept);
    }
}
