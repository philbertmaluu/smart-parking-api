<?php

namespace App\Services;

use App\Models\Receipt;
use App\Models\VehiclePassage;
use Illuminate\Support\Facades\Log;
use Exception;

// ESC/POS library imports
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;

/**
 * Receipt Printer Service
 * Handles thermal receipt printing using ESC/POS commands
 * Supports Zy-Q822 and similar 80mm thermal printers
 */
class ReceiptPrinterService
{
    private ?Printer $printer = null;
    private array $config;
    private bool $isConnected = false;

    public function __construct()
    {
        $this->config = config('printer');
    }

    /**
     * Check if printer is enabled
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    /**
     * Connect to the printer
     */
    public function connect(): bool
    {
        if (!$this->isEnabled()) {
            Log::warning('Printer is disabled in configuration');
            return false;
        }

        try {
            $connectionType = $this->config['connection_type'] ?? 'windows';

            if ($connectionType === 'network') {
                $connector = new NetworkPrintConnector(
                    $this->config['network']['ip'],
                    $this->config['network']['port']
                );
            } elseif ($connectionType === 'windows') {
                // Windows shared printer
                // Share name should be set (e.g., "POS80C")
                $shareName = $this->config['windows']['share_name'] ?? 'POS80C';
                $connector = new WindowsPrintConnector($shareName);
            } else {
                // USB printer - use share name or device path
                $device = $this->config['usb']['device'] ?? 'POS-80C';
                $connector = new WindowsPrintConnector($device);
            }

            $this->printer = new Printer($connector);
            $this->isConnected = true;

            Log::info('Printer connected successfully', [
                'type' => $connectionType,
                'model' => $this->config['model']['name'] ?? 'Unknown',
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to connect to printer', [
                'error' => $e->getMessage(),
                'config' => [
                    'type' => $this->config['connection_type'] ?? 'unknown',
                    'printer_name' => $this->config['windows']['name'] ?? 'N/A',
                ],
            ]);
            $this->isConnected = false;
            return false;
        }
    }

    /**
     * Disconnect from printer
     */
    public function disconnect(): void
    {
        if ($this->printer !== null) {
            try {
                $this->printer->close();
            } catch (Exception $e) {
                Log::warning('Error closing printer connection', ['error' => $e->getMessage()]);
            }
            $this->printer = null;
            $this->isConnected = false;
        }
    }

