<?php

namespace App\Http\Controllers\Api\Comments;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Services\PostServices\YoutubePostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * YouTube's PubSubHubbub (WebSub) callback - the only real-time push
 * mechanism YouTube's public API offers, and it only ever announces
 * new/updated video publishes on a subscribed channel's upload feed.
 * There is no push notification for comments, likes, or shares on
 * YouTube - those can only be pulled via the Data API, which is why
 * receive() re-runs YoutubePostService::backfillOneVideo() (the same
 * per-video fetch used by the connect-time batch backfill) for whichever
 * video the hub just announced, rather than trying to parse engagement
 * data out of the notification itself (the Atom payload carries none).
 *
 * Renewal: Google's hub leases expire after ~5 days
 * (see YoutubePostService::subscribeToWebhooks()) and must be renewed
 * before then or delivery silently stops. Add a scheduled command that
 * calls subscribeToWebhooks() for every youtube PostAccount periodically
 * (eg. daily) - none exists yet, this controller only handles delivery.
 */
class YoutubeWebhookController extends Controller
{
    public function __construct(protected YoutubePostService $youtubeService)
    {
    }

    /**
     * The hub calls back with a GET to confirm every subscribe/unsubscribe
     * request (see hub.verify=async in subscribeToWebhooks()) - echo back
     * hub.challenge verbatim to confirm, but only for a topic that
     * actually matches one of our connected channels, so an arbitrary
     * third party can't get an unrelated topic confirmed against this
     * callback.
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $topic = (string) $request->query('hub_topic');
        $challenge = $request->query('hub_challenge');

        if (!in_array($mode, ['subscribe', 'unsubscribe'], true) || !$challenge) {
            return response('Forbidden', 403);
        }

        $channelId = $this->extractChannelId($topic);

        if (!$channelId || !SocialAccount::where('platform', 'youtube')->where('platform_account_id', $channelId)->exists()) {
            Log::warning('YouTube WebSub verify for unrecognized topic.', ['topic' => $topic]);
            return response('Forbidden', 403);
        }

        return response((string) $challenge, 200);
    }

    /**
     * Every subsequent notification: an Atom feed with one <entry> per
     * new/updated video. Must ack fast and 200 regardless of what's
     * inside - the hub only cares that delivery succeeded, and treats
     * repeated failures as cause to eventually stop trying.
     */
    public function receive(Request $request)
    {
        if (!$this->verifySignature($request)) {
            Log::warning('YouTube WebSub signature mismatch', ['ip' => $request->ip()]);

            return response('Forbidden', 403);
        }

        $xml = @simplexml_load_string($request->getContent());

        if ($xml === false) {
            return response('', 200);
        }

        $xml->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
        $xml->registerXPathNamespace('yt', 'http://www.youtube.com/xml/schemas/2015');

        foreach ($xml->xpath('//atom:entry') ?: [] as $entry) {
            $yt = $entry->children('yt', true);
            $videoId = (string) $yt->videoId;
            $channelId = (string) $yt->channelId;

            if (!$videoId || !$channelId) {
                continue;
            }

            $account = SocialAccount::where('platform', 'youtube')->where('platform_account_id', $channelId)->first();

            if (!$account) {
                continue;
            }

            try {
                $this->youtubeService->backfillOneVideo($account, $videoId);
            } catch (\Throwable $e) {
                Log::warning('Failed to backfill a YouTube video from WebSub notification.', [
                    'account_id' => $account->id,
                    'video_id'   => $videoId,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return response('', 200);
    }

    /**
     * Google's hub signs the POST body with the hub.secret given at
     * subscribe time, via X-Hub-Signature: "{algo}={hex digest}" (sha1 by
     * the spec's default, but the algorithm is whatever the hub actually
     * used, hence reading it from the header rather than assuming sha1).
     */
    protected function verifySignature(Request $request): bool
    {
        $header = $request->header('X-Hub-Signature', '');

        if (!str_contains($header, '=')) {
            return false;
        }

        [$algo, $signature] = explode('=', $header, 2);

        if (!in_array($algo, hash_algos(), true)) {
            return false;
        }

        $expected = hash_hmac($algo, $request->getContent(), adminSetting('posts.youtube.webhook_secret', 'socialeaz-youtube-hub'));

        return hash_equals($expected, $signature);
    }

    /**
     * hub.topic is the full feed URL
     * (https://www.youtube.com/xml/feeds/videos.xml?channel_id=XXXX) -
     * pull just the channel_id back out of it.
     */
    protected function extractChannelId(string $topic): ?string
    {
        $query = parse_url($topic, PHP_URL_QUERY);

        if (!$query) {
            return null;
        }

        parse_str($query, $params);

        return $params['channel_id'] ?? null;
    }
}
