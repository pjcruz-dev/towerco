<?php

declare(strict_types=1);

namespace Tests\Unit\DynamicEntities;

use App\Modules\DynamicEntities\Services\DynReportBuilderAiService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

final class DynReportBuilderEntityMatchTest extends TestCase
{
    #[Test]
    public function pick_entity_prefers_users_system_for_team_access_prompt(): void
    {
        $service = app(DynReportBuilderAiService::class);
        $method = new ReflectionMethod(DynReportBuilderAiService::class, 'pickEntity');
        $method->setAccessible(true);

        $catalog = [
            [
                'slug' => 'chart_of_accounts',
                'name' => 'Chart of Accounts',
                'module_pack' => 'finance',
                'fields' => [],
            ],
            [
                'slug' => 'users_system',
                'name' => 'Users',
                'module_pack' => 'system',
                'fields' => [],
            ],
        ];

        $picked = $method->invoke(
            $service,
            'team & access all users, can you create dashboard for it?',
            '',
            $catalog,
            ['users', 'users_system', 'tenant_users'],
            'team_access',
        );

        $this->assertSame('users_system', $picked['slug']);
    }
}
