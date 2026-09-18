<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Messaging\MessageChannel;
use App\Models\SocialAccount;
use App\Services\ApiService;
use App\Services\MessagingServices\DiscordMessagingService;
use App\Services\MessagingServices\GoogleChatMessagingService;
use App\Services\MessagingServices\InstagramMessengerService;
use App\Services\MessagingServices\MatrixMessagingService;
use App\Services\MessagingServices\SlackMessagingService;
use App\Services\MessagingServices\TeamsMessagingService;
use App\Services\MessagingServices\TelegramMessagingService;
use App\Services\MessagingServices\TiktokMessagingService;
use App\Services\MessagingServices\XMessagingService;
use App\Services\MessagingServices\ZaloMessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
/**
 * Connecting/managing the inbox's message_channels rows - separate from
 * ChatController (which is purely about reading/replying to conversations
 * once a channel already exists).
 */
class MessageChannelController extends Controller
{
    public function index()
    {
        // user_id no longer lives on message_channels - it moved to the
        // parent social_accounts row, so scoping to the current admin's
        // channels has to go through that relation instead of a direct
        // column lookup.
        $channels = MessageChannel::whereHas('socialAccount', fn($q) => $q->where('user_id', Auth::id()))
            ->with('socialAccount')
            ->orderBy('platform')
            ->get();

        return view('admin.chats.channels', compact('channels'));
    }

    /**
     * The Manage Channels quick-modal on admin/chats/dashboard.blade.php
     * appends ?return_to=dashboard to every OAuth link it renders; the
     * full admin/chats/channels.blade.php page's identical links don't,
     * so this stays null there and every callback below keeps its
     * original admin.chats.channels destination unchanged. Stashed in
     * session (not carried through the OAuth round-trip as a query param)
     * since Instagram/Facebook/X/TikTok all control their own
     * redirect_uri construction and none of them promise to echo back an
     * arbitrary extra query param.
     */
    private function rememberReturnTo(Request $request): void
    {
        session(['messaging_return_to' => $request->query('return_to') === 'dashboard' ? 'dashboard' : null]);
    }

    private function returnRoute(): string
    {
        return session()->pull('messaging_return_to') === 'dashboard' ? 'admin.chats.dashboard' : 'admin.chats.channels';
    }

    /**
     * Native Instagram Login (instagram.com/oauth/authorize) - the
     * Instagram professional account signs in directly, no Facebook Page
     * or Business Manager involved. Separate session state key from
     * messaging_oauth_state_meta so connecting Facebook and Instagram in
     * two tabs at once doesn't clobber each other's pending state.
     * See InstagramMessagingTrait::redirect()/handleInstagramCallback().
     */
    public function redirectInstagram(Request $request, InstagramMessengerService $service)
    {
        $state = Str::uuid()->toString();
        session(['messaging_oauth_state_instagram' => $state]);
        $this->rememberReturnTo($request);

        return $service->redirect($state);
    }

    public function callbackInstagram(Request $request, InstagramMessengerService $service)
    {
        if (!$request->filled('code') || $request->query('state') !== session('messaging_oauth_state_instagram')) {
            Log::info('Instagram messaging OAuth callback failed or was cancelled.', $request->only(['error', 'error_reason', 'error_description']));

            return redirect()->route($this->returnRoute())->with('error', 'Instagram connection failed or was cancelled.');
        }

        session()->forget('messaging_oauth_state_instagram');

        $result = $service->handleInstagramCallback($request->query('code'));

        return redirect()->route($this->returnRoute())->with(
            $result['success'] ? 'success' : 'error',
            $result['success']
                ? "Connected {$result['data']['instagram']} Instagram account(s)."
                : ($result['error'] ?? 'Instagram connection failed.')
        );
    }

    public function redirectX(Request $request, XMessagingService $service)
    {
        $state = Str::uuid()->toString();
        session(['messaging_oauth_state' => $state]);
        $this->rememberReturnTo($request);

        return $service->redirect($state);
    }

