<?php

namespace App\Support\Ai;

use App\Models\Analysis;

/**
 * A source of analysis reports. Implementations: MockReportProvider (built-in,
 * deterministic), AnthropicReportProvider (Claude), OpenAiReportProvider (GPT).
 *
 * $images is an ordered list of ['position' => int, 'mime' => string,
 * 'data' => base64 string] for each uploaded photo.
 *
 * Returns the canonical report array the frontend renders (see ReportNormalizer).
 */
interface ReportProvider
{
    public function generate(Analysis $analysis, array $images): array;
}
