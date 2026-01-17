<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Places API
    |--------------------------------------------------------------------------
    |
    | Configuration for Google Places API used to fetch business reviews
    | and place details.
    |
    */

    'places' => [
        'api_key' => env('GOOGLE_PLACES_API_KEY'),
        'base_url' => 'https://maps.googleapis.com/maps/api/place',
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Play Store API
    |--------------------------------------------------------------------------
    |
    | Configuration for Google Play Developer API used to fetch app reviews
    | and reply to them.
    |
    */

    'play_store' => [
        'package_name' => env('GOOGLE_PLAY_PACKAGE_NAME'),
        'service_account_json' => env('GOOGLE_PLAY_SERVICE_ACCOUNT_JSON'),
    ],

];
