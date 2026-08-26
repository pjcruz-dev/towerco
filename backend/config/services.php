<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'azure' => [
        'client_id' => env('AZURE_CLIENT_ID'),
        'client_secret' => env('AZURE_CLIENT_SECRET'),
        'redirect' => env('AZURE_REDIRECT_URI'),
        'tenant' => env('AZURE_TENANT_ID', 'common'),
    ],

    /*
     * Central Microsoft Graph Mail.Send (daemon). Defaults to AZURE_* so one
     * Entra app can cover SSO + mail; override MAIL_GRAPH_* for a dedicated mail app.
     */
    'microsoft_graph_mail' => [
        'client_id' => env('MAIL_GRAPH_CLIENT_ID', env('AZURE_CLIENT_ID')),
        'client_secret' => env('MAIL_GRAPH_CLIENT_SECRET', env('AZURE_CLIENT_SECRET')),
        'tenant' => env('MAIL_GRAPH_TENANT_ID', env('AZURE_TENANT_ID', 'common')),
        'save_to_sent_items' => filter_var(env('MAIL_GRAPH_SAVE_TO_SENT_ITEMS', false), FILTER_VALIDATE_BOOL),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
