<?php

namespace App\View\Components;

use App\Models\SocialAccount;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Illuminate\View\View;

class ConnectSocialMedia extends Component
{
    public string $id;

    public array $connectedPlatforms;

    public function __construct(
        ?string $id = null,
        ?array $connectedPlatforms = null,
    ) {
        $this->id = $id ?? 'connect-social-media-'.Str::uuid();
        $this->connectedPlatforms = $connectedPlatforms ?? (auth()->check()
            ? SocialAccount::where('user_id', auth()->id())
                ->where('is_token_valid', true)
                ->distinct()
                ->pluck('platform')
                ->all()
            : []);
    }

    public function render(): View
    {
        return view('components.connect-social-media');
    }
}
