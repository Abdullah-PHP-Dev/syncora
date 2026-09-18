@extends('layouts.app')

@push('styles')
<style>
    .post-detail-wrapper {
        max-width: 1100px;
        margin: 0 auto;
    }

    /* Card Styling */
    .social-card {
        background: #ffffff;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
        border: 1px solid rgba(0, 0, 0, 0.06);
        overflow: hidden;
    }

    /* Media Handling */
    .media-hero-container {
        position: relative;
        background-color: #0d1117;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .media-hero-element {
        width: 100%;
        max-height: 520px;
        object-fit: contain;
        background-color: #000;
        display: block;
        margin: 0 auto;
    }

    .carousel-control-prev, .carousel-control-next {
        width: 44px;
        height: 44px;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(8px);
        border-radius: 50%;
        top: 50%;
        transform: translateY(-50%);
        opacity: 0.9;
        margin: 0 15px;
        color: #1a1d20;
        border: none;
        transition: all 0.2s ease;
    }
    .carousel-control-prev:hover, .carousel-control-next:hover {
        background: #ffffff;
        transform: translateY(-50%) scale(1.08);
    }

    /* Platform Avatars */
    .platform-avatar {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        color: #fff;
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
    }
    .platform-avatar.instagram { background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%); }
    .platform-avatar.facebook { background: #1877f2; }
    .platform-avatar.twitter, .platform-avatar.x { background: #000; }
    .platform-avatar.youtube { background: #ff0000; }
    .platform-avatar.linkedin { background: #0a66c2; }
    .platform-avatar.tiktok { background: #000000; }

    /* Metric Cards */
    .metric-card {
        background: #f8fafc;
        border: 1px solid #edf2f7;
        border-radius: 14px;
        padding: 14px 18px;
        transition: all 0.2s ease;
    }
    .metric-card:hover {
        background: #ffffff;
        border-color: #cbd5e1;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    }
    .metric-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
    }

    /* Content Typography */
    .post-title {
        font-size: 1.35rem;
        font-weight: 700;
        color: #0f172a;
    }
    .post-body-text {
        font-size: 1.05rem;
        line-height: 1.7;
        color: #334155;
    }

    /* Comments Section */
    .comment-card {
        background: #ffffff;
        border: 1px solid #edf2f7;
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 16px;
        transition: all 0.2s ease;
    }
    .avatar-ring {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #fff;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .nested-reply-box {
        margin-left: 48px;
        padding-left: 16px;
        border-left: 2px solid #e2e8f0;
        margin-top: 14px;
    }
    .reply-item {
        background-color: #f8fafc;
        border: 1px solid #f1f5f9;
        padding: 12px 16px;
        border-radius: 12px;
    }
    .action-link {
        font-size: 0.825rem;
        font-weight: 600;
        color: #64748b;
        text-decoration: none;
        transition: color 0.2s;
    }
    .badge1 {
        background-color:#15803d
    }
    .text-gray {
        color: rgb(245 249 255) !important
    }
    .action-link:hover { color: #2563eb; }
    .action-link.delete-link:hover { color: #ef4444; }

    /* Sentiment Badges */
    .sentiment-badge-positive { background-color: #dcfce7; color: #15803d; }
    .sentiment-badge-negative { background-color: #fee2e2; color: #b91c1c; }
    .sentiment-badge-neutral { background-color: #f1f5f9; color: #475569; }
</style>
@endpush

@section('content')
<div class="container-fluid py-4">
    <div class="post-detail-wrapper">

        {{-- TOP HEADER BAR --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="{{ url()->previous() }}" class="btn btn-sm btn-light rounded-pill px-3 mb-2 border">
                    <i class="fa fa-arrow-left me-1"></i> Back
                </a>
                <h3 class="fw-bold mb-0 text-dark">{{ $post->title ?: 'Post Details' }}</h3>
                <p class="text-muted small mb-0">ID: {{ $post->post_id ?? $post->id }} • Platform: <span class="text-capitalize fw-semibold">{{ $post->platform }}</span></p>
            </div>

            {{-- POST STATUS & APPROVAL BADGES --}}
            <div class="d-flex align-items-center gap-2">
                <span class="badge1 bg-{{ $post->status_badge }}-subtle text-{{ $post->status_badge }} border border-{{ $post->status_badge }}-subtle px-3 py-2 rounded-pill text-capitalize fs-6">
                    <i class="fa fa-circle me-1" style="font-size: 8px;"></i> {{ $post->status }}
                </span>

                @if($post->status == 'completed')
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fs-6"
                          data-bs-toggle="tooltip" 
                          title="Approved by {{ $post->approved_by ?? 'System' }} on {{ $post->updated_at?->format('d M Y') }}">
                        <i class="fa fa-check-double me-1"></i> Approved
                    </span>
                @endif

                @if($post->is_featured)
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 rounded-pill fs-6">
                        <i class="fa fa-star me-1"></i> Featured
                    </span>
                @endif

                @if($post->status == 'failed')
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill fs-6">
                        <i class="fa fa-star me-1"></i> Failed
                    </span>
                @endif
            </div>
        </div>

        {{-- MAIN POST CARD --}}
        <div class="card social-card mb-4">

            {{-- DYNAMIC MEDIA SECTION --}}
            @php
                $mediaItems = $post->media;
                // Fallback to single media_url if no media relations attached
                if ($mediaItems->isEmpty() && !empty($post->media_url)) {
                    $mediaItems = collect([(object)['media_url' => $post->media_url, 'media_type' => $post->post_type ?? 'image']]);
                }
                $videoExts = ['mp4', 'mov', 'avi', 'webm', 'mkv'];
            @endphp

            @if ($mediaItems->count() > 0)
                <div class="media-hero-container">
                    @if ($mediaItems->count() === 1)
                        @php
                            $media = $mediaItems->first();
                            $url = $media->media_url ?? $media->url ?? '';
                            $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                        @endphp

                        @if (in_array($ext, $videoExts) || ($media->media_type ?? '') === 'video')
                            <video class="media-hero-element" controls preload="metadata">
                                <source src="{{ $url }}" type="video/{{ $ext ?: 'mp4' }}">
                                Your browser does not support video playback.
                            </video>
                        @elseif (strtolower($post->platform) === 'youtube')
                            @php
                                parse_str(parse_url($url, PHP_URL_QUERY), $query);
                                $videoId = $query['v'] ?? $post->post_id;
                            @endphp
                            <div class="ratio ratio-16x9">
                                <iframe src="https://www.youtube.com/embed/{{ $videoId }}" allowfullscreen></iframe>
                            </div>
                        @else
                            <img src="{{ $url }}" class="media-hero-element" alt="Post Media Attachment">
                        @endif

                    @else
                        {{-- CAROUSEL FOR MULTIPLE ATTACHMENTS --}}
                        <div id="postMediaCarousel" class="carousel slide" data-bs-ride="false">
                            <div class="carousel-indicators">
                                @foreach ($mediaItems as $index => $item)
                                    <button type="button" data-bs-target="#postMediaCarousel" data-bs-slide-to="{{ $index }}" class="{{ $loop->first ? 'active' : '' }}"></button>
                                @endforeach
                            </div>

                            <div class="carousel-inner">
                                @foreach ($mediaItems as $item)
                                    @php
                                        $url = $item->media_url ?? $item->url ?? '';
                                        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                                    @endphp
                                    <div class="carousel-item {{ $loop->first ? 'active' : '' }}">
                                        @if (in_array($ext, $videoExts) || ($item->media_type ?? '') === 'video')
                                            <video class="media-hero-element" controls preload="metadata">
                                                <source src="{{ $url }}" type="video/{{ $ext ?: 'mp4' }}">
                                            </video>
                                        @else
                                            <img src="{{ $url }}" class="media-hero-element" alt="Post Media">
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            <button class="carousel-control-prev" type="button" data-bs-target="#postMediaCarousel" data-bs-slide="prev">
                                <i class="fa fa-chevron-left"></i>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#postMediaCarousel" data-bs-slide="next">
                                <i class="fa fa-chevron-right"></i>
                            </button>
                        </div>
                    @endif
                </div>
            @endif

            {{-- POST CARD BODY --}}
            <div class="card-body p-4">

                {{-- ACCOUNT INFO & PLATFORM META --}}
                <div class="d-flex align-items-center justify-content-between pb-3 mb-4 border-bottom">
                    <div class="d-flex align-items-center gap-3">
                        <div class="platform-avatar {{ strtolower($post->platform) }}">
                            <i class="fab fa-{{ strtolower($post->platform) === 'x' ? 'x-twitter' : strtolower($post->platform) }}"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark">
                                {{ $post->socialAccount->name ?? ucfirst($post->platform) }}
                            </h5>
                            <div class="d-flex align-items-center gap-2 text-muted small mt-1">
                                <span><i class="far fa-user me-1"></i> Author: <strong>{{ $post->user->name ?? 'System' }}</strong></span>
                                <span>•</span>
                                <span><i class="far fa-clock me-1"></i> {{ $post->created_at->diffForHumans() }}</span>
                                
                                @if($post->category)
                                    <span>•</span>
                                    <span class="badge bg-light text-dark border fw-normal">
                                        <i class="far fa-folder me-1 text-primary"></i> {{ $post->category->name }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($post->platform_url)
                        <a href="{{ $post->platform_url }}" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                            <i class="fa fa-external-link-alt me-1"></i> View Live
                        </a>
                    @endif
                </div>

                {{-- TITLE & CONTENT --}}
                @if($post->title)
                    <h4 class="post-title mb-3">{{ $post->title }}</h4>
                @endif

                <div class="post-body-text mb-4">
                    {!! $post->content !!}
                </div>

                {{-- HASHTAGS, MENTIONS & TAGS --}}
                @if(!empty($post->hashtags) || !empty($post->mentions) || !empty($post->tags))
                    <div class="d-flex flex-wrap gap-2 mb-4 pt-2">
                        @if(!empty($post->hashtags))
                            @foreach($post->hashtags as $hashtag)
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2">
                                    #{{ ltrim($hashtag, '#') }}
                                </span>
                            @endforeach
                        @endif

                        @if(!empty($post->mentions))
                            @foreach($post->mentions as $mention)
                                <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-3 py-2">
                                    @ {{ ltrim($mention, '@') }}
                                </span>
                            @endforeach
                        @endif

                        @if(!empty($post->tags))
                            @foreach($post->tags as $tag)
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-2">
                                    <i class="fa fa-tag me-1"></i> {{ is_array($tag) ? ($tag['name'] ?? '') : $tag }}
                                </span>
                            @endforeach
                        @endif
                    </div>
                @endif

                {{-- ENGAGEMENT METRICS GRID --}}
                <div class="row g-3 pt-2">
                    <div class="col-6 col-md-3">
                        <div class="metric-card d-flex align-items-center gap-3">
                            <div class="metric-icon bg-danger-subtle text-danger"><i class="far fa-heart"></i></div>
                            <div>
                                <div class="fw-bold fs-5 text-dark">{{ number_format($post->likes ?? 0) }}</div>
                                <div class="text-muted small">Likes</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="metric-card d-flex align-items-center gap-3">
                            <div class="metric-icon bg-primary-subtle text-primary"><i class="far fa-comment"></i></div>
                            <div>
                                <div class="fw-bold fs-5 text-dark">{{ number_format($post->postComments->count()) }}</div>
                                <div class="text-muted small">Comments</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="metric-card d-flex align-items-center gap-3">
                            <div class="metric-icon bg-success-subtle text-success"><i class="fa fa-share"></i></div>
                            <div>
                                <div class="fw-bold fs-5 text-dark">{{ number_format($post->shares ?? 0) }}</div>
                                <div class="text-muted small">Shares</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="metric-card d-flex align-items-center gap-3">
                            <div class="metric-icon bg-warning-subtle text-warning"><i class="far fa-bookmark"></i></div>
                            <div>
                                <div class="fw-bold fs-5 text-dark">{{ number_format($post->saves ?? 0) }}</div>
                                <div class="text-muted small">Saves</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="metric-card d-flex align-items-center gap-3">
                            <div class="metric-icon bg-info-subtle text-info"><i class="far fa-eye"></i></div>
                            <div>
                                <div class="fw-bold fs-5 text-dark">{{ number_format($post->views ?? 0) }}</div>
                                <div class="text-muted small">Views</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="metric-card d-flex align-items-center gap-3">
                            <div class="metric-icon bg-purple-subtle text-purple" style="background:#f3e8ff; color:#7e22ce;"><i class="fa fa-chart-line"></i></div>
                            <div>
                                <div class="fw-bold fs-5 text-dark">{{ number_format($post->reach ?? 0) }}</div>
                                <div class="text-muted small">Reach</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="metric-card d-flex align-items-center gap-3">
                            <div class="metric-icon bg-secondary-subtle text-secondary"><i class="fa fa-bullseye"></i></div>
                            <div>
                                <div class="fw-bold fs-5 text-dark">{{ number_format($post->impressions ?? 0) }}</div>
                                <div class="text-muted small">Impressions</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="metric-card d-flex align-items-center gap-3">
                            <div class="metric-icon bg-dark-subtle text-dark"><i class="fa fa-percentage"></i></div>
                            <div>
                                <div class="fw-bold fs-5 text-dark">{{ number_format($post->engagement_rate ?? 0, 2) }}%</div>
                                <div class="text-muted small">Eng. Rate</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SCHEDULING & EXPIRY INFO --}}
                @if($post->schedule_at || $post->expiry_at)
                    <div class="row g-2 mt-3 pt-3 border-top">
                        @if($post->schedule_at)
                            <div class="col-md-6">
                                <div class="p-2 bg-light rounded-3 small">
                                    <i class="far fa-calendar-alt text-primary me-1"></i>
                                    <strong>Scheduled for:</strong> {{ $post->schedule_at->format('d M Y, h:i A') }}
                                </div>
                            </div>
                        @endif
                        @if($post->expiry_at)
                            <div class="col-md-6">
                                <div class="p-2 bg-warning-subtle rounded-3 small text-warning-emphasis">
                                    <i class="fa fa-hourglass-end me-1"></i>
                                    <strong>Expires at:</strong> {{ $post->expiry_at->format('d M Y, h:i A') }}
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

            </div>
        </div>

        {{-- COMMENTS & MODERATION SECTION --}}
        <div class="comments-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="far fa-comments me-2 text-primary"></i> Comments & Moderation
                </h5>
                <span class="badge bg-dark rounded-pill px-3 py-2">
                    {{ number_format($post->postComments->count()) }}
                </span>
            </div>

            @if ($post->postComments->count() > 0)
                @foreach ($post->postComments as $comment)
                    <div class="comment-card" data-tr_id="{{ $comment->id }}">
                        <div class="d-flex align-items-start gap-3">

                            {{-- USER AVATAR --}}
                            <img src="{{ !empty($comment->user_avatar_url) && $comment->user_avatar_url !== 'test' ? $comment->user_avatar_url : 'https://ui-avatars.com/api/?background=random&name=' . urlencode($comment->user_name) }}"
                                 class="avatar-ring" alt="{{ $comment->user_name }}">

                            <div class="flex-grow-1">

                                {{-- HEADER --}}
                                <div class="d-flex justify-content-between align-items-baseline mb-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <h6 class="fw-bold text-dark mb-0">{{ $comment->user_name }}</h6>
                                        
                                        @if($comment->sender_type !== 'customer')
                                            <span class="badge bg-primary-subtle text-primary" style="font-size: 0.65rem;">Admin</span>
                                        @endif

                                        {{-- SENTIMENT BADGE --}}
                                        @if($comment->sentiment_label)
                                            <span class="badge sentiment-badge-{{ $comment->sentiment_label }} rounded-pill px-2 py-1" style="font-size: 0.65rem;">
                                                {{ $comment->sentiment_emoji }} {{ ucfirst($comment->sentiment_label) }}
                                            </span>
                                        @endif

                                        @if($comment->is_pinned)
                                            <span class="badge bg-warning text-dark" style="font-size: 0.65rem;"><i class="fa fa-thumbtack"></i> Pinned</span>
                                        @endif
                                    </div>

                                    <span class="text-muted small" style="font-size: 0.75rem;">
                                        {{ $comment->posted_at ? $comment->posted_at->diffForHumans() : $comment->created_at->diffForHumans() }}
                                    </span>
                                </div>

                                {{-- CONTENT --}}
                                <div class="text-secondary mb-2" style="font-size: 0.95rem;">
                                    {!! $comment->content !!}
                                </div>

                                {{-- COMMENT ACTIONS & STATS --}}
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-3">
                                        <a href="javascript:void(0)" class="action-link reply-toggle">
                                            <i class="fa fa-reply me-1"></i> Reply
                                        </a>

                                        <a href="javascript:void(0)" class="action-link delete-link delete_comment" data-id="{{ $comment->id }}">
                                            <i class="fa fa-trash me-1"></i> Delete
                                        </a>
                                    </div>

                                    @if($comment->likes > 0)
                                        <div class="text-muted small">
                                            <i class="far fa-heart text-danger me-1"></i> {{ $comment->likes }}
                                        </div>
                                    @endif
                                </div>

                                {{-- REPLY FORM (AJAX) --}}
                                <div class="reply-form d-none mt-3">
                                    <form class="ajax-reply-form" method="POST">
                                        @csrf
                                        <input type="hidden" name="parent_comment_id" value="{{ $comment->id }}">
                                        <input type="hidden" name="post_id" value="{{ $post->id }}">
                                        <input type="hidden" name="social_account_id" value="{{ $comment->social_account_id }}">
                                        <input type="hidden" name="platform" value="{{ $comment->platform }}">

                                        <div class="input-group">
                                            <input type="text" name="content" class="form-control form-control-sm rounded-start-pill px-3 reply-input-field" placeholder="Write a reply..." required>
                                            <button type="submit" class="btn btn-primary btn-sm rounded-end-pill px-4 btn-submit-reply">
                                                <i class="fa fa-paper-plane me-1"></i> Send
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                {{-- THREADED CHILD REPLIES --}}
                                @php $replies = $comment->childComments; @endphp
                                <div class="nested-reply-box {{ $replies->isEmpty() ? 'd-none' : '' }}">
                                    @foreach ($replies as $reply)
                                        <div class="reply-item d-flex align-items-start gap-2 mb-2" data-tr_id="{{ $reply->id }}">
                                            <img src="{{ !empty($reply->user_avatar_url) && $reply->user_avatar_url !== 'test' ? $reply->user_avatar_url : 'https://ui-avatars.com/api/?background=random&name=' . urlencode($reply->user_name) }}"
                                                 class="avatar-ring" style="width: 32px; height: 32px;">
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <span class="fw-bold small text-dark">
                                                        {{ $reply->user_name }}
                                                        @if($reply->sender_type !== 'customer')
                                                            <span class="badge bg-primary-subtle text-primary me-1" style="font-size: 0.6rem;">Admin</span>
                                                        @endif
                                                    </span>
                                                    <span class="text-muted" style="font-size: 0.7rem;">
                                                        {{ $reply->posted_at ? $reply->posted_at->diffForHumans() : $reply->created_at->diffForHumans() }}
                                                    </span>
                                                </div>
                                                <div class="text-secondary small mt-1">
                                                    {{ $reply->content }}
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="text-center py-5 bg-white rounded-4 border">
                    <i class="far fa-comments text-muted fs-1 mb-2 opacity-50"></i>
                    <p class="text-muted mb-0">No comments found for this post yet.</p>
                </div>
            @endif
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle Reply Input Form
        $(document).on('click', '.reply-toggle', function() {
            let form = $(this).closest('.flex-grow-1').find('.reply-form').first();
            form.toggleClass('d-none');
            if (!form.hasClass('d-none')) {
                form.find('.reply-input-field').focus();
            }
        });

        // Initialize Tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });

    // SUBMIT COMMENT REPLY VIA AJAX
    $(document).on('submit', '.ajax-reply-form', function(e) {
        e.preventDefault();

        let form = $(this);
        let submitBtn = form.find('.btn-submit-reply');
        let replyBox = form.closest('.flex-grow-1').find('.nested-reply-box').first();
        let inputField = form.find('.reply-input-field');

        submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Sending...');

        $.ajax({
            url: "{{ route('admin.comments.store') }}",
            type: "POST",
            data: form.serialize(),
            success: function(response) {
                submitBtn.prop('disabled', false).html('<i class="fa fa-paper-plane me-1"></i> Send');

                if (response.success || response.status) {
                    let data = response.data || response.comment || response;

                    let userName = data.user_name || "{{ Auth::user()->name ?? 'Admin' }}";
                    let content = data.content || inputField.val();
                    let avatar = data.user_avatar_url && data.user_avatar_url !== 'test' 
                        ? data.user_avatar_url 
                        : `https://ui-avatars.com/api/?background=random&name=${encodeURIComponent(userName)}`;

                    let newReplyHtml = `
                        <div class="reply-item d-flex align-items-start gap-2 mb-2" data-tr_id="${data.id || ''}">
                            <img src="${avatar}" class="avatar-ring" style="width: 32px; height: 32px;">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="fw-bold small text-dark">${userName}</span>
                                    <span class="text-muted" style="font-size: 0.7rem;">Just now</span>
                                </div>
                                <div class="text-secondary small mt-1">
                                    ${content}
                                </div>
                            </div>
                        </div>
                    `;

                    replyBox.removeClass('d-none').append(newReplyHtml);
                    inputField.val('');
                    form.closest('.reply-form').addClass('d-none');

                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Reply submitted',
                        showConfirmButton: false,
                        timer: 2000
                    });
                } else {
                    Swal.fire('Error!', response.message || 'Failed to submit reply.', 'error');
                }
            },
            error: function(xhr) {
                submitBtn.prop('disabled', false).html('<i class="fa fa-paper-plane me-1"></i> Send');
                let message = xhr.responseJSON?.message || 'Something went wrong.';
                Swal.fire('Error!', message, 'error');
            }
        });
    });

    // DELETE COMMENT VIA AJAX
    $(document).on('click', '.delete_comment', function(e) {
        e.preventDefault();
        let comment_id = $(this).data('id');

        Swal.fire({
            title: 'Are you sure?',
            text: "This comment will be permanently removed!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "{{ route('admin.comments.destroy', ':id') }}".replace(':id', comment_id),
                    type: 'DELETE',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            $(`div[data-tr_id="${comment_id}"]`).fadeOut(300, function() {
                                $(this).remove();
                            });
                            Swal.fire('Deleted!', 'Comment has been deleted.', 'success');
                        } else {
                            Swal.fire('Error!', response.message || 'Could not delete comment.', 'error');
                        }
                    },
                    error: function(xhr) {
                        let message = xhr.responseJSON?.message || 'Something went wrong.';
                        Swal.fire('Error!', message, 'error');
                    }
                });
            }
        });
    });
</script>
@endpush