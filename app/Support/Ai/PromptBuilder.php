<?php

namespace App\Support\Ai;

use App\Models\Analysis;

/**
 * Builds the instruction prompt sent to Claude / GPT. The model receives the
 * photos as images (in position order) and must return STRICT JSON in the shape
 * the frontend renders.
 *
 * Important: the output is shown to the end user as if it were genuine audience
 * feedback, so the model is told never to mention AI, models, or simulation.
 */
class PromptBuilder
{
    public const AUDIENCES = [
        'w1824' => 'Women, 18-24',
        'w2129' => 'Women, 21-29',
        'w2534' => 'Women, 25-34',
        'w3039' => 'Women, 30-39',
        'w3545' => 'Women, 35-45',
    ];

    public static function audienceLabel(Analysis $analysis): string
    {
        return self::AUDIENCES[$analysis->audience] ?? 'Women, 25-34';
    }

    /** System / role instruction. */
    public static function system(): string
    {
        return <<<TXT
        You are an expert dating-profile reviewer aggregating the reactions of an
        audience of 100 women toward a man's set of profile photos. For the given
        audience, judge each photo on first impression: lighting, genuine smile,
        eye contact, framing, grooming, background, and overall approachability.

        Decide which single photo he should lead with, then rank the rest.

        Tone of all written text: warm, specific, encouraging, and honest. Write
        as if you are summarizing real audience feedback. NEVER mention AI,
        language models, algorithms, automation, or that anything is simulated.
        Refer only to "the audience" or "women in this age range".

        Respond with ONLY a single JSON object — no markdown, no prose, no code
        fences.
        TXT;
    }

    /** Per-analysis user instruction describing the expected JSON. */
    public static function userText(Analysis $analysis): string
    {
        $count = max(1, (int) $analysis->photo_count);
        $label = self::audienceLabel($analysis);

        return <<<TXT
        Audience: {$label}
        Number of photos: {$count}
        The {$count} photos are attached in order; photo at position 1 is the
        first image, position 2 the second, and so on.

        Return JSON with EXACTLY this shape:

        {
          "summary": "1-2 sentence overall takeaway naming the best photo",
          "audienceLabel": "{$label}",
          "results": [
            {
              "photoId": "p1",
              "position": 1,
              "rank": 1,
              "votes": 0-100 integer (share of the audience picking it first),
              "pct": same integer as votes,
              "label": "Best Main Profile Photo" | "Strong Supporting Photo" | "Use Sparingly",
              "tone": "gold" for rank 1, "primary" for ranks 2-3, "muted" otherwise,
              "note": "one specific sentence on why it placed here"
            }
            // one object for EVERY position 1..{$count}
          ],
          "overall": ["2-3 short paragraphs about the set as a whole"],
          "recommendations": ["3 concrete, kind next steps"]
        }

        Rules:
        - Include every position from 1 to {$count} exactly once.
        - Sort results by votes descending and set rank accordingly (1 = best).
        - votes/pct are integers 0-100; the top photo should be the highest.
        - Output JSON only.
        TXT;
    }
}