    public function callbackX(Request $request, XMessagingService $service)
    {
        if (!$request->filled('code') || $request->query('state') !== session('messaging_oauth_state')) {
            return redirect()->route($this->returnRoute())->with('error', 'X connection failed or was cancelled.');
        }

        $result = $service->handleCallback($request->query('code'));

        return redirect()->route($this->returnRoute())->with(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? 'X account connected.' : ($result['error'] ?? 'X connection failed.')
        );
    }

    /**
     * Own session state key (rather than the shared 'messaging_oauth_state'
     * X/Discord reuse) so connecting TikTok doesn't clobber a concurrently
     * in-progress connect of one of those - same reasoning as Instagram's
     * dedicated key above.
     */
    public function redirectTiktok(Request $request, TiktokMessagingService $service)
    {
        $state = Str::uuid()->toString();
        session(['messaging_oauth_state_tiktok' => $state]);
        $this->rememberReturnTo($request);

        return $service->redirect($state);
    }

    public function callbackTiktok(Request $request, TiktokMessagingService $service)
    {
        // Route::get() also matches HEAD (confirmed: route:list shows
        // "GET|HEAD" for this URI) - Laravel runs the FULL controller for
        // a HEAD request and only strips the response body afterward, so
        // without this guard a HEAD probe against this URL (a browser/
        // security-scanner link preview, a corporate proxy's "safe
        // browsing" pre-check, etc.) would silently burn the single-use
        // auth_code before the real GET from the actual redirect ever
        // arrives - which reads as "Authorization code is expired" on
        // literally every attempt, since the real request always loses
        // that race. HEAD must be side-effect-free by definition; this
        // makes it actually be that instead of accidentally running
        // handleCallback().
        if ($request->isMethod('head')) {
            return response('', 200);
        }

        Log::info('TikTok messaging callback hit.', [
            'method' => $request->method(),
            'has_code' => $request->filled('code') || $request->filled('auth_code'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if (!$request->filled('code') && !$request->filled('auth_code')) {
            return redirect()->route($this->returnRoute())->with('error', 'TikTok connection failed or was cancelled.');
        }

        if ($request->query('state') !== session('messaging_oauth_state_tiktok')) {
            return redirect()->route($this->returnRoute())->with('error', 'TikTok connection failed - state mismatch.');
        }

        session()->forget('messaging_oauth_state_tiktok');

        $result = $service->handleCallback($request->query('code') ?: $request->query('auth_code'));

        return redirect()->route($this->returnRoute())->with(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? 'TikTok Business Account connected.' : ($result['error'] ?? 'TikTok connection failed.')
        );
    }

    /**
     * No OAuth for Telegram - a bot token from @BotFather is the entire
     * credential. Verified live via getMe before saving, then the
     * per-bot webhook is registered immediately so it's usable right away.
     */
    public function storeTelegram(Request $request, TelegramMessagingService $service, ApiService $apiService)
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'bot_token' => ['required', 'string'],
        ]);

        $base = rtrim(adminSetting('messaging.telegram.api_base'), '/');
        $check = $apiService->get("{$base}/bot{$validated['bot_token']}/getMe");

        if (!$check['success'] || empty($check['data']['ok'])) {
            return back()->withErrors(['bot_token' => 'Could not verify this bot token with Telegram.']);
        }

        $bot = $check['data']['result'];

        $account = SocialAccount::updateOrCreate(
            ['platform' => 'telegram', 'platform_account_id' => (string) $bot['id'], 'user_id' => Auth::id()],
            [
                'name'                     => $validated['name'],
                'username'                 => $bot['username'] ?? null,
                'access_token'             => $validated['bot_token'],
                'is_token_valid'           => true,
                'has_messaging_permission' => true,
            ]
        );

        $channel = MessageChannel::updateOrCreate(
            ['platform' => 'telegram', 'external_id' => (string) $bot['id']],
            [
                'social_account_id' => $account->id,
                'verify_token'      => Str::random(40),
            ]
        );

        $webhookUrl = route('messaging.webhook.telegram.receive', ['channel' => $channel->id]);
        $result = $service->registerWebhook($channel, $webhookUrl);

        if (!$result['success']) {
            return back()->withErrors(['bot_token' => $result['error']]);
        }

        return redirect()->route('admin.chats.channels')->with('success', "Telegram bot @{$bot['username']} connected.");
    }

