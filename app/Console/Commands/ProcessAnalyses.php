<?php

namespace App\Console\Commands;

use App\Models\Analysis;
use App\Support\ReportGenerator;
use Illuminate\Console\Command;

/**
 * The MatchFrame worker. Run on a schedule (see routes/console.php).
 *
 *  Pass 0  failed  -> queued     (auto-retry transient failures, up to a cap)
 *  Pass 1  queued  -> processing -> ready   (AI runs; report stored but HELD)
 *  Pass 2  ready   -> revealed              (once reveal_at has passed)
 *
 * Auto-retry: a failed analysis is re-queued automatically while
 * attempts < AI_MAX_ATTEMPTS, waiting AI_RETRY_BACKOFF_MINUTES between tries.
 * Once it hits the cap it stays "failed" for an admin to inspect / Re-queue
 * manually (which resets the counter).
 */
class ProcessAnalyses extends Command
{
    protected $signature = 'analyses:process {--limit=25}';
    protected $description = 'Run the AI pipeline on queued analyses, auto-retry failures, and reveal due reports';

    public function handle(ReportGenerator $generator): int
    {
        $limit = (int) $this->option('limit');
        $maxAttempts = max(1, (int) env('AI_MAX_ATTEMPTS', 3));
        $backoffMinutes = max(0, (int) env('AI_RETRY_BACKOFF_MINUTES', 10));

        // ── Pass 0: auto-retry failed analyses that haven't hit the cap ───
        $retryable = Analysis::where('status', 'failed')
            ->whereNotNull('paid_at')
            ->where('attempts', '<', $maxAttempts)
            ->where(function ($q) use ($backoffMinutes) {
                $q->whereNull('last_tried_at')
                  ->orWhere('last_tried_at', '<=', now()->subMinutes($backoffMinutes));
            })
            ->limit($limit)
            ->get();

        foreach ($retryable as $analysis) {
            $analysis->update(['status' => 'queued']);
            $this->info("Re-queued {$analysis->id} (attempt {$analysis->attempts}/{$maxAttempts})");
        }

        // ── Pass 1: process queued analyses ──────────────────────────────
        $queued = Analysis::where('status', 'queued')->limit($limit)->get();
        foreach ($queued as $analysis) {
            try {
                $analysis->update([
                    'status'        => 'processing',
                    'attempts'      => (int) $analysis->attempts + 1,
                    'last_tried_at' => now(),
                ]);

                $report = $generator->generate($analysis);

                $analysis->update([
                    'status'       => 'ready', // done, but held until reveal_at
                    'report'       => $report,
                    'processed_at' => now(),
                    'error'        => null,
                ]);
                $this->info("Processed {$analysis->id} (held until {$analysis->reveal_at})");
            } catch (\Throwable $e) {
                $gaveUp = (int) $analysis->attempts >= $maxAttempts;
                $analysis->update([
                    'status' => 'failed',
                    'error'  => substr($e->getMessage(), 0, 1000),
                ]);
                $this->error(
                    "Failed {$analysis->id} (attempt {$analysis->attempts}/{$maxAttempts})"
                    .($gaveUp ? ' — gave up, needs manual re-queue' : ' — will auto-retry')
                    .": {$e->getMessage()}"
                );
            }
        }

        // ── Pass 2: reveal anything whose time has come ───────────────────
        $due = Analysis::where('status', 'ready')
            ->whereNotNull('reveal_at')
            ->where('reveal_at', '<=', now())
            ->limit($limit)
            ->get();

        foreach ($due as $analysis) {
            $analysis->update(['status' => 'revealed']);
            // Hook: notify the user their report is ready (mail/queue) here.
            $this->info("Revealed {$analysis->id}");
        }

        $this->info("Done. Re-queued {$retryable->count()}, processed {$queued->count()}, revealed {$due->count()}.");

        return self::SUCCESS;
    }
}
