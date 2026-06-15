<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class PlatformBillingSetting extends Model
{
    public const SINGLETON_ID = 1;

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'currency',
        'default_annual_discount_percent',
        'tier_overrides',
    ];

    protected function casts(): array
    {
        return [
            'default_annual_discount_percent' => 'float',
            'tier_overrides' => 'array',
        ];
    }

    public static function singleton(): self
    {
        if (! Schema::hasTable('platform_billing_settings')) {
            return new self([
                'id' => self::SINGLETON_ID,
                'currency' => (string) config('billing.revenue.currency', 'USD'),
                'default_annual_discount_percent' => (float) config('billing.annual.default_discount_percent', 20),
                'tier_overrides' => null,
            ]);
        }

        /** @var self|null $row */
        $row = self::query()->find(self::SINGLETON_ID);

        if ($row instanceof self) {
            return $row;
        }

        return self::query()->firstOrCreate(
            ['id' => self::SINGLETON_ID],
            [
                'currency' => (string) config('billing.revenue.currency', 'USD'),
                'default_annual_discount_percent' => (float) config('billing.annual.default_discount_percent', 20),
                'tier_overrides' => null,
            ],
        );
    }
}
