<?php

namespace App\Http\Resources;

use App\Models\Analysis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Analysis
 */
class AnalysisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $revealed = $this->isRevealed();

        // Photos with public URLs, ordered by position.
        $photos = $this->photos
            ->sortBy('position')
            ->values()
            ->map(fn ($p) => [
                'id'       => $p->id,
                'position' => (int) $p->position,
                'url'      => $this->photoUrl($p->storage_path),
            ]);

        $urlByPosition = [];
        foreach ($photos as $p) {
            $urlByPosition[$p['position']] = $p['url'];
        }

        // The report is ONLY included once revealed — and each result gets the
        // matching photo URL so the frontend can render the actual images.
        $report = $revealed ? $this->report : null;
        if (is_array($report) && isset($report['results']) && is_array($report['results'])) {
            $report['results'] = array_map(function ($r) use ($urlByPosition) {
                $r['url'] = $urlByPosition[$r['position'] ?? null] ?? null;
                return $r;
            }, $report['results']);
        }

        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'status'      => $this->status,
            'audience'    => $this->audience,
            'photo_count' => (int) $this->photo_count,
            'created_at'  => optional($this->created_at)?->toIso8601String(),
            'paid_at'     => optional($this->paid_at)?->toIso8601String(),
            'reveal_at'   => optional($this->reveal_at)?->toIso8601String(),

            // Uploaded photos (id, position, public url).
            'photos' => $photos,

            // What the user is allowed to see (mirrors the app's logic).
            'user_facing_status' => $this->userFacingStatus(),
            'revealed'           => $revealed,
            'seconds_until_reveal' => $this->secondsUntilReveal(),

            'report' => $report,

            // Surface errors so the frontend can show a retry state.
            'error' => $this->status === 'failed' ? $this->error : null,
        ];
    }

    /** Public URL for a stored photo path (null-safe). */
    private function photoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        try {
            return Storage::disk('public')->url($path);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function isRevealed(): bool
    {
        if ($this->status === 'revealed') {
            return true;
        }
        if ($this->status === 'ready' && $this->reveal_at) {
            return $this->reveal_at->isPast();
        }
        return false;
    }

    private function userFacingStatus(): string
    {
        if ($this->status === 'failed') {
            return 'failed';
        }
        if ($this->status === 'draft') {
            return 'unpaid';
        }
        if ($this->isRevealed()) {
            return 'completed';
        }
        return 'processing';
    }

    private function secondsUntilReveal(): ?int
    {
        if (! $this->reveal_at || $this->isRevealed()) {
            return null;
        }
        return max(0, now()->diffInSeconds($this->reveal_at, false));
    }
}
