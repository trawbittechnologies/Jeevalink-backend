<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // NOTE: '*' cannot be used here when supports_credentials is true.
    // List your Vercel frontend URL and any preview/localhost URLs.
    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:3000',
        // Add your Vercel production URL below (e.g. https://jeevalink.vercel.app)
        env('FRONTEND_URL', 'https://jeevalink.vercel.app'),
    ],

    'allowed_origins_patterns' => [
        // Allow all Vercel preview deployments
        '#^https://jeevalink.*\.vercel\.app$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Authorization'],

    'max_age' => 86400,

    'supports_credentials' => true,

];
