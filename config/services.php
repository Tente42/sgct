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
    'grandstream' => [
        'host' => env('GRANDSTREAM_IP'), // IP o dominio del PBX Grandstream
        'port' => env('GRANDSTREAM_PORT'), // Puerto API 
        'user' => env('GRANDSTREAM_USER'), // Usuario con permisos de API
        'pass' => env('GRANDSTREAM_PASS'), // Contraseña del usuario API
        'verify_ssl' => env('GRANDSTREAM_VERIFY_SSL', false), // Verificar SSL (true/false)
    ],
    'admins' => [
        'name'  => env('ADMIN_USER'),
        'email' => env('ADMIN_MAIL'),
        'pass'  => env('ADMIN_PASS'),
    ],

    'users' => [
        'name'  => env('USUARIO_USER'),
        'email' => env('USUARIO_MAIL'),
        'pass'  => env('USUARIO_PASS'),
    ],


];
