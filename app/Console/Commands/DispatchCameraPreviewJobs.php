<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\GateDevice;
use App\Jobs\FetchCameraPreviewJob;

class DispatchCameraPreviewJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'camera:dispatch-previews';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch FetchCameraPreviewJob for active snapshot-capable gate devices';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $devices = GateDevice::where('supports_snapshot', true)
                ->where('status', 'active')
                ->get();

            foreach ($devices as $device) {
                dispatch(new FetchCameraPreviewJob($device->id));
            }

            $this->info('Dispatched FetchCameraPreviewJob for ' . $devices->count() . ' devices');
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to dispatch camera preview jobs: ' . $e->getMessage());
            return 1;
        }
    }
}
