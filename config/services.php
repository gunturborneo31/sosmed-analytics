<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'redirect' => env('META_REDIRECT_URI'),
        'graph_version' => env('META_GRAPH_VERSION', 'v21.0'),
        'config_id' => env('META_CONFIG_ID'),
        'graph_url' => 'https://graph.facebook.com/',

        /*
        | Jalur kedua: Instagram Business Login. Operator masuk dengan akun
        | Instagram-nya sendiri, tanpa perlu Facebook Page. Kredensial ini
        | BERBEDA dari App ID/Secret Facebook di atas — ambil dari menu
        | produk Instagram di dashboard Meta.
        */
        'instagram_app_id' => env('INSTAGRAM_APP_ID'),
        'instagram_app_secret' => env('INSTAGRAM_APP_SECRET'),
        'instagram_redirect' => env('INSTAGRAM_REDIRECT_URI'),
        'instagram_graph_url' => 'https://graph.instagram.com',
        'instagram_auth_url' => 'https://www.instagram.com/oauth/authorize',
        'instagram_token_url' => 'https://api.instagram.com/oauth/access_token',
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
