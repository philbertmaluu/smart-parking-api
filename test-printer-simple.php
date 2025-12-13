<?php

/**
 * Simple printer test - uses Laravel bootstrap
 * Run: php test-printer-simple.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\ReceiptPrinterService;

echo "========================================\n";
echo "  Printer Connection Test\n";
echo "========================================\n\n";

$config = config('printer');
echo "Configuration:\n";
echo "  Connection Type: " . ($config['connection_type'] ?? 'not set') . "\n";
echo "  Printer IP: " . ($config['network']['ip'] ?? 'not set') . "\n";
echo "  Printer Port: " . ($config['network']['port'] ?? 'not set') . "\n";
echo "  Printer Enabled: " . ($config['enabled'] ? 'Yes' : 'No') . "\n\n";

echo "Testing printer connection...\n";
echo "----------------------------------------\n";

try {
    $printerService = new ReceiptPrinterService();
    
    // Test connection
    $result = $printerService->testConnection();
    
    if ($result['success']) {
        echo "✅ SUCCESS!\n";
        echo "Message: " . $result['message'] . "\n";
        echo "\nA test page should have been printed.\n";
    } else {
        echo "❌ FAILED!\n";
        echo "Message: " . $result['message'] . "\n";
        if (isset($result['config'])) {
            echo "\nConfig used:\n";
            print_r($result['config']);
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERROR!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n========================================\n";

