<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Receipt Printer Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for thermal receipt printer (Zy-Q822)
    | Supports both Network (LAN) and USB connections
    |
    */

    'enabled' => env('PRINTER_ENABLED', true),

    // Connection type: 'windows', 'network', or 'usb'
    'connection_type' => env('PRINTER_CONNECTION_TYPE', 'windows'),

    // Windows printer settings (recommended for USB printers)
    'windows' => [
        'name' => env('PRINTER_WINDOWS_NAME', 'POS-80C'),
        'share_name' => env('PRINTER_SHARE_NAME', 'POS80C'), // Printer share name (must share the printer first)
    ],

    // Network printer settings (LAN - direct connection)
    'network' => [
        'ip' => env('PRINTER_IP', '192.168.1.100'),
        'port' => env('PRINTER_PORT', 9100),
    ],

    // USB printer settings (legacy)
    'usb' => [
        // Windows share name or device path
        'device' => env('PRINTER_USB_DEVICE', 'USB001'),
    ],

    // Printer model info (for reference)
    'model' => [
        'name' => 'Zy-Q822',
        'serial' => env('PRINTER_SERIAL', 'ZY2537F00111'),
        'width' => 80, // 80mm paper width
        'characters_per_line' => 48, // Characters per line for 80mm
    ],

    // Receipt settings
    'receipt' => [
        'company_name' => env('RECEIPT_COMPANY_NAME', 'Smart Parking System'),
        'company_tagline' => env('RECEIPT_TAGLINE', 'Safe & Secure Parking'),
        'footer_message' => env('RECEIPT_FOOTER', 'Thank you for parking with us!'),
        'currency' => env('RECEIPT_CURRENCY', 'Tsh'),
    ],

    // Auto-print settings
    'auto_print' => [
        'on_entry' => env('PRINTER_AUTO_PRINT_ENTRY', true),
        'on_exit' => env('PRINTER_AUTO_PRINT_EXIT', true),
    ],
];



