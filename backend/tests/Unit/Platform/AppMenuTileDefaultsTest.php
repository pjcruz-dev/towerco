<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use App\Modules\Platform\Support\AppMenuTileDefaults;
use Tests\TestCase;

final class AppMenuTileDefaultsTest extends TestCase
{
    public function test_module_href_strips_login_and_opens_the_dashboard(): void
    {
        $this->assertSame(
            'http://alliance.localhost/e-approval',
            AppMenuTileDefaults::moduleHref('http://alliance.localhost/login', '/e-approval'),
        );
        $this->assertSame(
            'https://app.example.com/doc-extract',
            AppMenuTileDefaults::moduleHref('https://app.example.com/login?foo=1', '/doc-extract'),
        );
    }

    public function test_default_tiles_include_one_dashboard_per_module(): void
    {
        $byKey = [];
        foreach (AppMenuTileDefaults::tiles() as $tile) {
            $byKey[$tile['key']] = $tile;
        }

        $this->assertSame('Opens the E-Forms dashboard', $byKey['module-e-forms']['subtitle']);
        $this->assertStringEndsWith('/e-approval', $byKey['module-e-forms']['href']);
        $this->assertStringEndsWith('/ticketing', $byKey['module-ticketing']['href']);
        $this->assertStringEndsWith('/documents/controlled', $byKey['module-document-control']['href']);
        $this->assertStringEndsWith('/doc-extract', $byKey['module-doc-extract']['href']);
    }
}
