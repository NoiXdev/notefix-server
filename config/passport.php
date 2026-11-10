<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Passport Guard
    |--------------------------------------------------------------------------
    |
    | Here you may specify which authentication guard Passport will use when
    | authenticating users. This value should correspond with one of your
    | guards that is already present in your "auth" configuration file.
    |
    */

    'guard' => 'web',

    'middleware' => [],

    /*
    |--------------------------------------------------------------------------
    | Encryption Keys
    |--------------------------------------------------------------------------
    |
    | Passport uses encryption keys while generating secure access tokens for
    | your application. By default, the keys are stored as local files but
    | can be set via environment variables when that is more convenient.
    |
    */

    // The *_BASE64 variants carry a whole PEM key in one line, for container
    // platforms (Portainer) whose environment variables can't hold newlines.
    'private_key' => env('PASSPORT_PRIVATE_KEY')
        ?? (($key = env('PASSPORT_PRIVATE_KEY_BASE64')) ? base64_decode($key) : null),

    'public_key' => env('PASSPORT_PUBLIC_KEY')
        ?? (($key = env('PASSPORT_PUBLIC_KEY_BASE64')) ? base64_decode($key) : null),

    /*
    |--------------------------------------------------------------------------
    | Passport Database Connection
    |--------------------------------------------------------------------------
    |
    | By default, Passport's models will utilize your application's default
    | database connection. If you wish to use a different connection you
    | may specify the configured name of the database connection here.
    |
    */

    'connection' => env('PASSPORT_CONNECTION'),

];
