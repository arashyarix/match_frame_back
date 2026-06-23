<?php

namespace App\Support\Ai;

use App\Models\Analysis;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;

/**
 * Generates the report with Anthropic's Claude (vision). The photos are sent as
 * base64 images; the model returns the JSON report, which we normalize.
 */
class AnthropicReportProvider implements ReportProvider
{
    public function __construct(private AppSetting $settings) {}

    public function generate(Analysis $analysis, array $images): array
    {
        $key = $this->settings->aiKey();
        if (! $key) {
            throw new \RuntimeException('Anthropic API key is not set.');
        }

        // Build the multimodal message: instruction text + each photo.
        $content = [['type' => 'text', 'text' => PromptBuilder::userText($analysis)]];
        foreach ($images as $img) {
            $content[] = [
                'type'   => 'image',
                'source' => [
                    'type'       => 'base64',
                    'media_type' => $img['mime'],
                    'data'       => $img['data'],
                ],
            ];
        }

        $response = Http::withHeaders([
            'x-api-key'         => $key,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
            'model'      => $this->settings->aiModel(),
            'max_tokens' => 2000,
            'system'     => PromptBuilder::system(),
            'messages'   => [['role' => 'user', 'content' => $content]],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'Anthropic request failed ('.$response->status().'): '.$response->body()
            );
        }

        // Claude returns content as an array of blocks; concatenate text blocks.
        $text = collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");

        $raw = ReportNormalizer::decodeJson($text);

        return ReportNormalizer::normalize($raw, $analysis);
    }
}
