<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AdAccount;
use App\Services\RedirectAdAuth\SocialAuthManager;

class AdController extends Controller
{
    public function dashboard()
    {
        return view('admin.ads.dashboard');
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
    public function redirect(
        string $platform,
        SocialAuthManager $manager
    ) {
    
        return $manager->redirect($platform);
    
    }

    public function callback(
        string $platform,
        SocialAuthManager $manager
    ) {
    
        return $manager->callback($platform);
    
    }

    public function redirects($platform)
    {
        session()->forget([
            'platform'
        ]);
        if ($platform == 'youtube') {
            $platform = 'google';
        }

        //$mediaAccount = $this->mediaAccountModel->where('platform', $platform)->where('company_id', company()->id())->first();
        $clientId = core()->superConfig("admin.twsaa.$platform.ads.app_id");
        $clientSecret = core()->superConfig("admin.twsaa.$platform.ads.app_secret");


        $domain = env('APP_DOMAIN');

        if (!isset($domain)) {
            $domain = 'beaelge.com';
        }
        $redirectUri = 'https://'.$domain.'/admin/social/auth/' . $platform . '/callback';
        $previousUrl = URL::previous();
        Session::put('previous_url', $previousUrl);
        $state = Str::random(32);
        $codeVerifier = Str::random(64);
        session([
            'ad_platform_state' => $state,
            'ad_platform_code_verifier' => $codeVerifier,
            'ad_platform' => $platform,
        ]);
        if ($platform === 'snapchat') {
            $url = 'https://accounts.snapchat.com/login/oauth2/authorize?' . http_build_query([
                'client_id'     => $clientId,
                'redirect_uri'  => $redirectUri,
                'response_type' => 'code',
                'scope'         => 'snapchat-marketing-api snapchat-profile-api',
                'state'         => $state,
            ]);
            
            return Redirect::to($url);
        } else if ($platform === 'tiktok') {
            $tiktokAuthUrl = "https://business-api.tiktok.com/portal/auth?" . http_build_query([
                'app_id' => $clientId,
                'state' => $state,
                'grant_type' => 'authorization_code',
                'scope' => 'refresh_token',
                'redirect_uri' => $redirectUri,
            ]);

            return redirect()->away($tiktokAuthUrl);
        } else if (in_array($platform, ['facebook', 'instagram'])) {
            if ($platform == 'instagram') {
                Session::put('platform', $platform);
            }
            $redirectUri = 'https://'.$domain.'/admin/social/auth/facebook/callback'; //'https://'.env('APP_DOMAIN').'/admin/social/auth/facebook/callback';
            return redirect("https://www.facebook.com/v25.0/dialog/oauth?client_id={$clientId}&redirect_uri={$redirectUri}&state={$state}&code_verifier={$codeVerifier}&scope=ads_management,ads_read");
        } else if ($platform === 'google' || $platform === 'youtube') { 
            $response = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                'client_id' => $clientId,
                'access_type' => 'offline',
                'state' => $state,
                'redirect_uri' => $redirectUri,
                //'scope' => 'https://www.googleapis.com/auth/adwords',
                'scope' => implode(' ', [
                    'https://www.googleapis.com/auth/adwords',
                    // 'https://www.googleapis.com/auth/business.manage',
                    // 'https://www.googleapis.com/auth/youtube.upload',
                    // 'openid',
                    // 'email',s
                    // 'profile',
                    // 'https://www.googleapis.com/auth/userinfo.email',
                    // 'https://www.googleapis.com/auth/userinfo.profile'
                ]),
                'response_type' => 'code',
                'prompt' => 'consent'
            ]);

            return redirect()->away($response);
            
        } else if ($platform === 'x') {
            $oauthTimestamp = time();

            // Step 1: Build initial OAuth parameters
            $oauthParams = [
                'oauth_callback' => $redirectUri,
                'oauth_consumer_key' => $clientId,
                'oauth_nonce' => $state,
                'oauth_signature_method' => 'HMAC-SHA1',
                'oauth_timestamp' => $oauthTimestamp,
                'oauth_version' => '1.0',
            ];
     
            // Step 2: Create base string for signature
            $baseString = $this->buildBaseString(
                'https://api.x.com/oauth/request_token',
                'POST',
                $oauthParams
            );

            // Step 3: Create composite key
            $compositeKey = rawurlencode($clientSecret) . '&';

            // Step 4: Generate signature
            $oauthSignature = base64_encode(hash_hmac('sha1', $baseString, $compositeKey, true));
            $oauthParams['oauth_signature'] = $oauthSignature;

            // Step 5: Build Authorization header
            $authHeader = 'OAuth ' . collect($oauthParams)->map(function ($value, $key) {
                return rawurlencode($key) . '="' . rawurlencode($value) . '"';
            })->implode(', ');

            // Step 6: Make request to get request token
            $response = Http::withHeaders([
                'Authorization' => $authHeader,
                // 'Content-Type' => 'application/x-www-form-urlencoded',
            ])->post('https://api.x.com/oauth/request_token');

            if ($response->failed()) {
                return response()->json(['error' => 'Failed to get request token', 'details' => $response->json()['errors'][0]['message'] ?? 'X Authorization error'], 401);
            }

            parse_str($response->body(), $tokens);

            if (!isset($tokens['oauth_token'])) {
                return response()->json(['error' => 'Missing oauth_token', 'raw' => $tokens], 400);
            }

            session(['oauth_token_secret' => $tokens['oauth_token_secret'], 'x_state' => $state]);

            return redirect('https://api.x.com/oauth/authorize?oauth_token=' . $tokens['oauth_token']);
        }
    }
}
