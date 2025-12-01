<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Gate;
use App\Services\GateHardwareService;
use Illuminate\Support\Facades\Log;

/**
 * ProcessGateHardware
 *
 * This Artisan command runs inside the Laravel application and forwards
 * cached gate actions (open/close/deny) to the configured boom gate devices.
 *
 * It does **not** change the existing gate control logic – it only consumes
 * the cache entries created by GateControlService/manual/emergency control.
 */
class ProcessGateHardware extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gate:process-hardware {--once : Run a single pass and exit instead of looping}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Forward cached gate control actions to physical boom gate devices';

    /**
     * Execute the console command.
     */
    public function handle(GateHardwareService $hardwareService): int
    {
        $this->info('Starting gate hardware processor...');

        $runOnce = (bool) $this->option('once');

        $loop = function () use ($hardwareService) {
            $gates = Gate::active()->get();

            if ($gates->isEmpty()) {
                $this->line('No active gates found.');
                return;
            }

            foreach ($gates as $gate) {
                $result = $hardwareService->processCachedActionForGateId($gate->id);

                if (!empty($result['action'])) {
                    $status = $result['success'] ? '✓' : '✗';
                    $this->line("{$status} Gate {$gate->id} ({$gate->name}) action={$result['action']} - {$result['message']}");
                }
            }
        };

        if ($runOnce) {
            $loop();
            return Command::SUCCESS;
        }

        // Simple long-running loop for supervised workers
        while (true) {
            try {
                $loop();
            } catch (\Exception $e) {
                $this->error('Error processing hardware actions: ' . $e->getMessage());
                Log::error('ProcessGateHardware: exception in loop', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            // Short sleep between iterations to avoid busy-looping
            usleep(500000); // 0.5 seconds
        }
    }
}


