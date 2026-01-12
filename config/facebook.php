<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Facebook App Credentials
    |--------------------------------------------------------------------------
    */
    'app_id' => env('FACEBOOK_APP_ID'),
    'app_secret' => env('FACEBOOK_APP_SECRET'),
    'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v24.0'),

    /*
    |--------------------------------------------------------------------------
    | Facebook Graph API Base URL
    |--------------------------------------------------------------------------
    */
    'base_url' => 'https://graph.facebook.com',

    /*
    |--------------------------------------------------------------------------
    | Default Permissions for Page Management
    |--------------------------------------------------------------------------
    */
    'default_permissions' => [
        'pages_show_list',
        'pages_read_engagement',
        'pages_manage_metadata',
        'pages_manage_posts',
        'pages_manage_engagement',  // Required for responding to reviews
        'business_management',
    ],

];
