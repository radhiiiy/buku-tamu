<?php

return [
    // log    : pesan hanya ditulis ke storage/logs/laravel.log (default, aman untuk pengembangan)
    // fonnte : kirim sungguhan lewat https://fonnte.com (butuh token perangkat)
    'driver' => env('WHATSAPP_DRIVER', 'log'),

    'fonnte' => [
        'token' => env('FONNTE_TOKEN'),
        'endpoint' => 'https://api.fonnte.com/send',
    ],

    'country_code' => '62',
];
