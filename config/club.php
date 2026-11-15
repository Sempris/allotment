<?php

/*
 | Details of the allotment club, printed on the yearly bill.
 | They change every few years, so they live in the environment instead of a settings page.
 */
return [
    'name' => env('CLUB_NAME', 'Kleingartenverein Sonnenhang e. V.'),
    'address' => env('CLUB_ADDRESS', 'Am Sonnenhang 1, 36037 Fulda'),
    'email' => env('CLUB_EMAIL', 'vorstand@example.org'),

    'bank' => [
        'holder' => env('CLUB_BANK_HOLDER', 'Kleingartenverein Sonnenhang e. V.'),
        'iban' => env('CLUB_IBAN', 'DE00 0000 0000 0000 0000 00'),
        'name' => env('CLUB_BANK_NAME', 'Musterbank'),
    ],

    // Days after the bill's date until the amount is due.
    'payment_days' => (int) env('CLUB_PAYMENT_DAYS', 14),
];
