<?php

namespace Database\Seeders;

use App\Models\Analysis;
use App\Models\AppSetting;
use App\Models\AuthUser;
use App\Models\Payment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds a few end users, analyses (one revealed with a full report, one held
 * back, one failed) and matching payments — so the admin panel has real data to
 * show right after install. Safe to skip in production.
 */
class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        AppSetting::singleton();

        if (AuthUser::count() > 0) {
            $this->command?->warn('Users already exist — skipping sample data.');
            return;
        }

        $sampleReport = [
            'summary'       => 'Based on an audience of 100 women (ages 25-34), photo 3 is your strongest first impression: natural light, a genuine smile, and clear eye contact.',
            'audienceLabel' => 'Women, 25-34',
            'results' => [
                ['photoId' => 'p3', 'position' => 3, 'rank' => 1, 'votes' => 87, 'pct' => 87, 'label' => 'Best Main Profile Photo', 'tone' => 'gold', 'note' => 'Natural light, genuine smile, eyes clearly visible.'],
                ['photoId' => 'p1', 'position' => 1, 'rank' => 2, 'votes' => 79, 'pct' => 79, 'label' => 'Strong Supporting Photo', 'tone' => 'primary', 'note' => 'A clear, friendly second image.'],
                ['photoId' => 'p5', 'position' => 5, 'rank' => 3, 'votes' => 71, 'pct' => 71, 'label' => 'Strong Supporting Photo', 'tone' => 'primary', 'note' => 'Good warmth and context.'],
            ],
            'overall'         => ['Your photos read as warm and genuine.'],
            'recommendations' => ['Lead with photo 3.', 'Add one more close-up in soft daylight.'],
            'generatedAt'     => now()->toIso8601String(),
        ];

        $rows = [
            [
                'email' => 'amir@example.com',
                'name'  => 'Profile Refresh',
                'status' => 'revealed',
                'audience' => 'w2534',
                'photo_count' => 6,
                'created' => now()->subHours(40),
                'paid'    => now()->subHours(40),
                'processed' => now()->subHours(26),
                'reveal'  => now()->subHours(14),
                'report'  => $sampleReport,
                'error'   => null,
            ],
            [
                'email' => 'reza@example.com',
                'name'  => 'Summer Set',
                'status' => 'ready', // AI done internally, still held back
                'audience' => 'w2129',
                'photo_count' => 8,
                'created' => now()->subHours(5),
                'paid'    => now()->subHours(5),
                'processed' => now()->subHour(),
                'reveal'  => now()->addHours(9),
                'report'  => $sampleReport,
                'error'   => null,
            ],
            [
                'email' => 'sina@example.com',
                'name'  => 'Quick Test',
                'status' => 'failed',
                'audience' => 'w3039',
                'photo_count' => 3,
                'created' => now()->subHours(60),
                'paid'    => now()->subHours(60),
                'processed' => null,
                'reveal'  => null,
                'report'  => null,
                'error'   => 'Vision model timed out while reviewing photo 2. Re-queue to retry.',
            ],
        ];

        foreach ($rows as $r) {
            $userId = (string) Str::uuid();
            AuthUser::create([
                'id' => $userId,
                'email' => $r['email'],
                'email_confirmed_at' => now()->subDays(3),
                'last_sign_in_at' => $r['created'],
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ]);

            $analysisId = (string) Str::uuid();
            Analysis::create([
                'id' => $analysisId,
                'user_id' => $userId,
                'name' => $r['name'],
                'status' => $r['status'],
                'audience' => $r['audience'],
                'photo_count' => $r['photo_count'],
                'created_at' => $r['created'],
                'paid_at' => $r['paid'],
                'processed_at' => $r['processed'],
                'reveal_at' => $r['reveal'],
                'report' => $r['report'],
                'error' => $r['error'],
            ]);

            if ($r['paid']) {
                Payment::create([
                    'id' => (string) Str::uuid(),
                    'analysis_id' => $analysisId,
                    'user_id' => $userId,
                    'amount_cents' => 1200,
                    'currency' => 'usd',
                    'status' => 'paid',
                    'created_at' => $r['paid'],
                ]);
            }
        }

        $this->command?->info('Seeded 3 sample users, analyses and payments.');
    }
}
