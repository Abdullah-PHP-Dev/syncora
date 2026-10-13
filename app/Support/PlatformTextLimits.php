<?php

namespace App\Support;

use App\Models\SocialAccount;
use App\Services\PostServices\XPostService;

/**
 * Text limits each platform's publishing API enforces - one place for the
 * composer's live counters and PostRequest's validation.
 *
 * - Facebook Page post (Graph API message): 63,206 characters
 * - Instagram caption (Graph API): 2,200 characters, 30 hashtags, 20 @mentions
 * - X: 280 weighted characters (links 23, CJK/emoji 2); 25,000 with an
 *   X Basic/Premium subscription (XPostService::characterLimit())
 * - LinkedIn post commentary: 3,000 characters
 * - TikTok: video caption 2,200; photo post title 90, description 4,000
 * - YouTube: title 100 characters, description 5,000 bytes, no < or >
 * - Threads: 500 characters
 * - Pinterest: title 100, description 500 characters
 * - Google Business Profile post: 1,500 characters
 */
class PlatformTextLimits
{
    public const LIMITS = [
        'facebook'  => ['label' => 'Facebook', 'content' => 63206],
        'instagram' => ['label' => 'Instagram', 'content' => 2200, 'hashtags' => 30, 'mentions' => 20],
        'x'         => ['label' => 'X', 'content' => 280, 'weighted' => true],
        'linkedin'  => ['label' => 'LinkedIn', 'content' => 3000],
        'tiktok'    => ['label' => 'TikTok', 'content' => 2200, 'title' => 90],
        'youtube'   => ['label' => 'YouTube', 'content' => 5000, 'content_bytes' => true, 'title' => 100, 'no_angle_brackets' => true],
        'threads'   => ['label' => 'Threads', 'content' => 500],
        'pinterest' => ['label' => 'Pinterest', 'content' => 500, 'title' => 100],
        'google'    => ['label' => 'Google Business Profile', 'content' => 1500],
    ];

    /** The longest text any platform accepts (posts.content holds it). */
    public const MAX_CONTENT = 63206;

    public const MAX_TITLE = 100;

    /** Limits for one account - X depends on the account's subscription. */
    public static function forAccount(SocialAccount $account, bool $lookup = false): array
    {
        $limits = self::LIMITS[$account->platform] ?? ['label' => ucfirst($account->platform), 'content' => self::MAX_CONTENT];

        if ($account->platform === 'x') {
            $limits['content'] = $lookup
                ? (app(XPostService::class)->characterLimit($account) ?? 280)
                : (in_array($account->metadata['x_subscription_type'] ?? null, ['Basic', 'Premium', 'PremiumPlus'], true) ? 25000 : 280);
        }

        return $limits;
    }

    /**
     * Every problem with the text for the selected platforms/accounts,
     * keyed by platform. $accountIds: platform => [social_account ids]
     * (all of the user's posting accounts for a platform when empty).
     */
    public static function check(array $platforms, array $accountIds, string $content, ?string $title, int $userId): array
    {
        $problems = [];

        foreach (array_unique($platforms) as $platform) {
            $accounts = SocialAccount::where('user_id', $userId)->where('platform', $platform)
                ->when(! empty($accountIds[$platform]), fn ($q) => $q->whereIn('id', $accountIds[$platform]), fn ($q) => $q->where('has_posting_permission', true))
                ->get();

            $limitSets = $accounts->isEmpty()
                ? [self::LIMITS[$platform] ?? ['label' => ucfirst($platform), 'content' => self::MAX_CONTENT]]
                : $accounts->map(fn ($account) => self::forAccount($account, true))->all();

            // The strictest of the selected accounts (eg. one X account without Premium).
            $strictest = collect($limitSets)->sortBy('content')->first();
            if ($found = self::problems($strictest, $content, $title)) {
                $problems[$platform] = $found;
            }
        }

        return $problems;
    }

    /**
     * Problems with $content/$title for one platform's limits, as
     * human-readable messages (empty = fine).
     */
    public static function problems(array $limits, string $content, ?string $title = null): array
    {
        $label = $limits['label'];
        $problems = [];

        $length = match (true) {
            ! empty($limits['weighted']) => XPostService::weightedLength($content),
            ! empty($limits['content_bytes']) => strlen($content),
            default => mb_strlen($content),
        };
        if ($length > $limits['content']) {
            $unit = ! empty($limits['content_bytes']) ? 'bytes' : 'characters';
            $problems[] = "{$label} allows " . number_format($limits['content']) . " {$unit} - this text is " . number_format($length) . '.'
                . ($label === 'X' && $limits['content'] === 280 ? ' (25,000 with X Premium.)' : '');
        }

        if (isset($limits['hashtags']) && preg_match_all('/(^|\s)#[\p{L}\p{N}_]+/u', $content) > $limits['hashtags']) {
            $problems[] = "{$label} allows up to {$limits['hashtags']} hashtags.";
        }
        if (isset($limits['mentions']) && preg_match_all('/(^|\s)@[\w.]+/u', $content) > $limits['mentions']) {
            $problems[] = "{$label} allows up to {$limits['mentions']} @mentions.";
        }
        if (! empty($limits['no_angle_brackets']) && (str_contains($content, '<') || str_contains($content, '>') || str_contains((string) $title, '<') || str_contains((string) $title, '>'))) {
            $problems[] = "{$label} doesn't allow < or > in the title or description.";
        }

        return $problems;
    }
}
