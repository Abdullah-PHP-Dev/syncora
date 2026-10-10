<?php

namespace App\Services\AdServices;

use App\Support\AdAccountSelection;
use Illuminate\Support\Facades\Auth;

/**
 * ChatGPT Ads (OpenAI Ads API, https://developers.openai.com/ads).
 * Connecting is an API key on the Connection Hub (OpenAiDriver), not an
 * OAuth redirect; campaigns are created as campaign > ad group > ad.
 */
class OpenAiAdService
{
    protected $account;

    public function __construct()
    {
        $this->account = Auth::check() ? AdAccountSelection::resolve((int) Auth::id(), 'openai') : null;
    }

    /** No OAuth: send the user to the key form on the Hub's ChatGPT Ads card. */
    public function redirect($platform, $state)
    {
        return redirect()->to(route('admin.connections.index') . '#openai');
    }

    public function callback($platform, $state = null)
    {
        return redirect()->to(route('admin.connections.index') . '#openai');
    }
}
