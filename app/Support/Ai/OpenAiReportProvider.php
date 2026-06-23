<?php

namespace App\Support\Ai;

use App\Models\Analysis;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;

/**
 * Generates the report with OpenAI's GPT (vision). Photos are sent as data URLs;
 * JSON mode keeps the response parseable.
 */
class OpenAiReportProvider implements ReportProvider
{
    public function __construct(private AppSetting $settings) {}

    public function generate(Analysis $analysis, array $images): array
    {
        $key = $this->settings->aiKey();
        if (! $key) {
            throw new \RuntimeException('OpenAI API key is not set.');
        }

        $content = [['type' => 'text', 'text' => PromptBuilder::userText($analysis)]];
        foreach ($images as $img) {
            $content[] = [
                'type'      => 'image_url',
                'image_url' => ['url' => "data:{$img['mime']};base64,{$img['data']}"],
            ];
        }

        $response = Http::withToken($key)
            ->timeout(120)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'           => $this->settings->aiModel(),
                'max_tokens'      => 2000,
                'response_format' => ['type' => 'json_object'],
                'messages'        => [
                    ['role' => 'system', 'content' => PromptBuilder::system()],
                    ['role' => 'user', 'content' => $content],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'OpenAI request failed ('.$response->status().'): '.$response->body()
            );
        }

        $text = (string) $response->json('choices.0.message.content', '');
        $raw = ReportNormalizer::decodeJson($text);

        return ReportNormalizer::normalize($raw, $analysis);
    }
}
