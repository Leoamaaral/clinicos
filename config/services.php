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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        /*
        | Z-API: https://developer.z-api.io/api-reference/introduction
        | URL completa do endpoint send-text, ex.:
        | https://api.z-api.io/instances/{instanceId}/token/{token}/send-text
        */
        'api_url' => env('WHATSAPP_API_URL'),
        /*
        | Token de segurança da conta (header Client-Token).
        | Obtenha em: https://developer.z-api.io/security/client-token
        */
        'client_token' => env('WHATSAPP_CLIENT_TOKEN'),
        /*
        | URL do webhook de mensagens recebidas (configure no painel Z-API):
        | {APP_URL}/webhooks/whatsapp
        */
    ],

];
