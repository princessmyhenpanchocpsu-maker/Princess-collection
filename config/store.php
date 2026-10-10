<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Initial Administrator
    |--------------------------------------------------------------------------
    |
    | Used only by AdminUserSeeder. Admin accounts can never be created through
    | public registration, so the first one is defined here from the environment.
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME', 'Store Admin'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Store Settings
    |--------------------------------------------------------------------------
    |
    | Starting values for settings an admin can change in the dashboard.
    | Saved values live in the `settings` table and override these.
    | Money values are decimal strings in Philippine pesos.
    |
    */

    'settings' => [
        'shipping_fee' => '120.00',
        'free_shipping_threshold' => '2000.00',
        'announcement_text' => 'Free shipping on orders ₱2,000 and up · Cash on Delivery nationwide',
    ],

    /*
    |--------------------------------------------------------------------------
    | Product Image Uploads
    |--------------------------------------------------------------------------
    */

    'images' => [
        'max_kilobytes' => 4096,
        'max_per_product' => 8,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
    ],

    'sizes' => ['XS', 'S', 'M', 'L', 'XL'],

];
