<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Messaging\MessageChannel;
use App\Services\ApiService;
use App\Services\MessagingServices\FacebookMessengerService;
use App\Services\MessagingServices\TelegramMessagingService;
use App\Services\MessagingServices\XMessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $channels = MessageChannel::where('user_id', Auth::id())->orderBy('platform')->get();

        return view('admin.chats.channels', compact('channels'));
    }

    /**
     * One Facebook Login flow connects both Messenger and Instagram Direct
     * - see MetaMessagingTrait::redirect()/handleMetaCallback().
     */
    public function redirectMeta(FacebookMessengerService $service)
    {
        $state = Str::uuid()->toString();
        session(['messaging_oauth_state' => $state]);

        return $service->redirect($state);
    }

    public function callbackMeta(Request $request, FacebookMessengerService $service)
    {
        if (!$request->filled('code') || $request->query('state') !== session('messaging_oauth_state')) {
            return redirect()->route('admin.chats.channels')->with('error', 'Meta connection failed or was cancelled.');
        }

        $result = $service->handleMetaCallback($request->query('code'));

        return redirect()->route('admin.chats.channels')->with(
            $result['success'] ? 'success' : 'error',
            $result['success']
                ? "Connected {$result['data']['facebook']} Page(s) and {$result['data']['instagram']} Instagram account(s)."
                : ($result['error'] ?? 'Meta connection failed.')
        );
    }

    public function redirectX(XMessagingService $service)
    {
        $state = Str::uuid()->toString();
        session(['messaging_oauth_state' => $state]);

        return $service->redirect($state);
    }

    public function callbackX(Request $request, XMessagingService $service)
    {
        if (!$request->filled('code') || $request->query('state') !== session('messaging_oauth_state')) {
            return redirect()->route('admin.chats.channels')->with('error', 'X connection failed or was cancelled.');
        }

        $result = $service->handleCallback($request->query('code'));

        return redirect()->route('admin.chats.channels')->with(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? 'X account connected.' : ($result['error'] ?? 'X connection failed.')
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

        $channel = MessageChannel::updateOrCreate(
            ['platform' => 'telegram', 'external_id' => (string) $bot['id']],
            [
                'user_id'      => Auth::id(),
                'name'         => $validated['name'],
                'username'     => $bot['username'] ?? null,
                'access_token' => $validated['bot_token'],
                'verify_token' => Str::random(40),
                'status'       => true,
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

        MessageChannel::updateOrCreate(
            ['platform' => 'whatsapp', 'external_id' => $validated['phone_number_id']],
            [
                'user_id'      => Auth::id(),
                'name'         => $validated['name'],
                'username'     => $check['data']['display_phone_number'] ?? null,
                'access_token' => $validated['access_token'],
                'status'       => true,
            ]
        );

        return redirect()->route('admin.chats.channels')->with('success', 'WhatsApp number connected.');
    }

    public function destroy(MessageChannel $channel)
    {
        abort_unless($channel->user_id === Auth::id(), 403);

        $channel->delete();

        return redirect()->route('admin.chats.channels')->with('success', 'Channel disconnected.');
    }
}
