<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use App\Modules\Platform\Support\TenantChromePalette;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class TenantChromePaletteTest extends TestCase
{
    public function test_saturated_presets_use_light_text_and_amber_uses_dark_text(): void
    {
        $this->assertSame('#fafafa', TenantChromePalette::foreground('#0d9488'));
        $this->assertSame('#fafafa', TenantChromePalette::foreground('#7367f0'));
        $this->assertSame('#fafafa', TenantChromePalette::foreground('#e11d48'));
        $this->assertSame('#fafafa', TenantChromePalette::foreground('#2563eb'));
        $this->assertSame('#111827', TenantChromePalette::foreground('#f59e0b'));
    }

    public function test_paint_replaces_primary_keys_and_clear_removes_them(): void
    {
        $painted = TenantChromePalette::paint(
            ['background' => '#ffffff', 'primary' => '#171717'],
            [],
            '#0d9488',
        );

        $this->assertSame('#ffffff', $painted['light']['background']);
        $this->assertSame('#0d9488', $painted['light']['primary']);
        $this->assertSame('#fafafa', $painted['light']['primary-foreground']);
        $this->assertSame('#0d9488', $painted['dark']['sidebar-primary']);

        $cleared = TenantChromePalette::paint($painted['light'], $painted['dark'], null);
        $this->assertArrayNotHasKey('primary', $cleared['light']);
        $this->assertSame('#ffffff', $cleared['light']['background']);
    }

    public function test_custom_color_is_required_when_preset_is_custom(): void
    {
        $this->expectException(ValidationException::class);

        TenantChromePalette::resolvePrimary('custom', null);
    }
}
