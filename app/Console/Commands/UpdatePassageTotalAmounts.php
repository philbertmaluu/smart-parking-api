<?php

namespace App\Console\Commands;

use App\Models\VehiclePassage;
use App\Repositories\VehiclePassageRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdatePassageTotalAmounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'passages:update-total-amounts {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update total_amount for existing vehicle passages based on duration';

    protected $repository;

    /**
     * Create a new command instance.
     */
    public function __construct(VehiclePassageRepository $repository)
    {
        parent::__construct();
        $this->repository = $repository;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to update vehicle passage total amounts...');

        // Find all passages with exit_time (completed passages)
        $passages = VehiclePassage::whereNotNull('exit_time')
            ->whereNotNull('entry_time')
            ->get();

        $this->info("Found {$passages->count()} completed passages to check.");

        $updated = 0;
        $skipped = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar($passages->count());
        $bar->start();

        foreach ($passages as $passage) {
            try {
                // Calculate hours spent
                $entryTime = $passage->entry_time;
                $exitTime = $passage->exit_time;
                $hoursSpent = $entryTime->diffInHours($exitTime, true);

                // Calculate hours to charge using the same logic
                $hoursToCharge = $this->calculateHoursToCharge($hoursSpent);

                // Calculate new total amount
                $newTotalAmount = $passage->base_amount * $hoursToCharge;

                // Only update if the amount is different
                if (abs($passage->total_amount - $newTotalAmount) > 0.01) {
                    if (!$this->option('dry-run')) {
                        $passage->update([
                            'total_amount' => $newTotalAmount,
                            'duration_minutes' => $entryTime->diffInMinutes($exitTime),
                        ]);
                    }
                    $updated++;
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $this->error("\nError processing passage ID {$passage->id}: " . $e->getMessage());
                $errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($this->option('dry-run')) {
            $this->info("DRY RUN - No changes made");
            $this->info("Would update: {$updated} passages");
            $this->info("Would skip: {$skipped} passages");
        } else {
            $this->info("✓ Updated: {$updated} passages");
            $this->info("✓ Skipped: {$skipped} passages (already correct)");
        }

        if ($errors > 0) {
            $this->warn("⚠ Errors: {$errors} passages");
        }

        $this->info('Done!');

        return Command::SUCCESS;
    }

    /**
     * Calculate total billable hours based on parking time and smart charging rules.
     *
     * Charging Rules:
     * - Minimum charge — always 1 hour
     * - Up to 1 hour 30 minutes → Still charge only 1 hour
     * - From 1 hour 31 minutes up to 2 hours → Charge double = 2 hours
     * - More than 2 hours → Round up to the next full hour
     *
     * @param float $hoursSpent  Actual number of hours spent (e.g., 1.25 = 1h15m)
     * @return int  Billable hours to charge
     */
    private function calculateHoursToCharge(float $hoursSpent): int
    {
        // Minimum charge — always 1 hour
        if ($hoursSpent <= 0) {
            return 1;
        }

        // Up to 1 hour 30 minutes → Still charge only 1 hour
        if ($hoursSpent <= 1.5) {
            return 1;
        }

        // From 1 hour 31 minutes up to 2 hours → Charge double = 2 hours
        if ($hoursSpent < 2.0) {
            return 2;
        }

        // More than 2 hours → Round up to the next full hour
        return (int) ceil($hoursSpent);
    }
}

