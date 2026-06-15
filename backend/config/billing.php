<?php

declare(strict_types=1);

/**
 * Canonical plan tier entitlements (central tenants.plan_tier).
 * Module services read slices from TenantPlanEntitlementsService — do not duplicate in module configs.
 */
return [
    /*
    | Subscription lifecycle (no payment processor). Status on tenants.subscription_status:
    | trial | active | past_due | canceled
    */
    'subscription' => [
        'default_status' => env('TOWEROS_SUBSCRIPTION_DEFAULT_STATUS', 'active'),
        'trial_days' => (int) env('TOWEROS_SUBSCRIPTION_TRIAL_DAYS', 14),
        'past_due_grace_days' => (int) env('TOWEROS_SUBSCRIPTION_PAST_DUE_GRACE_DAYS', 7),
        /** When trial_ends_at passes: active | past_due */
        'on_trial_expire' => env('TOWEROS_SUBSCRIPTION_ON_TRIAL_EXPIRE', 'active'),
    ],

    /** Tenant API routes that remain available when subscription is suspended. */
    'subscription_api_exempt_prefixes' => [
        'api/v1/auth/',
        'api/v1/e-approval/health',
        'api/v1/public/e-approval/',
        'api/v1/admin/billing',
    ],

    /**
     * Stripe payments (Phase 4). Default off — set TOWEROS_STRIPE_ENABLED=true and API keys to activate.
     */
    'stripe' => [
        'enabled' => filter_var(env('TOWEROS_STRIPE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'secret_key' => env('STRIPE_SECRET'),
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'prices' => [
            'starter' => env('STRIPE_PRICE_STARTER'),
            'professional' => env('STRIPE_PRICE_PROFESSIONAL'),
            'enterprise' => env('STRIPE_PRICE_ENTERPRISE'),
        ],
        /** Tiers available in self-serve Checkout (enterprise is typically sales-assisted). */
        'self_serve_tiers' => ['starter', 'professional'],
        'checkout_success_path' => env('TOWEROS_STRIPE_CHECKOUT_SUCCESS_PATH', '/billing?checkout=success'),
        'checkout_cancel_path' => env('TOWEROS_STRIPE_CHECKOUT_CANCEL_PATH', '/billing?checkout=canceled'),
        'portal_return_path' => env('TOWEROS_STRIPE_PORTAL_RETURN_PATH', '/billing'),
    ],

    /**
     * Indicative list prices for platform revenue dashboard (USD/month per tenant).
     * Not used for invoicing — Stripe or manual contracts are source of truth.
     */
    /**
     * FX rates: 1 USD = X units of each currency. Used to convert canonical USD list prices
     * into the platform billing currency. Override per env in production as needed.
     */
    'exchange_rates' => [
        'USD' => 1,
        'PHP' => (float) env('TOWEROS_FX_USD_PHP', 56),
        'EUR' => (float) env('TOWEROS_FX_USD_EUR', 0.92),
        'GBP' => (float) env('TOWEROS_FX_USD_GBP', 0.79),
        'SGD' => (float) env('TOWEROS_FX_USD_SGD', 1.35),
        'AUD' => (float) env('TOWEROS_FX_USD_AUD', 1.55),
        'MYR' => (float) env('TOWEROS_FX_USD_MYR', 4.7),
        'IDR' => (float) env('TOWEROS_FX_USD_IDR', 16000),
        'THB' => (float) env('TOWEROS_FX_USD_THB', 36),
        'VND' => (float) env('TOWEROS_FX_USD_VND', 25000),
        'JPY' => (float) env('TOWEROS_FX_USD_JPY', 150),
        'INR' => (float) env('TOWEROS_FX_USD_INR', 83),
    ],

    /**
     * Platform billing currencies (ISO 4217). Superadmin picks one in Plan catalog.
     * Canonical list prices are stored in USD and converted using exchange_rates.
     */
    'currencies' => [
        'USD' => 'US Dollar',
        'PHP' => 'Philippine Peso',
        'EUR' => 'Euro',
        'GBP' => 'British Pound',
        'SGD' => 'Singapore Dollar',
        'AUD' => 'Australian Dollar',
        'MYR' => 'Malaysian Ringgit',
        'IDR' => 'Indonesian Rupiah',
        'THB' => 'Thai Baht',
        'VND' => 'Vietnamese Dong',
        'JPY' => 'Japanese Yen',
        'INR' => 'Indian Rupee',
    ],

    'revenue' => [
        /** Fallback before platform_billing_settings row exists (overridden by superadmin catalog). */
        'currency' => env('TOWEROS_BILLING_CURRENCY', 'USD'),
        'estimate_note' => 'Indicative MRR from configured list prices for active/trial tenants — not Stripe-invoiced amounts.',
        'estimated_monthly_usd' => [
            'starter' => (float) env('TOWEROS_LIST_PRICE_STARTER_USD', 0),
            'professional' => (float) env('TOWEROS_LIST_PRICE_PROFESSIONAL_USD', 99),
            'enterprise' => (float) env('TOWEROS_LIST_PRICE_ENTERPRISE_USD', 299),
        ],
    ],

    /** Default annual prepay discount when platform catalog has no override. */
    'annual' => [
        'default_discount_percent' => (float) env('TOWEROS_BILLING_ANNUAL_DISCOUNT_PERCENT', 20),
    ],

    'plan_tiers' => [
        'starter' => [
            'label' => 'Starter',
            'sort' => 10,
            'included' => [
                'paid_seats' => 5,
                'rfi_units' => 10,
                'storage_gb' => 25,
            ],
            'pricing' => [
                'monthly_base_usd' => (float) env('TOWEROS_LIST_PRICE_STARTER_USD', 0),
                'rfi_overage_usd' => (float) env('TOWEROS_RFI_OVERAGE_STARTER_USD', 0),
                'paid_seat_overage_usd' => (float) env('TOWEROS_SEAT_OVERAGE_STARTER_USD', 0),
            ],
            'modules' => [
                'e_approval' => [
                    'file_uploads' => false,
                    'max_file_fields' => 0,
                ],
                'project_one' => [
                    'rollout_file_uploads' => false,
                ],
                'ticketing' => [
                    'enabled' => false,
                    'file_uploads' => false,
                    'max_attachments_per_ticket' => 0,
                ],
            ],
        ],
        'professional' => [
            'label' => 'Professional',
            'sort' => 20,
            'included' => [
                'paid_seats' => 15,
                'rfi_units' => 50,
                'storage_gb' => 100,
            ],
            'pricing' => [
                'monthly_base_usd' => (float) env('TOWEROS_LIST_PRICE_PROFESSIONAL_USD', 99),
                'rfi_overage_usd' => (float) env('TOWEROS_RFI_OVERAGE_PROFESSIONAL_USD', 25),
                'paid_seat_overage_usd' => (float) env('TOWEROS_SEAT_OVERAGE_PROFESSIONAL_USD', 15),
            ],
            'modules' => [
                'e_approval' => [
                    'file_uploads' => true,
                    'max_file_fields' => 10,
                ],
                'project_one' => [
                    'rollout_file_uploads' => true,
                ],
                'ticketing' => [
                    'enabled' => false,
                    'file_uploads' => false,
                    'max_attachments_per_ticket' => 0,
                ],
            ],
        ],
        'enterprise' => [
            'label' => 'Enterprise',
            'sort' => 30,
            'included' => [
                'paid_seats' => 50,
                'rfi_units' => 250,
                'storage_gb' => 500,
            ],
            'pricing' => [
                'monthly_base_usd' => (float) env('TOWEROS_LIST_PRICE_ENTERPRISE_USD', 299),
                'rfi_overage_usd' => (float) env('TOWEROS_RFI_OVERAGE_ENTERPRISE_USD', 20),
                'paid_seat_overage_usd' => (float) env('TOWEROS_SEAT_OVERAGE_ENTERPRISE_USD', 12),
            ],
            'modules' => [
                'e_approval' => [
                    'file_uploads' => true,
                    'max_file_fields' => null,
                ],
                'project_one' => [
                    'rollout_file_uploads' => true,
                ],
                'ticketing' => [
                    'enabled' => true,
                    'file_uploads' => true,
                    'max_attachments_per_ticket' => 10,
                ],
            ],
        ],
    ],
];
