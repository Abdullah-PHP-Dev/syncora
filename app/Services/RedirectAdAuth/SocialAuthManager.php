<?php

namespace App\Services\RedirectAdAuth;


use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;


class SocialAuthManager
{


    public function redirect(string $platform)
    {


        if ($platform === 'youtube') {
            $platform = 'google';
        }


        $allowed = [
            'facebook',
            'instagram',
            'google',
            'tiktok',
            'snapchat',
            'x'
        ];


        abort_unless(
            in_array($platform, $allowed),
            404
        );



        $state = Str::uuid()->toString();
        $codeVerifier = Str::random(64);

        Session::put([
            'oauth_platform' => $platform,
            'oauth_state' => $state,
            'previous_url' => URL::previous()
        ]);



        return match ($platform) {

            'facebook',
            'instagram'
            => app(FacebookOAuth::class)
                ->redirect($platform, $state, $codeVerifier),


            'google'
            => app(GoogleOAuth::class)
                ->redirect($state),


            'tiktok'
            => app(TikTokOAuth::class)
                ->redirect($state),


            'snapchat'
            => app(SnapchatOAuth::class)
                ->redirect($state),


            'x'
            => app(XOAuth::class)
                ->redirect($state),
        };
    }

    public function callback(string $platform)
    {
        $allowed = [
            'facebook',
            'instagram',
            'google',
            'tiktok',
            'snapchat',
            'x'
        ];


        abort_unless(
            in_array($platform, $allowed),
            404
        );



        $state = Str::uuid()->toString();
        $codeVerifier = Str::random(64);

        Session::put([
            'oauth_platform' => $platform,
            'oauth_state' => $state,
            'previous_url' => URL::previous()
        ]);



        return match ($platform) {

            'facebook',
            'instagram'
            => app(FacebookOAuth::class)
                ->callback($platform, $state, $codeVerifier),


            'google'
            => app(GoogleOAuth::class)
                ->callback($state),


            'tiktok'
            => app(TikTokOAuth::class)
                ->callback($state),


            'snapchat'
            => app(SnapchatOAuth::class)
                ->callback($state),


            'x'
            => app(XOAuth::class)
                ->callback($state),
        };
    }
}
