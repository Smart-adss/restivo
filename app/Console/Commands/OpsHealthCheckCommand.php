<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class OpsHealthCheckCommand extends Command
{
    protected $signature = 'ops:health-check
                            {--disk-min-mb=1024 : Fail when free disk is below this many megabytes}
                            {--json : Machine-readable output}';

    protected $description = 'Report failed_jobs, log size, and free disk for daily pilot monitoring';

    public function handle(): int
    {
        $failedCount = (int) DB::table('failed_jobs')->count();
        $latestFailed = DB::table('failed_jobs')->orderByDesc('id')->first();

        $logPath = storage_path('logs/laravel.log');
        $logBytes = File::exists($logPath) ? File::size($logPath) : 0;

        $freeBytes = @disk_free_space(base_path());
        $freeMb = $freeBytes === false ? null : (int) floor($freeBytes / 1024 / 1024);
        $diskMin = (int) $this->option('disk-min-mb');

        $payload = [
            'failed_jobs' => $failedCount,
            'latest_failed_at' => $latestFailed->failed_at ?? null,
            'latest_failed_uuid' => $latestFailed->uuid ?? null,
            'log_bytes' => $logBytes,
            'disk_free_mb' => $freeMb,
            'disk_min_mb' => $diskMin,
            'checked_at' => now('Africa/Cairo')->toDateTimeString().' Africa/Cairo',
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(
                ['metric', 'value'],
                collect($payload)->map(fn ($value, $key) => [$key, $value === null ? 'n/a' : (string) $value])->all()
            );
        }

        $failed = $failedCount > 0 || ($freeMb !== null && $freeMb < $diskMin);

        if ($failedCount > 0) {
            $this->error('failed_jobs is not empty — open /platform/failed-jobs');
        }

        if ($freeMb !== null && $freeMb < $diskMin) {
            $this->error("Free disk {$freeMb} MB is below {$diskMin} MB");
        }

        if (! $failed) {
            $this->info('Health check OK');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
