{{--
    "Promote" from Content Publishing (PostsDashboard.vue's post menu):
    shows the post being promoted and pre-fills the campaign's ad text field
    when the form has one ('description' on most platforms, 'message' on X).
    Google/YouTube use multi-line headline lists, so there the text is only
    offered for copying.

    Expects: $promotePost (?Post), $platform.
--}}
@if ($promotePost ?? null)
    @php
        $preview = dash_media_preview($promotePost->media->first());
        $text = (string) $promotePost->content;
    @endphp
    <div class="ads-promote" data-promote-text="{{ $text }}">
        @if ($preview && $preview['kind'] === 'image' && $preview['url'])
            <img src="{{ $preview['url'] }}" class="ads-promote-thumb" alt="">
        @else
            <span class="ads-promote-thumb"><i class="bx {{ $preview && $preview['kind'] === 'video' ? 'bx-play' : 'bx-file-blank' }}"></i></span>
        @endif
        <div class="ads-promote-text">
            <small><i class="bx bxs-megaphone"></i> Promoting your post</small>
            <span>{{ \Illuminate\Support\Str::limit($text ?: '(no caption)', 140) }}</span>
            <em class="ads-promote-status" hidden><i class="bx bx-check"></i> Post text added to the ad - review it before launching.</em>
        </div>
        <div class="ads-promote-actions">
            @if ($text !== '')
                <button type="button" class="ads-acct-btn" data-promote-copy><i class="bx bx-copy"></i> Copy text</button>
            @endif
            <a href="{{ route('admin.posts.preview', ['post' => $promotePost->id, 'platform' => $promotePost->platform]) }}" class="ads-acct-btn"><i class="bx bx-show"></i> View post</a>
        </div>
    </div>
    @once
    @push('styles')
    <style>
        .ads-promote { display: flex; align-items: center; gap: 12px; padding: 12px 16px; margin: -8px 0 16px; background: linear-gradient(180deg, #f8f6ff, #fff); border: 1px solid #e4dcff; border-radius: 14px; }
        .ads-promote-thumb { width: 52px; height: 52px; border-radius: 10px; object-fit: cover; flex-shrink: 0; background: #f1f3f7; display: grid; place-items: center; color: #8a92a3; font-size: 20px; }
        .ads-promote-text { flex: 1; min-width: 0; font-size: .84rem; color: #161b2b; }
        .ads-promote-text small { display: flex; align-items: center; gap: 4px; font-size: .7rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #6d4aff; margin-bottom: 2px; }
        .ads-promote-text span { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ads-promote-status { display: flex; align-items: center; gap: 4px; margin-top: 4px; font-style: normal; font-size: .74rem; color: #079455; font-weight: 600; }
        .ads-promote-actions { display: flex; gap: 6px; flex-shrink: 0; flex-wrap: wrap; }
        @media (max-width: 767.98px) { .ads-promote { flex-wrap: wrap; } }
    </style>
    @endpush
    @push('scripts')
    <script>
        (function () {
            // After load: the Vue #app root re-mounts first.
            window.addEventListener('load', function () {
                setTimeout(function () {
                    const banner = document.querySelector('.ads-promote');
                    const text = banner?.dataset.promoteText || '';
                    const field = document.querySelector('textarea[name="description"], textarea[name="message"]');
                    if (text && field && !field.value.trim()) {
                        field.value = text;
                        field.dispatchEvent(new Event('input', { bubbles: true }));
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                        banner.querySelector('.ads-promote-status').hidden = false;
                    }
                }, 0);
            });
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('[data-promote-copy]');
                if (!btn) return;
                navigator.clipboard?.writeText(btn.closest('.ads-promote').dataset.promoteText).then(function () {
                    btn.innerHTML = '<i class="bx bx-check"></i> Copied';
                });
            });
        })();
    </script>
    @endpush
    @endonce
@endif
