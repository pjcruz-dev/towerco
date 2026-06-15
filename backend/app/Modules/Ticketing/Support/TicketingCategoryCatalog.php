<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Support;

use App\Modules\Ticketing\Services\TicketingSettingsService;

final class TicketingCategoryCatalog
{
    /**
     * @return list<string>
     */
    public static function defaults(): array
    {
        return [
            'general',
            'bug',
            'feature_request',
            'access',
            'billing',
            'integration',
            'operations',
        ];
    }

    /**
     * @return list<string>
     */
    public function resolve(): array
    {
        $settings = app(TicketingSettingsService::class);
        $raw = $settings->getString(TicketingSettingsService::CATEGORIES);
        if ($raw === null || trim($raw) === '') {
            return self::defaults();
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return self::defaults();
        }

        $categories = [];
        foreach ($decoded as $item) {
            if (is_string($item)) {
                $slug = strtolower(trim($item));
            } elseif (is_array($item) && isset($item['id'])) {
                $slug = strtolower(trim((string) $item['id']));
            } else {
                continue;
            }
            if ($slug !== '' && preg_match('/^[a-z0-9_]+$/', $slug)) {
                $categories[] = $slug;
            }
        }

        return $categories !== [] ? array_values(array_unique($categories)) : self::defaults();
    }

    public function isValid(?string $category): bool
    {
        if ($category === null || $category === '') {
            return true;
        }

        return in_array(strtolower($category), $this->resolve(), true);
    }
}
