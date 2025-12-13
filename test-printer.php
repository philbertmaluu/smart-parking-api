<?php

/**
 * Simple printer test script
 * Run: php test-printer.php
 */

require __DIR__ . '/vendor/autoload.php';

use App\Services\ReceiptPrinterService;

echo "========================================\n";
echo "  Printer Connection Test\n";
echo "========================================\n\n";

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "Configuration:\n";
echo "  Connection Type: " . ($_ENV['PRINTER_CONNECTION_TYPE'] ?? 'not set') . "\n";
echo "  Printer IP: " . ($_ENV['PRINTER_IP'] ?? 'not set') . "\n";
echo "  Printer Port: " . ($_ENV['PRINTER_PORT'] ?? 'not set') . "\n";
echo "  Printer Enabled: " . ($_ENV['PRINTER_ENABLED'] ?? 'not set') . "\n\n";

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
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "\n========================================\n";

