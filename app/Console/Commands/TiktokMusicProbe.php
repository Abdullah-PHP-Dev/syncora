<?php

namespace App\Console\Commands;

use App\Models\SocialAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Throwaway diagnostic for the "Please select valid music for Carousel Ads."
 * rejection - dumps what TikTok's music endpoints actually return for this
 * advertiser so the fix is based on the real response shape rather than
 * guesswork. Delete once carousel music is working.
 *
 * Usage: php artisan tiktok:music-probe {--music-id=} {--page-size=20}
 */
class TiktokMusicProbe extends Command
{
    protected $signature = 'tiktok:music-probe {--music-id=} {--page-size=20} {--upload}';

    protected $description = 'Probe TikTok music endpoints to debug Carousel Ads music rejection';

    public function handle(): int
    {
        $account = SocialAccount::wherePlatform('tiktok')->where('has_ads_permission', true)->whereNotNull('access_token')->latest('id')->first();

        if (!$account) {
            $this->error('No TikTok ad account with an access token found.');
            return self::FAILURE;
        }

        $base = adminSetting('ads.tiktok.base_url');

        $this->info('Base URL     : ' . $base);
        $this->info('Advertiser   : ' . $account->platform_account_id);
        $this->newLine();

        // 1. Commercial Music Library - what does the catalog actually hold?
        $this->probe('file/music/get/', $base . 'file/music/get/', $account, [
            'advertiser_id' => $account->platform_account_id,
            'page_size'     => (int) $this->option('page-size'),
        ]);

        // 2. If a specific music_id was passed (e.g. the self-uploaded track
        //    that got rejected), ask TikTok what it thinks of it.
        if ($musicId = $this->option('music-id')) {
            $this->probe('file/music/get/ (filtered)', $base . 'file/music/get/', $account, [
                'advertiser_id' => $account->platform_account_id,
                'music_ids'     => [$musicId],
            ]);
        }

        // 3. Upload a generated silent WAV and dump the FULL raw response -
        //    uploadMusic() keeps only data.music_id, so this is the only way
        //    to see whether that id matches what file/music/get/ later lists.
        if ($this->option('upload')) {
            $this->uploadProbe($base, $account);
        }

        return self::SUCCESS;
    }

    private function uploadProbe(string $base, SocialAccount $account): void
    {
        $this->line('=== file/music/upload/ (raw response) ===');

        $path = storage_path('app/tiktok-music-probe.wav');
        file_put_contents($path, $this->silentWav());

        try {
            $response = Http::withHeaders(['Access-Token' => $account->access_token])
                ->timeout(60)
                ->attach('music_file', file_get_contents($path), 'probe.wav')
                ->post($base . 'file/music/upload/', [
                    ['name' => 'advertiser_id',   'contents' => $account->platform_account_id],
                    ['name' => 'upload_type',     'contents' => 'UPLOAD_BY_FILE'],
                    ['name' => 'file_name',       'contents' => 'probe.wav'],
                    ['name' => 'music_signature', 'contents' => md5_file($path)],
                ]);

            $this->line('HTTP ' . $response->status());
            $this->line(json_encode($response->json() ?? $response->body(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {
            $this->error(get_class($e) . ': ' . $e->getMessage());
        } finally {
            @unlink($path);
        }

        $this->newLine();
    }

    /**
     * Minimal 1-second 8kHz mono silent WAV - enough for the endpoint to
     * accept a file without shipping a binary fixture into the repo.
     */
    private function silentWav(): string
    {
        $sampleRate = 8000;
        $samples    = $sampleRate;
        $dataSize   = $samples * 2;

        return 'RIFF' . pack('V', 36 + $dataSize) . 'WAVE'
            . 'fmt ' . pack('V', 16) . pack('v', 1) . pack('v', 1)
            . pack('V', $sampleRate) . pack('V', $sampleRate * 2)
            . pack('v', 2) . pack('v', 16)
            . 'data' . pack('V', $dataSize) . str_repeat("\0", $dataSize);
    }

    private function probe(string $label, string $url, SocialAccount $account, array $query): void
    {
        $this->line("=== {$label} ===");

        try {
            $response = Http::withHeaders(['Access-Token' => $account->access_token])
                ->timeout(30)
                ->get($url, $this->flatten($query));

            $this->line('HTTP ' . $response->status());
            $this->line(json_encode($response->json() ?? $response->body(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {
            $this->error(get_class($e) . ': ' . $e->getMessage());
        }

        $this->newLine();
    }

    /**
     * TikTok expects array query params JSON-encoded, not PHP's a[]=1&a[]=2.
     */
    private function flatten(array $query): array
    {
        return collect($query)
            ->map(fn($value) => is_array($value) ? json_encode($value) : $value)
            ->all();
    }
}
