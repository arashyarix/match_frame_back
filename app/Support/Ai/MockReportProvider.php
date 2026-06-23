<?php

namespace App\Support\Ai;

use App\Models\Analysis;

/**
 * Built-in, deterministic report. Used when no AI provider is selected/keyed, so
 * the whole system works end-to-end with zero external dependencies.
 */
class MockReportProvider implements ReportProvider
{
    public function generate(Analysis $analysis, array $images): array
    {
        $count = max(1, (int) $analysis->photo_count);

        // Pseudo-random but stable per analysis id.
        mt_srand(crc32($analysis->id));

        $raw = ['results' => []];
        for ($i = 1; $i <= $count; $i++) {
            $raw['results'][] = ['position' => $i, 'pct' => mt_rand(45, 92)];
        }

        return ReportNormalizer::normalize($raw, $analysis);
    }
}
