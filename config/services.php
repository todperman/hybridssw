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


    'omise' => [
        'public_key' => env('OMISE_PUBLIC_KEY'),
        'secret_key' => env('OMISE_SECRET_KEY'),
        // Webhook Settings ใน Omise Dashboard (แยกของโหมดทดสอบกับโหมดจริง) ใช้ตรวจลายเซ็น webhook
        'webhook_secret' => env('OMISE_WEBHOOK_SECRET'),
        // ช่องทางที่เปิดให้ผู้ชำระเลือก ต้องเปิดใช้ในบัญชี Omise ด้วย (PromptPay ต้องขอเปิดกับ Omise)
        'methods' => array_values(array_filter(array_map('trim', explode(',', (string) env('OMISE_METHODS', 'promptpay,card'))))),
        'api_url' => env('OMISE_API_URL', 'https://api.omise.co'),
        // ล็อกเวอร์ชัน API ไว้ Omise เปลี่ยนรูปแบบข้อมูลตามเวอร์ชันของบัญชี ล็อกไว้จะไม่พังเมื่อบัญชีถูกอัปเกรด
        'api_version' => env('OMISE_API_VERSION', '2019-05-29'),
    ],

];