    /**
     * WhatsApp Cloud API numbers are provisioned through Meta's Embedded
     * Signup JS SDK or a permanent System User token from Business
     * Settings - not a plain OAuth redirect - so this is a verified
     * manual entry rather than a connect button, which matches how
     * WhatsApp is actually set up even in production integrations.
     */
    public function storeWhatsApp(Request $request, ApiService $apiService)
    {
        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'phone_number_id'  => ['required', 'string'],
            'access_token'     => ['required', 'string'],
        ]);

        $version = adminSetting('messaging.meta.graph_version') ?: 'v21.0';
        $check = $apiService->get(
            "https://graph.facebook.com/{$version}/{$validated['phone_number_id']}",
            ['Authorization' => "Bearer {$validated['access_token']}"],
            ['fields' => 'display_phone_number,verified_name']
        );

        if (!$check['success']) {
            return back()->withErrors(['phone_number_id' => 'Could not verify this phone number ID/token with Meta.']);
        }

        $account = SocialAccount::updateOrCreate(
            ['platform' => 'whatsapp', 'platform_account_id' => $validated['phone_number_id'], 'user_id' => Auth::id()],
            [
                'name'                     => $validated['name'],
                'username'                 => $check['data']['display_phone_number'] ?? null,
                'access_token'             => $validated['access_token'],
                'is_token_valid'           => true,
                'has_messaging_permission' => true,
            ]
        );

        MessageChannel::updateOrCreate(
            ['platform' => 'whatsapp', 'external_id' => $validated['phone_number_id']],
            ['social_account_id' => $account->id]
        );

        return redirect()->route('admin.chats.channels')->with('success', 'WhatsApp number connected.');
    }

    /**
     * No OAuth for LINE either - a Channel Access Token + Channel Secret
     * from the LINE Developers Console (Messaging API channel settings)
     * is the entire credential, verified live via the bot info endpoint.
     * Unlike Telegram there's no setWebhook API call to make - the
     * webhook URL has to be pasted into the LINE Developers Console by
     * hand, so this just surfaces the URL to copy after connecting.
     */
    public function storeLine(Request $request, ApiService $apiService)
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'channel_secret' => ['required', 'string'],
            'access_token'   => ['required', 'string'],
        ]);

        $baseUrl = adminSetting('messaging.line.base_url') ?: 'https://api.line.me/v2/bot/';

        $check = $apiService->get($baseUrl . 'info', ['Authorization' => 'Bearer ' . $validated['access_token']]);

        if (!$check['success']) {
            return back()->withErrors(['access_token' => 'Could not verify this Channel Access Token with LINE.']);
        }

        $bot = $check['data'];

        $account = SocialAccount::updateOrCreate(
            ['platform' => 'line', 'platform_account_id' => $bot['userId'], 'user_id' => Auth::id()],
            [
                'name'                     => $validated['name'],
                'username'                 => $bot['displayName'] ?? null,
                'avatar_url'               => $bot['pictureUrl'] ?? null,
                'access_token'             => $validated['access_token'],
                'is_token_valid'           => true,
                'has_messaging_permission' => true,
            ]
        );

        $channel = MessageChannel::updateOrCreate(
            ['platform' => 'line', 'external_id' => $bot['userId']],
            [
                'social_account_id' => $account->id,
                'verify_token'      => $validated['channel_secret'],
            ]
        );

        $webhookUrl = route('messaging.webhook.line.receive', ['channel' => $channel->id]);

        return redirect()->route('admin.chats.channels')->with(
            'success',
            "LINE channel connected. Paste this webhook URL into your LINE Developers Console (Messaging API tab): {$webhookUrl}"
        );
    }

    /**
     * Zalo needs both an OAuth handshake (for the access/refresh token)
     * *and* a manually-copied "OA Secret Key" from the Zalo Developers
     * Console (needed only for webhook signature verification - it isn't
     * part of the OAuth response at all, see ZaloMessagingService's
     * docblock). Rather than adding a whole second "finish setup" screen
     * after the OAuth round-trip, this collects the secret key and
     * display name up front and stashes them in the session, so the
     * callback has everything it needs to create the channel in one shot.
     */
    public function redirectZalo(Request $request, ZaloMessagingService $service)
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'oa_secret_key' => ['required', 'string'],
        ]);

        $state = Str::uuid()->toString();

        session([
            'messaging_oauth_state' => $state,
            'zalo_pending_name'     => $validated['name'],
            'zalo_pending_secret'   => $validated['oa_secret_key'],
        ]);

        return $service->redirect($state);
    }

    public function callbackZalo(Request $request, ZaloMessagingService $service)
    {
        if (!$request->filled('code') || $request->query('state') !== session('messaging_oauth_state')) {
            return redirect()->route('admin.chats.channels')->with('error', 'Zalo connection failed or was cancelled.');
        }

        $result = $service->handleCallback($request->query('code'));

        if (!$result['success']) {
            return redirect()->route('admin.chats.channels')->with('error', $result['error'] ?? 'Zalo connection failed.');
        }

        $oa = $result['oa'];
        $token = $result['token'];

        $account = SocialAccount::updateOrCreate(
            ['platform' => 'zalo', 'platform_account_id' => $oa['oa_id'], 'user_id' => Auth::id()],
            [
                'name'                     => session('zalo_pending_name') ?: ($oa['name'] ?? 'Zalo OA'),
                'username'                 => $oa['name'] ?? null,
                'avatar_url'               => $oa['avatar'] ?? null,
                'access_token'             => $token['access_token'],
                'refresh_token'            => $token['refresh_token'] ?? null,
                'expires_at'               => now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
                'is_token_valid'           => true,
                'has_messaging_permission' => true,
            ]
        );

        MessageChannel::updateOrCreate(
            ['platform' => 'zalo', 'external_id' => $oa['oa_id']],
            [
                'social_account_id' => $account->id,
                // oa_secret_key stashed pre-redirect - stays on MessageChannel,
                // it's the webhook signature secret, not an identity/token field.
                'verify_token' => session('zalo_pending_secret'),
            ]
        );

        session()->forget(['zalo_pending_name', 'zalo_pending_secret']);

        return redirect()->route('admin.chats.channels')->with('success', 'Zalo Official Account connected.');
    }

    /**
     * Slack's OAuth v2 install flow needs nothing pre-collected the way
     * Zalo's redirectZalo() does - the signing secret it eventually needs
     * for webhook verification lives at the app level (one admin setting,
     * shared across every workspace that installs the app), not per
     * installation, so there's no secret to stash in the session first.
     */
    public function redirectSlack(SlackMessagingService $service)
    {
        $state = Str::uuid()->toString();
        session(['messaging_oauth_state' => $state]);

        return $service->redirect($state);
    }

    public function callbackSlack(Request $request, SlackMessagingService $service)
    {
        if (!$request->filled('code') || $request->query('state') !== session('messaging_oauth_state')) {
            return redirect()->route('admin.chats.channels')->with('error', 'Slack connection failed or was cancelled.');
        }

        $result = $service->handleCallback($request->query('code'));

        return redirect()->route('admin.chats.channels')->with(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? "Slack workspace \"{$result['data']->name}\" connected." : ($result['error'] ?? 'Slack connection failed.')
        );
    }

    /**
     * No OAuth here either - a Microsoft Teams bot is registered once as
     * an Azure Bot resource, which hands you a Microsoft App ID and App
     * Password up front, the same static-credential shape as Telegram's
     * bot token. Verified live by attempting to mint a Connector API
     * access token (there's no simpler "who am I" call in the Bot
     * Framework - see TeamsMessagingService::verifyCredentials()). Like
     * LINE, there's no API to register the webhook URL - it has to be
     * pasted by hand into the Azure Bot resource's "Messaging endpoint"
     * field, so this surfaces that URL after connecting.
     */
    public function storeTeams(Request $request, TeamsMessagingService $service)
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'app_id'       => ['required', 'string'],
            'app_password' => ['required', 'string'],
        ]);

        $check = $service->verifyCredentials($validated['app_id'], $validated['app_password']);

        if (!$check['success']) {
            return back()->withErrors(['app_password' => 'Could not authenticate with this Microsoft App ID/Password against the Bot Framework.']);
        }

        $account = SocialAccount::updateOrCreate(
            ['platform' => 'teams', 'platform_account_id' => $validated['app_id'], 'user_id' => Auth::id()],
            [
                'name'                     => $validated['name'],
                'access_token'             => $validated['app_password'],
                'is_token_valid'           => true,
                'has_messaging_permission' => true,
            ]
        );

        $channel = MessageChannel::updateOrCreate(
            ['platform' => 'teams', 'external_id' => $validated['app_id']],
            ['social_account_id' => $account->id]
        );

        $webhookUrl = route('messaging.webhook.teams.receive', ['channel' => $channel->id]);

        return redirect()->route('admin.chats.channels')->with(
            'success',
            "Teams bot connected. Paste this URL into the Azure Bot resource's Messaging endpoint field: {$webhookUrl}"
        );
    }

    /**
     * A Google Chat app's credential is a service account key file, not a
     * single token - rather than asking for client_email/private_key as
     * two separate fields (error-prone for a multi-line PEM pasted into a
     * plain input), this takes the whole downloaded JSON key file as one
     * paste and extracts what it needs. The Cloud project *number* isn't
     * part of that JSON file at all (see GoogleChatMessagingService's
     * docblock for why it's needed - verifying inbound requests), so this
     * tries to auto-detect it via detectProjectNumber() first - manually
     * typing it in is exactly what previously broke a real customer's
     * inbound messages (a pasted project *id* where the numeric project
     * *number* belonged). The field is only asked for as a fallback when
     * auto-detection genuinely can't resolve it.
     */
    public function storeGoogleChat(Request $request, GoogleChatMessagingService $service)
    {
        $validated = $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'service_account_json' => ['required', 'string'],
            'project_number'       => ['nullable', 'string'],
        ]);

        $key = json_decode($validated['service_account_json'], true);

        if (!$key || empty($key['client_email']) || empty($key['private_key'])) {
            return back()->withErrors(['service_account_json' => 'That does not look like a valid service account JSON key file - it must contain client_email and private_key.']);
        }

        $check = $service->verifyCredentials($key['client_email'], $key['private_key']);

        if (!$check['success']) {
            return back()->withErrors(['service_account_json' => 'Could not authenticate with this service account against Google.']);
        }

        $projectNumber = $service->detectProjectNumber($key['client_email'], $key['private_key'], $key['project_id'] ?? '')
            ?? $validated['project_number']
            ?? null;

        if (!$projectNumber) {
            return back()->withErrors(['project_number' => 'Could not automatically detect this project\'s number - please enter it manually. Find it in Google Cloud Console under "Project info" on your project\'s dashboard.']);
        }

        $account = SocialAccount::updateOrCreate(
            // Scoped by user_id too, matching the (user_id, platform,
            // platform_account_id) identity this now lives under - omitting
            // it meant a second Socialeaz user connecting the same service
            // account would find and silently reassign the first user's
            // existing account row instead of getting their own, since the
            // lookup ignored who's currently authenticated. message_channels
            // itself no longer has a user_id column at all (dropped in
            // 2026_08_26_100005_drop_legacy_account_columns_and_tables), so
            // this scoping has to live on the SocialAccount lookup now.
            ['platform' => 'google_chat', 'platform_account_id' => $key['client_email'], 'user_id' => Auth::id()],
            [
                'name'                     => $validated['name'],
                'access_token'             => $key['private_key'],
                'is_token_valid'           => true,
                'has_messaging_permission' => true,
            ]
        );

        $channel = MessageChannel::updateOrCreate(
            ['platform' => 'google_chat', 'external_id' => $key['client_email']],
            [
                'social_account_id' => $account->id,
                'meta'              => ['project_number' => $projectNumber, 'project_id' => $key['project_id'] ?? null],
            ]
        );

        $webhookUrl = route('messaging.webhook.google_chat.receive', ['channel' => $channel->id]);

        // Recorded, not just displayed: whenever the Chat app's
        // Authentication Audience is the endpoint URL (and it always is,
        // once the app is a Workspace add-on), Google echoes this exact
        // string back as the inbound token's `aud` claim, so
        // GoogleChatMessagingService::verifyRequestToken() has to compare
        // against the URL the admin was actually told to paste - not one
        // recomputed later from an APP_URL that may since have changed.
        $channel->update(['meta' => array_merge($channel->meta ?? [], ['endpoint_url' => $webhookUrl])]);

        return redirect()->route('admin.chats.channels')->with(
            'success',
            "Google Chat app connected. Paste this URL as the App URL in Google Cloud Console > Google Chat API > Configuration: {$webhookUrl}"
        );
    }

    /**
     * Separate connection path from storeGoogleChat() above - user
     * authentication, not app authentication. Lets an admin authorize
     * Socialeaz to act as their own Google account (post/read in Chat
     * spaces they already belong to) with a normal consent-screen
     * redirect, no service account JSON needed. Does not receive
     * customer DMs - see GoogleChatMessagingService::redirectUserAuth()'s
     * docblock for why that's a hard Google API limitation, not a choice
     * made here.
     */
    public function redirectGoogleChatOAuth(GoogleChatMessagingService $service)
    {
        $state = Str::uuid()->toString();
        session(['messaging_oauth_state' => $state]);

        return $service->redirectUserAuth($state);
    }

    public function callbackGoogleChatOAuth(Request $request, GoogleChatMessagingService $service)
    {
        if (!$request->filled('code') || $request->query('state') !== session('messaging_oauth_state')) {
            return redirect()->route('admin.chats.channels')->with('error', 'Google Chat connection failed or was cancelled.');
        }

        $result = $service->handleUserOAuthCallback($request->query('code'), Auth::id());

        if (!$result['success']) {
            return redirect()->route('admin.chats.channels')->with('error', $result['error'] ?? 'Google Chat connection failed.');
        }

        return redirect()->route('admin.chats.channels')->with(
            $result['created'] > 0 ? 'success' : 'error',
            $result['created'] > 0
                ? "Connected {$result['created']} Google Chat space(s) as {$result['email']}."
                : 'Connected to Google, but no Chat spaces were found for this account.'
        );
    }

    /**
     * A Matrix account's credential is a homeserver URL + access token
     * pair, not a single token - the homeserver is chosen by whoever's
     * connecting (matrix.org, another public server, or something
     * self-hosted), so unlike every fixed-provider platform here it has
     * to be collected too, not just verified against. Verified live via
     * account/whoami. Like Discord, there's no webhook to register a URL
     * for - the success message below points to the same kind of
     * always-running listener process Discord needs.
     */
    public function storeMatrix(Request $request, MatrixMessagingService $service)
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'homeserver_url' => ['required', 'string'],
            'access_token'   => ['required', 'string'],
        ]);

        $check = $service->verifyCredentials($validated['homeserver_url'], $validated['access_token']);

        if (!$check['success']) {
            return back()->withErrors(['access_token' => 'Could not verify this access token against that homeserver.']);
        }

        $account = SocialAccount::updateOrCreate(
            ['platform' => 'matrix', 'platform_account_id' => $check['user_id'], 'user_id' => Auth::id()],
            [
                'name'                     => $validated['name'],
                'username'                 => $check['user_id'],
                'access_token'             => $validated['access_token'],
                'is_token_valid'           => true,
                'has_messaging_permission' => true,
            ]
        );

        $channel = MessageChannel::updateOrCreate(
            ['platform' => 'matrix', 'external_id' => $check['user_id']],
            [
                'social_account_id' => $account->id,
                'meta'              => ['homeserver_url' => rtrim($validated['homeserver_url'], '/')],
            ]
        );

        return redirect()->route('admin.chats.channels')->with(
            'success',
            "Matrix account {$check['user_id']} connected. Start receiving messages by running: php artisan messaging:matrix-listen {$channel->id} (under a process supervisor so it stays running)."
        );
    }

    /**
     * Starts the "authorize this bot to a server" OAuth flow - see
     * DiscordMessagingService's class docblock for what this can and
     * can't do (it does not produce the token used to actually send
     * messages).
     */
    public function redirectDiscord(DiscordMessagingService $service)
    {
        $state = Str::uuid()->toString();
        session(['messaging_oauth_state' => $state]);

        return $service->redirect($state);
    }

    /**
     * Registered at GET admin/messaging/channels/discord - the exact
     * redirect_uri configured in the Developer Portal - separate from
     * storeDiscord()'s POST at the same path (the bot-token form), which
     * Laravel routes independently by HTTP method.
     */
    public function callbackDiscord(Request $request, DiscordMessagingService $service)
    {
        if (!$request->filled('code') || $request->query('state') !== session('messaging_oauth_state')) {
            return redirect()->route('admin.chats.channels')->with('error', 'Discord authorization failed or was cancelled.');
        }

        $result = $service->handleOAuthCallback($request->query('code'));

        return redirect()->route('admin.chats.channels')->with(
            $result['success'] ? 'success' : 'error',
            $result['success']
                ? 'Discord bot authorized to server "' . ($result['guild']['name'] ?? 'Unknown') . '".'
                : ($result['error'] ?? 'Discord authorization failed.')
        );
    }

    /**
     * Discord bot tokens are static, generated once in the Developer
     * Portal (Bot tab) - this is the credential actually used to send
     * messages and open the Gateway connection (see
     * DiscordMessagingService's docblock on redirect()/
     * handleOAuthCallback() above for why the OAuth flow doesn't replace
     * this). Verified live via getMe before saving, the same
     * verify-and-save shape as Telegram/LINE. Unlike those two, there's
     * no webhook URL to hand back: this bot has to be started as a
     * Gateway listener daemon (`php artisan messaging:discord-listen
     * {channel}`) before it can receive anything, since Discord has no
     * webhook delivery for bot DMs at all.
     */
    public function storeDiscord(Request $request, DiscordMessagingService $service)
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'bot_token' => ['required', 'string'],
        ]);

        $check = $service->verifyBotToken($validated['bot_token']);

        if (!$check['success']) {
            return back()->withErrors(['bot_token' => 'Could not verify this bot token with Discord.']);
        }

        $bot = $check['bot'];

        $account = SocialAccount::updateOrCreate(
            ['platform' => 'discord', 'platform_account_id' => $bot['id'], 'user_id' => Auth::id()],
            [
                'name'                     => $validated['name'],
                'username'                 => $bot['username'] ?? null,
                'avatar_url'               => !empty($bot['avatar'])
                    ? "https://cdn.discordapp.com/avatars/{$bot['id']}/{$bot['avatar']}.png"
                    : null,
                'access_token'             => $validated['bot_token'],
                'is_token_valid'           => true,
                'has_messaging_permission' => true,
            ]
        );

        // Keyed by external_id = bot_id (Discord's Application ID and bot
        // user ID are the same snowflake) so DiscordMessagingService::
        // handleOAuthCallback() - triggered later by redirectDiscord()/
        // callbackDiscord()'s "authorize to a server" flow - can find this
        // exact row via the client_id it gets back from Discord, and attach
        // guild/webhook info to it without ever touching the real bot token
        // stored on $account above.
        $channel = MessageChannel::updateOrCreate(
            ['platform' => 'discord', 'external_id' => $bot['id']],
            ['social_account_id' => $account->id]
        );

        return redirect()->route('admin.chats.channels')->with(
            'success',
            "Discord bot @{$bot['username']} connected. Start receiving DMs by running: php artisan messaging:discord-listen {$channel->id} (under a process supervisor so it stays running)."
        );
    }

    public function destroy(MessageChannel $channel)
    {
        abort_unless($channel->socialAccount->user_id === Auth::id(), 403);

        $channel->delete();

        return redirect()->route('admin.chats.channels')->with('success', 'Channel disconnected.');
    }
}
