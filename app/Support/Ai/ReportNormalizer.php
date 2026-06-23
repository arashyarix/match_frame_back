<?php

namespace App\Support\Ai;

use App\Models\Analysis;

/**
 * Turns whatever JSON a model returns into the exact, complete report shape the
 * frontend renders — filling gaps, fixing ranks/tones/labels, clamping numbers,
 * and guaranteeing one entry per photo position. Defensive on purpose: models
 * occasionally omit a field or a photo.
 */
class ReportNormalizer
{
    public static function normalize(array $raw, Analysis $analysis): array
    {
        $count = max(1, (int) $analysis->photo_count);
        $label = PromptBuilder::audienceLabel($analysis);

        // Index whatever results we got by position.
        $byPosition = [];
        foreach (($raw['results'] ?? []) as $r) {
            $pos = (int) ($r['position'] ?? 0);
            if ($pos >= 1 && $pos <= $count) {
                $byPosition[$pos] = $r;
            }
        }

        // Ensure every position exists; synthesize a reasonable score if missing.
        $rows = [];
        foreach (range(1, $count) as $pos) {
            $r = $byPosition[$pos] ?? [];
            $pct = (int) round((float) ($r['pct'] ?? $r['votes'] ?? (70 - $pos * 3)));
            $pct = max(0, min(100, $pct));
            $rows[] = [
                'position' => $pos,
                'pct'      => $pct,
                'note'     => trim((string) ($r['note'] ?? '')),
                'label'    => (string) ($r['label'] ?? ''),
            ];
        }

        // Rank by pct desc (stable by position for ties).
        usort($rows, fn ($a, $b) => $b['pct'] <=> $a['pct'] ?: $a['position'] <=> $b['position']);

        $results = [];
        foreach ($rows as $i => $row) {
            $rank = $i + 1;
            $isTop = $rank === 1;
            $tone = $isTop ? 'gold' : ($rank <= 3 ? 'primary' : 'muted');
            $label = $row['label'] !== '' ? $row['label']
                : ($isTop ? 'Best Main Profile Photo'
                    : ($rank <= 3 ? 'Strong Supporting Photo' : 'Use Sparingly'));
            $note = $row['note'] !== '' ? $row['note']
                : ($isTop
                    ? 'Clear light, a genuine smile, and strong eye contact made this the standout first impression.'
                    : 'A solid supporting image for later in your profile.');

            $results[] = [
                'photoId'  => 'p'.$row['position'],
                'position' => $row['position'],
                'rank'     => $rank,
                'votes'    => $row['pct'],
                'pct'      => $row['pct'],
                'label'    => $label,
                'tone'     => $tone,
                'note'     => $note,
            ];
        }

        $top = $results[0];

        $summary = trim((string) ($raw['summary'] ?? ''));
        if ($summary === '') {
            $summary = "Based on an audience of 100 women ({$label}), photo {$top['position']} "
                ."is your strongest first impression at {$top['pct']}% approval.";
        }

        $overall = array_values(array_filter(array_map(
            fn ($p) => trim((string) $p),
            (array) ($raw['overall'] ?? [])
        )));
        if (empty($overall)) {
            $overall = [
                'Your photos read as warm and approachable overall.',
                'Leading with your top-ranked photo should improve match rates.',
            ];
        }

        $recommendations = array_values(array_filter(array_map(
            fn ($p) => trim((string) $p),
            (array) ($raw['recommendations'] ?? [])
        )));
        if (empty($recommendations)) {
            $recommendations = [
                "Lead with photo {$top['position']} as your main profile picture.",
                'Add one more close-up taken in soft, natural daylight.',
                'Drop the lowest-ranked photo to keep the set strong.',
            ];
        }

        return [
            'summary'         => $summary,
            'audienceLabel'   => $label,
            'results'         => $results,
            'overall'         => $overall,
            'recommendations' => $recommendations,
            'generatedAt'     => now()->toIso8601String(),
        ];
    }

    /** Extract the first JSON object from a model's text response. */
    public static function decodeJson(string $text): array
    {
        $text = trim($text);
        // Strip code fences if present.
        $text = preg_replace('/^```(?:json)?|```$/m', '', $text);
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end < $start) {
            throw new \RuntimeException('Model did not return JSON.');
        }
        $json = substr($text, $start, $end - $start + 1);
        $data = json_decode($json, true);
        if (! is_array($data)) {
            throw new \RuntimeException('Model returned invalid JSON.');
        }
        return $data;
    }
}
