<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Support;

final class TicketingSourceCatalog
{
    public const MODULE_MANUAL = 'manual';

    public const MODULE_E_APPROVAL = 'e_approval';

    public const MODULE_AI_ASSISTANT = 'ai_assistant';

    /**
     * @return list<string>
     */
    public function modules(): array
    {
        return [
            self::MODULE_MANUAL,
            self::MODULE_E_APPROVAL,
            self::MODULE_AI_ASSISTANT,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function labels(): array
    {
        return [
            self::MODULE_MANUAL => 'Manual',
            self::MODULE_E_APPROVAL => 'E-Forms',
            self::MODULE_AI_ASSISTANT => 'AI Assistant',
        ];
    }

    public function isKnownModule(string $module): bool
    {
        return in_array($module, $this->modules(), true);
    }
}
