<?php

return [
    'company' => env('LEGAL_COMPANY_NAME', 'ML Software UG (haftungsbeschränkt)'),
    'representative' => env('LEGAL_REPRESENTATIVE', 'Silvio Täubert'),
    'street' => env('LEGAL_STREET', 'Dr.-Rudolf-Friedrichs-Straße 2b'),
    'postal_code' => env('LEGAL_POSTAL_CODE', '01445'),
    'city' => env('LEGAL_CITY', 'Radebeul'),
    'country' => env('LEGAL_COUNTRY', 'Deutschland'),
    'email' => env('LEGAL_EMAIL', env('MAIL_FROM_ADDRESS', 'kontakt@aktienki.com')),
    'phone' => env('LEGAL_PHONE'),
    'register_court' => env('LEGAL_REGISTER_COURT'),
    'register_number' => env('LEGAL_REGISTER_NUMBER'),
    'vat_id' => env('LEGAL_VAT_ID'),
    'formation_status' => env('LEGAL_FORMATION_STATUS'),
    'privacy_email' => env('LEGAL_PRIVACY_EMAIL', env('LEGAL_EMAIL', env('MAIL_FROM_ADDRESS', 'datenschutz@aktienki.com'))),
    'legal_version' => env('LEGAL_DOCUMENT_VERSION', '1.0-beta'),
    'effective_date' => env('LEGAL_EFFECTIVE_DATE', '2026-08-16'),
];
