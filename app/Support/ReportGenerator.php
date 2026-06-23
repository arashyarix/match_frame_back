<?php

namespace App\Support;

use App\Models\Analysis;
use App\Models\AppSetting;
use App\Support\Ai\AnthropicReportProvider;
use App\Support\Ai\ImagePrep;
use App\Support\Ai\MockReportProvider;
use App\Support\Ai\OpenAiReportProvider;
use Illuminate\Support\Facades\Storage;

/**
 * Produces the analysis report in the shape the frontend renders.
 *
 * The active provider is chosen in the admin panel (Settings → AI provider):
 *   - "mock"      → built-in deterministic report (no key needed)
 *   - "anthropic" → Claude (vision)
 *   - "openai"    → GPT (vision)
 *
 * If a real provider is selected but unkeyed, we fall back to the built-in one.
 * If the chosen model errors, the exception propagates so the worker records the
 * analysis as "failed" (the admin can then re-queue it).
 */
class ReportGenerator
{
    /** Max images sent to the model (keeps token cost/latency sane). */
    private const MAX_IMAGES = 12;

    /** Total base64 payload budget across all images (~ keeps us well under API limits). */
    private const MAX_TOTAL_BASE64_BYTES = 12_000_000; // ~12 MB

    public function generate(Analysis $analysis): array
    {
        $settings = AppSetting::singleton();

        // No real provider configured → built-in report (no images needed).
        if (! $settings->aiReady()) {
            return (new MockReportProvider())->generate($analysis, []);
        }

        $images = $this->loadImages($analysis);

        // If we somehow have no readable images, don't waste an API call.
        if (empty($images)) {
            return (new MockReportProvider())->generate($analysis, []);
        }

        $provider = match ($settings->aiProvider()) {
            'anthropic' => new AnthropicReportProvider($settings),
            'openai'    => new OpenAiReportProvider($settings),
            default     => new MockReportProvider(),
        };

        return $provider->generate($analysis, $images);
    }

    /**
     * Read each uploaded photo from storage as base64, in position order.
     * Returns [['position'=>int,'mime'=>string,'data'=>base64], ...].
     */
    private function loadImages(Analysis $analysis): array
    {
        $disk = Storage::disk('public');
        $photos = $analysis->photos()->orderBy('position')->get();

        $images = [];
        $totalBytes = 0;
        foreach ($photos as $photo) {
            if (count($images) >= self::MAX_IMAGES) {
                break;
            }
            $path = (string) $photo->storage_path;
            if ($path === '') {
                continue;
            }
            try {
                if (! $disk->exists($path)) {
                    continue;
                }
                $bytes = $disk->get($path);
                $mime = $disk->mimeType($path) ?: 'image/jpeg';
                // Only send actual images.
                if (! str_starts_with($mime, 'image/')) {
                    continue;
                }

                // Downscale + recompress so requests stay within API size limits.
                $prepared = ImagePrep::downscale($bytes, $mime);

                // Respect the overall payload budget.
                if ($totalBytes + strlen($prepared['data']) > self::MAX_TOTAL_BASE64_BYTES && ! empty($images)) {
                    break;
                }
                $totalBytes += strlen($prepared['data']);

                $images[] = [
                    'position' => (int) $photo->position,
                    'mime'     => $prepared['mime'],
                    'data'     => $prepared['data'],
                ];
            } catch (\Throwable $e) {
                // Skip unreadable files; the normalizer still covers the position.
                continue;
            }
        }

        return $images;
    }
}