    /**
     * Print entry receipt
     */
    public function printEntryReceipt(VehiclePassage $passage, ?Receipt $receipt = null): array
    {
        if (!$this->connect()) {
            return [
                'success' => false,
                'message' => 'Could not connect to printer. Please check printer connection.',
            ];
        }

        try {
            $receiptConfig = $this->config['receipt'] ?? [];
            $currency = $receiptConfig['currency'] ?? 'Tsh';

            // Load relationships if not loaded
            $passage->loadMissing(['vehicle.bodyType', 'entryGate', 'entryStation', 'entryOperator']);

            $vehicle = $passage->vehicle;
            $gate = $passage->entryGate;
            $station = $passage->entryStation;

            // === HEADER ===
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->setEmphasis(true);
            $this->printer->setTextSize(2, 2);
            $this->printer->text($receiptConfig['company_name'] ?? 'Smart Parking');
            $this->printer->feed(1);
            
            $this->printer->setTextSize(1, 1);
            $this->printer->setEmphasis(false);
            $this->printer->text($receiptConfig['company_tagline'] ?? 'Safe & Secure Parking');
            $this->printer->feed(1);
            
            $this->printer->text("================================");
            $this->printer->feed(1);
            
            $this->printer->setEmphasis(true);
            $this->printer->text("** ENTRY RECEIPT **");
            $this->printer->setEmphasis(false);
            $this->printer->feed(1);
            
            $this->printer->text("================================");
            $this->printer->feed(2);

            // === RECEIPT INFO ===
            $this->printer->setJustification(Printer::JUSTIFY_LEFT);
            
            // Receipt/Passage Number
            $receiptNumber = $receipt ? $receipt->receipt_number : $passage->passage_number;
            $this->printRow("Receipt No:", $receiptNumber);
            
            // Date & Time
            $this->printRow("Date:", $passage->entry_time->format('d/m/Y'));
            $this->printRow("Time:", $passage->entry_time->format('H:i:s'));
            
            $this->printer->feed(1);
            $this->printer->text("--------------------------------");
            $this->printer->feed(1);

            // === VEHICLE INFO ===
            $this->printer->setEmphasis(true);
            $this->printer->text("VEHICLE DETAILS");
            $this->printer->setEmphasis(false);
            $this->printer->feed(1);
            
            $this->printRow("Plate No:", $vehicle->plate_number ?? 'N/A');
            $this->printRow("Type:", $vehicle->bodyType->name ?? 'N/A');
            
            if ($vehicle->make || $vehicle->model) {
                $vehicleDesc = trim(($vehicle->make ?? '') . ' ' . ($vehicle->model ?? ''));
                if ($vehicleDesc) {
                    $this->printRow("Vehicle:", $vehicleDesc);
                }
            }
            
            $this->printer->feed(1);
            $this->printer->text("--------------------------------");
            $this->printer->feed(1);

            // === LOCATION INFO ===
            $this->printRow("Station:", $station->name ?? 'N/A');
            $this->printRow("Gate:", $gate->name ?? 'N/A');
            
            $this->printer->feed(1);
            $this->printer->text("--------------------------------");
            $this->printer->feed(1);

            // === PAYMENT INFO ===
            $this->printer->setEmphasis(true);
            $this->printer->text("PAYMENT DETAILS");
            $this->printer->setEmphasis(false);
            $this->printer->feed(1);

            $passageType = ucfirst($passage->passage_type ?? 'toll');
            $this->printRow("Type:", $passageType);

            if ($passage->passage_type === 'free' || $passage->passage_type === 'exempted') {
                $this->printer->setJustification(Printer::JUSTIFY_CENTER);
                $this->printer->setEmphasis(true);
                $this->printer->setTextSize(2, 1);
                $this->printer->text("** FREE ENTRY **");
                $this->printer->setTextSize(1, 1);
                $this->printer->setEmphasis(false);
            } else {
                $this->printRow("Base Rate:", "{$currency} " . number_format($passage->base_amount, 2));
                
                if ($passage->discount_amount > 0) {
                    $this->printRow("Discount:", "-{$currency} " . number_format($passage->discount_amount, 2));
                }
                
                $this->printer->feed(1);
                $this->printer->setEmphasis(true);
                $this->printRow("AMOUNT PAID:", "{$currency} " . number_format($passage->total_amount, 2));
                $this->printer->setEmphasis(false);
            }
            
            if ($receipt && $receipt->payment_method) {
                $this->printRow("Payment:", ucfirst($receipt->payment_method));
            }

            $this->printer->feed(1);
            $this->printer->text("================================");
            $this->printer->feed(1);

            // === FOOTER ===
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->text("Please keep this receipt");
            $this->printer->feed(1);
            $this->printer->text("for exit verification");
            $this->printer->feed(2);
            
            $this->printer->text($receiptConfig['footer_message'] ?? 'Thank you!');
            $this->printer->feed(1);
            
            // Print timestamp
            $this->printer->setTextSize(1, 1);
            $this->printer->text("Printed: " . now()->format('d/m/Y H:i:s'));
            $this->printer->feed(3);

            // Cut paper
            $this->printer->cut();

            $this->disconnect();

            Log::info('Entry receipt printed successfully', [
                'passage_id' => $passage->id,
                'plate_number' => $vehicle->plate_number ?? 'N/A',
            ]);

            return [
                'success' => true,
                'message' => 'Receipt printed successfully',
            ];

        } catch (Exception $e) {
            $this->disconnect();
            
            Log::error('Failed to print entry receipt', [
                'passage_id' => $passage->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to print receipt: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Print exit receipt with parking duration and fee
     */
    public function printExitReceipt(VehiclePassage $passage, ?Receipt $receipt = null): array
    {
        if (!$this->connect()) {
            return [
                'success' => false,
                'message' => 'Could not connect to printer. Please check printer connection.',
            ];
        }

        try {
            $receiptConfig = $this->config['receipt'] ?? [];
            $currency = $receiptConfig['currency'] ?? 'Tsh';

            // Load relationships if not loaded
            $passage->loadMissing(['vehicle.bodyType', 'entryGate', 'exitGate', 'entryStation', 'exitStation']);

            $vehicle = $passage->vehicle;
            $entryGate = $passage->entryGate;
            $exitGate = $passage->exitGate;
            $station = $passage->exitStation ?? $passage->entryStation;

            // Calculate duration
            $entryTime = $passage->entry_time;
            $exitTime = $passage->exit_time ?? now();
            $durationMinutes = $entryTime->diffInMinutes($exitTime);
            $hours = floor($durationMinutes / 60);
            $minutes = $durationMinutes % 60;
            $durationText = sprintf('%dh %dm', $hours, $minutes);

            // === HEADER ===
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->setEmphasis(true);
            $this->printer->setTextSize(2, 2);
            $this->printer->text($receiptConfig['company_name'] ?? 'Smart Parking');
            $this->printer->feed(1);
            
            $this->printer->setTextSize(1, 1);
            $this->printer->setEmphasis(false);
            $this->printer->text($receiptConfig['company_tagline'] ?? 'Safe & Secure Parking');
            $this->printer->feed(1);
            
            $this->printer->text("================================");
            $this->printer->feed(1);
            
            $this->printer->setEmphasis(true);
            $this->printer->text("** EXIT RECEIPT **");
            $this->printer->setEmphasis(false);
            $this->printer->feed(1);
            
            $this->printer->text("================================");
            $this->printer->feed(2);

            // === RECEIPT INFO ===
            $this->printer->setJustification(Printer::JUSTIFY_LEFT);
            
            $receiptNumber = $receipt ? $receipt->receipt_number : $passage->passage_number;
            $this->printRow("Receipt No:", $receiptNumber);
            $this->printRow("Passage No:", $passage->passage_number);
            
            $this->printer->feed(1);
            $this->printer->text("--------------------------------");
            $this->printer->feed(1);

            // === VEHICLE INFO ===
            $this->printer->setEmphasis(true);
            $this->printer->text("VEHICLE DETAILS");
            $this->printer->setEmphasis(false);
            $this->printer->feed(1);
            
            $this->printRow("Plate No:", $vehicle->plate_number ?? 'N/A');
            $this->printRow("Type:", $vehicle->bodyType->name ?? 'N/A');
            
            $this->printer->feed(1);
            $this->printer->text("--------------------------------");
            $this->printer->feed(1);

            // === PARKING DURATION ===
            $this->printer->setEmphasis(true);
            $this->printer->text("PARKING DURATION");
            $this->printer->setEmphasis(false);
            $this->printer->feed(1);

            $this->printRow("Entry:", $entryTime->format('d/m/Y H:i'));
            $this->printRow("Exit:", $exitTime->format('d/m/Y H:i'));
            $this->printRow("Duration:", $durationText);
            
            $this->printer->feed(1);
            $this->printer->text("--------------------------------");
            $this->printer->feed(1);

            // === PAYMENT INFO ===
            $this->printer->setEmphasis(true);
            $this->printer->text("PAYMENT DETAILS");
            $this->printer->setEmphasis(false);
            $this->printer->feed(1);

            $this->printRow("Base Rate:", "{$currency} " . number_format($passage->base_amount, 2));
            
            if ($passage->discount_amount > 0) {
                $this->printRow("Discount:", "-{$currency} " . number_format($passage->discount_amount, 2));
            }
            
            $this->printer->feed(1);
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->setEmphasis(true);
            $this->printer->setTextSize(2, 1);
            $this->printer->text("TOTAL: {$currency} " . number_format($passage->total_amount, 2));
            $this->printer->setTextSize(1, 1);
            $this->printer->setEmphasis(false);
            $this->printer->feed(1);
            
            $this->printer->setJustification(Printer::JUSTIFY_LEFT);
            if ($receipt && $receipt->payment_method) {
                $this->printRow("Payment:", ucfirst($receipt->payment_method));
            }

            $this->printer->feed(1);
            $this->printer->text("================================");
            $this->printer->feed(1);

            // === FOOTER ===
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->text($receiptConfig['footer_message'] ?? 'Thank you!');
            $this->printer->feed(1);
            
            $this->printer->text("Printed: " . now()->format('d/m/Y H:i:s'));
            $this->printer->feed(3);

            // Cut paper
            $this->printer->cut();

            $this->disconnect();

            Log::info('Exit receipt printed successfully', [
                'passage_id' => $passage->id,
                'plate_number' => $vehicle->plate_number ?? 'N/A',
                'total_amount' => $passage->total_amount,
            ]);

            return [
                'success' => true,
                'message' => 'Receipt printed successfully',
            ];

        } catch (Exception $e) {
            $this->disconnect();
            
            Log::error('Failed to print exit receipt', [
                'passage_id' => $passage->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to print receipt: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Print a formatted row (label: value)
     */
    private function printRow(string $label, string $value): void
    {
        $maxWidth = $this->config['model']['characters_per_line'] ?? 48;
        $labelWidth = strlen($label) + 1; // +1 for space
        $valueWidth = $maxWidth - $labelWidth;
        
        // Truncate value if too long
        if (strlen($value) > $valueWidth) {
            $value = substr($value, 0, $valueWidth - 3) . '...';
        }
        
        $padding = $maxWidth - strlen($label) - strlen($value);
        $this->printer->text($label . str_repeat(' ', max(1, $padding)) . $value);
        $this->printer->feed(1);
    }

    /**
     * Test printer connection
     */
    public function testConnection(): array
    {
        if (!$this->connect()) {
            return [
                'success' => false,
                'message' => 'Could not connect to printer',
                'config' => [
                    'type' => $this->config['connection_type'] ?? 'unknown',
                    'ip' => $this->config['network']['ip'] ?? 'N/A',
                    'port' => $this->config['network']['port'] ?? 'N/A',
                ],
            ];
        }

        try {
            // Print test page
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->setEmphasis(true);
            $this->printer->text("=== PRINTER TEST ===");
            $this->printer->feed(2);
            
            $this->printer->setEmphasis(false);
            $this->printer->text("Model: " . ($this->config['model']['name'] ?? 'Zy-Q822'));
            $this->printer->feed(1);
            $this->printer->text("Serial: " . ($this->config['model']['serial'] ?? 'N/A'));
            $this->printer->feed(1);
            $this->printer->text("Connection: " . ($this->config['connection_type'] ?? 'network'));
            $this->printer->feed(2);
            
            $this->printer->text("Printer is working!");
            $this->printer->feed(1);
            $this->printer->text("Time: " . now()->format('d/m/Y H:i:s'));
            $this->printer->feed(3);
            
            $this->printer->cut();
            
            $this->disconnect();

            return [
                'success' => true,
                'message' => 'Printer test successful! Check printer for test page.',
            ];

        } catch (Exception $e) {
            $this->disconnect();
            
            return [
                'success' => false,
                'message' => 'Printer test failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get printer status/configuration
     */
    public function getStatus(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'connection_type' => $this->config['connection_type'] ?? 'unknown',
            'network' => [
                'ip' => $this->config['network']['ip'] ?? 'Not configured',
                'port' => $this->config['network']['port'] ?? 9100,
            ],
            'model' => $this->config['model'] ?? [],
            'auto_print' => $this->config['auto_print'] ?? [],
        ];
    }
}

