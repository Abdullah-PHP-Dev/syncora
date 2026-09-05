<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Http\Requests\Admin\PostRequest;
use App\Models\Post;
use App\Services\PostServices\MetaPostService;
use App\Services\PostServices\InstagramPostService;
use App\Services\PostServices\GooglePostService;
use App\Services\PostServices\YoutubePostService;
use App\Services\PostServices\TiktokPostService;
use App\Services\PostServices\XPostService;
use App\Services\PostServices\LinkedInPostService;
use App\Services\PostServices\WhatsAppPostService;
use App\Services\PostServices\ThreadsPostService;
use App\Services\PostServices\PinterestPostService;
use App\Models\PostCategory;
use App\Models\SocialAccount;
use App\Models\PostMedia;
use App\Models\PostComment;
use App\Models\Messaging\Message;
use Illuminate\Support\Facades\DB;
use App\Models\Messaging\Conversation;
use App\Support\Gemini\RetryPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PostController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    protected $metaService, $instagramService, $googleService, $_config, $youtubeService, $tiktokService, $xService, $linkedinService, $whatsappService, $threadsService, $pinterestService, $nanoBananaAI;

    public function __construct(MetaPostService $metaService, InstagramPostService $instagramService, GooglePostService $googleService, YoutubePostService $youtubeService, TiktokPostService $tiktokService, XPostService $xService, LinkedInPostService $linkedinService, WhatsAppPostService $whatsappService, ThreadsPostService $threadsService, PinterestPostService $pinterestService)
    {
    //     session(['platform' => request()->platform ?? session('platform')]);

        $this->metaService = $metaService;
        $this->instagramService = $instagramService;
        $this->googleService = $googleService;
        $this->youtubeService = $youtubeService;
        $this->tiktokService = $tiktokService;
        $this->xService = $xService;
        $this->linkedinService = $linkedinService;
        $this->whatsappService = $whatsappService;
        $this->threadsService = $threadsService;
        $this->pinterestService = $pinterestService;
    //     $this->nanoBananaAI = $nanoBananaAI;
        $this->_config = request('_config');
     }
    /**
     * Display a listing of the resource.
     */
    public function dashboard(Request $request)
    {
        $userId = Auth::id();

        // ---- Date range filter (applies to posts/engagement below;
        // defaults to all-time when not provided) ----
        $dateFrom = $request->filled('from') ? \Carbon\Carbon::parse($request->query('from'))->startOfDay() : null;
        $dateTo   = $request->filled('to') ? \Carbon\Carbon::parse($request->query('to'))->endOfDay() : null;

        // ---- Accounts ----
        $accounts = SocialAccount::whereUserId($userId)->with('postDetails')->get();
        $totalAccounts = $accounts->count();
        $accountsByPlatform = $accounts->groupBy('platform')->map->count();
        $totalFollowers = (int) $accounts->sum('followers_count');
        $totalAccountLikes = (int) $accounts->sum('likes_count');
        $totalMedia = (int) $accounts->sum(fn ($account) => (int) $account->media_count);

        // ---- Posts Query ----
        $postQuery = Post::where('user_id', $userId);
        if ($dateFrom && $dateTo) {
            $postQuery->whereBetween('created_at', [$dateFrom, $dateTo]);
        }
        // Status counts
        $totalPosts = (clone $postQuery)->count();
        $publishedPosts = (clone $postQuery)->where('status', 'published')->count();
        $scheduledPosts = (clone $postQuery)->where('status', 'scheduled')->count();
        $pendingPosts   = (clone $postQuery)->whereIn('status', ['pending', 'PROCESSING', 'IN_PROGRESS'])->count();
        $failedPosts    = (clone $postQuery)->where('status', 'failed')->count();
        $draftPosts     = (clone $postQuery)->where('status', 'draft')->count();
    
        // Engagement totals
        $totalLikes    = (clone $postQuery)->sum('likes');
        $totalComments = PostComment::whereHas('post', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->count();
        $totalShares      = (clone $postQuery)->sum('shares');
        $totalViews       = (clone $postQuery)->sum('views');
        $totalImpressions = (clone $postQuery)->sum('impressions');
        $totalReach       = (clone $postQuery)->sum('reach');
        $totalEngagement = $totalLikes + $totalComments + $totalShares + $totalViews;

        // ---- Categories with post counts ----
        $categories = PostCategory::where('user_id', $userId)
            ->withCount(['posts' => function ($q) {
                $q->where('status', 'published');
            }])
            ->orderBy('id', 'desc')
            ->get();
        // ---- Monthly data for charts (last 7 months) ----
        $months = [];
        $monthlyPostCounts = [];
        $monthlyLikes = [];
        $monthlyViews = [];
        $monthlyImpressions = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = $date->format('M');
            $monthPosts = (clone $postQuery)
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year);
            $monthlyPostCounts[] = (clone $monthPosts)->count();
            $monthlyLikes[]      = (int) (clone $monthPosts)->sum('likes');
            $monthlyViews[]      = (int) (clone $monthPosts)->sum('views');
            $monthlyImpressions[] = (int) (clone $monthPosts)->sum('impressions');
        }

        // Compute month-over-month growth for the "Growth" card
        $growthPercent = 0;
        if (count($monthlyPostCounts) >= 2) {
            $prev = $monthlyPostCounts[count($monthlyPostCounts) - 2];
            $curr = $monthlyPostCounts[count($monthlyPostCounts) - 1];
            $growthPercent = $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : 0;
        }
    
        // ---- Recent comments (for Transactions list) ----
        $recentComments = PostComment::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get();
    
        // ---- Additional: total comments count (for Payments card) ----
        $totalCommentsAll = PostComment::where('user_id', $userId)->count();

        // ---- Comments grouped by platform ----
        $commentsByPlatform = PostComment::where('user_id', $userId)
            ->selectRaw('platform, count(*) as total')
            ->groupBy('platform')
            ->pluck('total', 'platform');

        // ---- Latest inbound DMs across all connected channels ----
        $latestMessages = Message::whereHas('conversation.channel', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->where('direction', 'inbound')
            ->with('conversation')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $totalMessages = Message::whereHas('conversation.channel', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->count();

        // ---- Connected accounts overview (followers/likes/media per account) ----
        $accountsOverview = $accounts->map(function ($account) {
            return [
                'id'             => $account->id,
                'platform'       => $account->platform,
                'name'           => $account->name ?: $account->username,
                'username'       => $account->username,
                'image'          => $account->avatar_url,
                'follower_count' => (int) $account->followers_count,
                'likes_count'    => (int) $account->likes_count,
                'media_count'    => (int) $account->media_count,
                'views_count'    => (int) $account->views_count,
            ];
        })->sortByDesc('follower_count')->values();

        // ---- Engagement rate: (likes+comments+shares) / reach ----
        // Reach under 50 makes the ratio meaningless (a handful of test
        // engagements against near-zero reach produces absurd percentages
        // like 4000%+) - show it as unavailable rather than a misleading number.
        $engagementRate = $totalReach >= 50
            ? round((($totalLikes + $totalComments + $totalShares) / $totalReach) * 100, 2)
            : null;

        // ---- Upcoming scheduled posts ----
        // Every post is written with status 'pending' when queued (see
        // MetaPostService::store() etc.) and only flips to 'completed'/
        // 'failed' once social:publish-posts runs it - there is no
        // 'scheduled' status literal anywhere in the write path, so a
        // pending post with a future schedule_at IS the "scheduled" one.
        $upcomingPosts = Post::where('user_id', $userId)
            ->where('status', 'pending')
            ->whereNotNull('schedule_at')
            ->where('schedule_at', '>=', now())
            ->with(['socialAccount', 'media'])
            ->orderBy('schedule_at')
            ->limit(4)
            ->get();

        // ---- Last 7 days daily performance (for the tabbed chart) ----
        $dailyLabels = [];
        $dailyReach = [];
        $dailyEngagement = [];
        $dailyClicks = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $dailyLabels[] = $day->format('M d');
            $dayPosts = Post::where('user_id', $userId)->whereDate('created_at', $day->toDateString());
            $dailyReach[] = (int) (clone $dayPosts)->sum('reach');
            $dailyEngagement[] = (int) (clone $dayPosts)->sum(DB::raw('likes + comments + shares'));
            $dailyClicks[] = (int) (clone $dayPosts)->sum('clicks');
        }

        // ---- Recent + top performing posts (for the two-column feed) ----
        $recentPosts = (clone $postQuery)->with(['socialAccount', 'media'])->latest()->limit(4)->get();
        $topPosts = (clone $postQuery)->with(['socialAccount', 'media'])
            ->orderByRaw('(likes + shares + comments + reach) DESC')
            ->limit(4)
            ->get();

        // ---- Current month calendar: which days have posts/comments/messages ----
        $calendarMonth = $request->filled('cal')
            ? \Carbon\Carbon::createFromFormat('Y-m', $request->query('cal'))->startOfMonth()
            : now();
        // A content calendar has to key posts by the day they're actually
        // scheduled for, not the day the DB row was created - scheduling
        // something today for next Friday must show up on Friday's cell,
        // not today's. (published_at is listed in Post::$fillable but
        // isn't a real column on this table - not something to build on
        // here - so "already published/no schedule" falls back to
        // created_at, same as before.) schedule_at and created_at can
        // each fall in a different month, so the query nets anything
        // touching this month via either, then groups in PHP by whichever
        // date is actually the right one to display for that post.
        $calendarEffectiveDate = fn ($p) => $p->schedule_mode && $p->schedule_at
            ? $p->schedule_at
            : $p->created_at;

        $calendarMonthPosts = Post::where('user_id', $userId)
            ->where(function ($q) use ($calendarMonth) {
                $q->whereMonth('schedule_at', $calendarMonth->month)->whereYear('schedule_at', $calendarMonth->year)
                    ->orWhere(function ($q2) use ($calendarMonth) {
                        $q2->whereNull('schedule_at')
                            ->whereMonth('created_at', $calendarMonth->month)->whereYear('created_at', $calendarMonth->year);
                    });
            })
            ->with('media', 'socialAccount')
            ->get()
            ->filter(fn ($p) => $calendarEffectiveDate($p)?->isSameMonth($calendarMonth))
            ->sortBy($calendarEffectiveDate)
            ->groupBy(fn ($p) => $calendarEffectiveDate($p)->day)
            ->map(function ($dayPosts) {
                // Collapse posts sharing a group_id (one quickStore()
                // submission fanned out across several platforms) into a
                // single representative entry - the same COALESCE(group_id,
                // id) grouping buildPostsQuery() uses for the main posts
                // listing, so a post sent to both Facebook and Instagram at
                // once shows up as one calendar entry, not two.
                return $dayPosts->groupBy(fn ($p) => $p->group_id ?? $p->id)
                    ->map(function ($groupMembers) {
                        $representative = $groupMembers->first();
                        $representative->setAttribute('group_platforms', $groupMembers->map(fn ($m) => [
                            'platform' => $m->platform,
                            'status' => $m->status,
                            'post_id' => $m->id,
                        ])->values());
                        return $representative;
                    })
                    ->values();
            });

        // Posting-permitted accounts, for the calendar's "quick post" modal
        // platform picker - same has_posting_permission gate as the main
        // create page, so an ad-only account can't be selected here either.
        $postingAccounts = SocialAccount::where('user_id', $userId)
            ->where('has_posting_permission', true)
            ->get();
        $calendarPostDays = $calendarMonthPosts->map->count();
        $calendarPostsThisMonth = (int) $calendarPostDays->sum();
        $calendarCommentsThisMonth = PostComment::where('user_id', $userId)
            ->whereMonth('created_at', $calendarMonth->month)
            ->whereYear('created_at', $calendarMonth->year)
            ->count();
        $calendarMessagesThisMonth = Message::whereHas('conversation.channel', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->whereMonth('created_at', $calendarMonth->month)
            ->whereYear('created_at', $calendarMonth->year)
            ->count();

        // ---- Unread inbox count, for the header notification bell ----
        $totalUnreadMessages = Conversation::whereHas('channel', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->sum('unread_count');

        // ---- Week-over-week reach/engagement trend (real previous-7-days
        // comparison, not a fabricated percentage) - used on the stat cards ----
        $prevWeekReach = (int) Post::where('user_id', $userId)
            ->whereBetween('created_at', [now()->subDays(13), now()->subDays(7)])
            ->sum('reach');
        $prevWeekEngagement = (int) Post::where('user_id', $userId)
            ->whereBetween('created_at', [now()->subDays(13), now()->subDays(7)])
            ->sum(DB::raw('likes + comments + shares'));
        $currWeekReach = array_sum($dailyReach);
        $currWeekEngagement = array_sum($dailyEngagement);
        $reachChangePercent = $prevWeekReach > 0
            ? round((($currWeekReach - $prevWeekReach) / $prevWeekReach) * 100, 1)
            : null;
        $engagementChangePercent = $prevWeekEngagement > 0
            ? round((($currWeekEngagement - $prevWeekEngagement) / $prevWeekEngagement) * 100, 1)
            : null;

        // ---- New accounts connected in the last 7 days, for the Connected
        // Accounts card (real signal, since follower history isn't tracked) ----
        $newAccountsThisWeek = SocialAccount::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return view($this->_config['view'], compact(
            'totalAccounts',
            'accountsByPlatform',
            'accountsOverview',
            'totalFollowers',
            'totalAccountLikes',
            'totalMedia',
            'totalPosts',
            'publishedPosts',
            'scheduledPosts',
            'pendingPosts',
            'failedPosts',
            'draftPosts',
            'totalLikes',
            'totalComments',
            'totalShares',
            'totalViews',
            'totalImpressions',
            'totalReach',
            'totalEngagement',
            'categories',
            'months',
            'monthlyPostCounts',
            'monthlyLikes',
            'monthlyViews',
            'monthlyImpressions',
            'growthPercent',
            'recentComments',
            'totalCommentsAll',
            'commentsByPlatform',
            'latestMessages',
            'totalMessages',
            'dateFrom',
            'dateTo',
            'engagementRate',
            'upcomingPosts',
            'dailyLabels',
            'dailyReach',
            'dailyEngagement',
            'dailyClicks',
            'calendarMonth',
            'calendarMonthPosts',
            'calendarPostDays',
            'calendarPostsThisMonth',
            'calendarCommentsThisMonth',
            'calendarMessagesThisMonth',
            'recentPosts',
            'topPosts',
            'totalUnreadMessages',
            'reachChangePercent',
            'engagementChangePercent',
            'newAccountsThisWeek',
            'postingAccounts'
        ));
    }
    /**
     * JSON endpoint used by the posts dashboard for paginated/filtered listing.
     */
    public function index(Request $request)
    {
        $userId = Auth::id();
        $perPage = min((int) $request->input('per_page', 6), 50) ?: 6;

        $posts = $this->buildPostsQuery($request, $userId)->paginate($perPage);
        $platformsByPostId = $this->platformsForGroups($posts->getCollection(), $userId);

        $posts->through(fn ($post) => $this->formatPostSummary($post, $platformsByPostId[$post->id] ?? null));

        return response()->json([
            'data' => $posts->items(),
            'current_page' => $posts->currentPage(),
            'last_page' => $posts->lastPage(),
            'per_page' => $posts->perPage(),
            'total' => $posts->total(),
            'from' => $posts->firstItem(),
            'to' => $posts->lastItem(),
            'platform_counts' => $this->platformCounts($userId),
        ]);
    }

	public function index_vue(Request $request)
    {
        $userId = Auth::id();

        $platform = strtolower($request->platform) ?? 'facebook';
        if ($request->platform === null) {
            $platform = session('platform');
        }

        $platform = $platform ?? 'facebook';

        $posts = $this->buildPostsQuery($request, $userId)->paginate(6);
        $platformsByPostId = $this->platformsForGroups($posts->getCollection(), $userId);

        $posts->through(fn ($post) => $this->formatPostSummary($post, $platformsByPostId[$post->id] ?? null));

        $platformCounts = $this->platformCounts($userId);

        // Real connected accounts (name, username, avatar_url, platform)
        // for the "Create post" modal's picker, so it shows the actual
        // Page/Profile you're posting as instead of a bare platform logo.
        $postingAccounts = SocialAccount::where('user_id', $userId)
            ->where('has_posting_permission', true)
            ->get(['id', 'platform', 'name', 'username', 'avatar_url'])
            ->values();

        return view('admin.posts.index_vue', compact('posts', 'platform', 'platformCounts', 'postingAccounts'));
    }

    /**
     * Apply search/status/platform/sort filters shared by the listing
     * endpoints, and collapse every Post row sharing a group_id (one
     * PostController::quickStore() submission fanned out across several
     * platforms) down to a single representative row - the earliest one
     * created. COALESCE(group_id, id) treats every ungrouped post (created
     * before this grouping existed, or via the full composer's store(),
     * which doesn't set group_id) as its own group of one, so nothing
     * outside quick-post's multi-platform case changes shape. The other
     * posts in each group are re-attached afterwards by platformsForGroups().
     */
    private function buildPostsQuery(Request $request, int $userId)
    {
        $query = Post::with(['socialAccount', 'media', 'user'])
            ->where('user_id', $userId)
            ->whereIn('id', function ($sub) use ($userId) {
                $sub->selectRaw('MIN(id)')
                    ->from('posts')
                    ->where('user_id', $userId)
                    ->groupBy(DB::raw('COALESCE(group_id, id)'));
            });

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', strtolower($status));
        }

        if ($platform = $request->input('platform')) {
            $platform = strtolower($platform);

            // A grouped card should match if ANY platform in its batch was
            // published to the filtered platform, not just its
            // representative row.
            $query->where(function ($q) use ($platform, $userId) {
                $q->where('platform', $platform)
                    ->orWhereIn(DB::raw('COALESCE(group_id, id)'), function ($sub) use ($platform, $userId) {
                        $sub->select(DB::raw('COALESCE(group_id, id)'))
                            ->from('posts')
                            ->where('user_id', $userId)
                            ->where('platform', $platform);
                    });
            });
        }

        $query->orderBy('created_at', $request->input('sort') === 'oldest' ? 'asc' : 'desc');

        return $query;
    }

    /**
     * For a page of representative Post rows, fetch every sibling post
     * sharing the same COALESCE(group_id, id) key (i.e. every platform a
     * single quick-post submission was published to), keyed by the
     * representative post's own id so formatPostSummary() can attach them.
     */
    private function platformsForGroups($posts, int $userId): array
    {
        $groupKeys = $posts->pluck('id')
            ->merge($posts->pluck('group_id')->filter())
            ->unique()
            ->values();

        if ($groupKeys->isEmpty()) {
            return [];
        }

        $siblings = Post::with('socialAccount')
            ->where('user_id', $userId)
            ->where(function ($q) use ($groupKeys) {
                $q->whereIn('group_id', $groupKeys)
                    ->orWhereIn('id', $groupKeys);
            })
            ->get(['id', 'group_id', 'platform', 'status', 'post_url', 'social_account_id']);

        $map = [];

        foreach ($posts as $post) {
            $key = $post->group_id ?? $post->id;

            $map[$post->id] = $siblings
                ->filter(fn ($sibling) => ($sibling->group_id ?? $sibling->id) === $key)
                ->map(fn ($sibling) => [
                    'platform_key'     => $sibling->platform,
                    'status'           => ucfirst($sibling->status ?? 'draft'),
                    'post_id'          => $sibling->id,
                    'post_url'         => $sibling->post_url,
                    // The actual connected Page/Profile this platform entry
                    // was published as - shown instead of a bare platform
                    // logo, same identity data the calendar's account
                    // picker uses.
                    'account_name'     => $sibling->socialAccount->name ?? $sibling->socialAccount->username ?? null,
                    'account_username' => $sibling->socialAccount->username ?? null,
                    'account_avatar'   => $sibling->socialAccount->avatar_url ?? null,
                ])
                ->values()
                ->all();
        }

        return $map;
    }

    /**
     * Post counts per platform (plus a total) for the listing's platform tabs.
     * Intentionally ignores search/status/platform filters, matching the tabs'
     * job of showing totals across the whole account.
     */
    private function platformCounts(int $userId): array
    {
        $counts = Post::where('user_id', $userId)
            ->select('platform', DB::raw('count(*) as count'))
            ->groupBy('platform')
            ->pluck('count', 'platform');

        return [
            'all' => $counts->sum(),
            ...$counts->toArray(),
        ];
    }

    /**
     * Show a dedicated per-platform preview of a post, with a sidebar
     * to switch between the other connected platforms for the same post.
     * "Same post" = every Post row sharing this one's group_id (one
     * PostController::quickStore() submission fanned out across
     * platforms) - see buildPostsQuery()'s docblock for the same
     * COALESCE(group_id, id) grouping used by the listing. Every member is
     * sent to the view so PostPreview.vue can switch between platforms
     * instantly client-side with no reload/refetch.
     */
    public function preview($postId, $platform)
    {
        $post = $this->postWithPreviewRelations()
            ->where('user_id', Auth::id())
            ->find($postId);

        if (!$post) {
            return view('admin.posts.preview', [
                'postId' => $postId,
                'platform' => $platform,
                'post' => null,
                'groupPosts' => [],
            ]);
        }

        $this->refreshInstagramCommentsIfNeeded($post);

        $groupKey = $post->group_id ?? $post->id;

        $groupPosts = $this->postWithPreviewRelations()
            ->where('user_id', Auth::id())
            ->where(function ($query) use ($groupKey) {
                $query->where('group_id', $groupKey)->orWhere('id', $groupKey);
            })
            ->get()
            ->each(fn ($member) => $this->refreshInstagramCommentsIfNeeded($member))
            ->map(fn ($member) => $this->formatPostForPreview($member))
            ->values();

        return view('admin.posts.preview', [
            'postId' => $postId,
            'platform' => $platform,
            'post' => $this->formatPostForPreview($post),
            'groupPosts' => $groupPosts,
        ]);
    }

    /**
     * Public, unauthenticated share-preview page for a single post -
     * routes/web.php registers this OUTSIDE the auth middleware
     * deliberately. Snap's Creative Kit "Share to Snapchat" button (see
     * resources/js/components/posts/PostsDashboard.vue and
     * dashboard.blade.php's quick-post modal) points its data-share-url
     * here so Snap's servers can fetch og:image/og:title without a
     * session - the same reason any social share button needs a public
     * URL rather than the real admin.posts.show page. Deliberately
     * exposes only this one post's own public-facing content (caption +
     * first media item) - no owner/account data.
     */
    public function sharePreview(int $postId)
    {
        $post = Post::with('media')->find($postId);

        return view('share.post', [
            'title' => $post ? Str::limit($post->content ?: 'SocialEaz post', 90) : 'SocialEaz post',
            'description' => $post ? Str::limit($post->content ?: '', 200) : null,
            'media' => $post?->media->first(),
        ]);
    }

    /**
     * The relation set every preview() query needs, factored out so the
     * "requested post" lookup and the "rest of its group" lookup can't
     * silently drift apart.
     */
    private function postWithPreviewRelations()
    {
        return Post::with([
            'user',
            'socialAccount',
            'media',
            'postComments' => function ($query) {
                $query->topLevel()->with('replies');
            },
        ]);
    }

    /**
     * Instagram's comment count often arrives before the comments
     * themselves are fetched/stored, so the preview backfills them lazily
     * the first time a given Instagram post is actually opened.
     */
    private function refreshInstagramCommentsIfNeeded(Post $post): void
    {
        if (
            strtolower($post->platform) === 'instagram'
            && $post->postComments->isEmpty()
            && (int) ($post->comments ?? 0) > 0
        ) {
            $this->instagramService->fetchComments($post);

            $post->load(['postComments' => function ($query) {
                $query->topLevel()->with('replies');
            }]);
        }
    }

    /**
     * Publish a reply to an existing comment on its connected platform and
     * store the resulting reply, used by the preview page's comment threads.
     */
    public function storeReply(Request $request, PostComment $comment)
    {
        $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        if (!$comment->post || $comment->post->user_id !== Auth::id()) {
            abort(403);
        }

        // Seeded/demo comments have no real counterpart on the platform, so
        // there's nothing to publish a live reply to — just store it locally.
        if (str_starts_with((string) $comment->comment_id, 'seed_')) {
            $reply = PostComment::create([
                'platform' => $comment->platform,
                'comment_id' => 'seed_' . Str::random(12),
                'parent_comment_id' => $comment->id,
                'social_account_id' => $comment->social_account_id,
                'post_id' => $comment->post_id,
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name ?? 'Support',
                'content' => $request->input('content'),
                'is_reply' => true,
                'sender_type' => 'support',
                'status' => 'approved',
                'posted_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'reply' => $this->formatCommentNode($reply),
            ]);
        }

        $service = $this->resolvePlatformService($comment->platform);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => "Unsupported platform: {$comment->platform}",
            ], 422);
        }

        $result = $service->publishComment(['body' => $request->input('content')], $comment);

        if (!($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to publish reply.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'reply' => $this->formatCommentNode($result['data']),
        ]);
    }

    /**
     * Store a new top-level comment on a post, authored by the business.
     * Saved locally only — no platform has a "comment on a post" API wired
     * up yet (only replying to an existing comment is supported).
     */
    public function storeComment(Request $request, Post $post)
    {
        $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        if ($post->user_id !== Auth::id()) {
            abort(403);
        }

        $comment = PostComment::create([
            'platform' => $post->platform,
            'comment_id' => 'seed_' . Str::random(12),
            'parent_comment_id' => null,
            'social_account_id' => $post->social_account_id,
            'post_id' => $post->id,
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name ?? 'Support',
            'content' => $request->input('content'),
            'is_reply' => false,
            'sender_type' => 'support',
            'status' => 'approved',
            'posted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'comment' => $this->formatCommentNode($comment),
        ]);
    }

    /**
     * Map a post/comment's platform key to its publishing service.
     */
    private function resolvePlatformService(string $platform)
    {
        return match (strtolower($platform)) {
            'facebook' => $this->metaService,
            'instagram' => $this->instagramService,
            'google' => $this->googleService,
            'youtube' => $this->youtubeService,
            'x' => $this->xService,
            'linkedin' => $this->linkedinService,
            'tiktok' => $this->tiktokService,
            default => null,
        };
    }

    /**
     * Resolve a post's media-driven display type.
     */
    private function resolvePostType(Post $post): string
    {
        $mediaCount = $post->media->count();

        if ($mediaCount > 1) {
            return 'carousel';
        }

        if ($mediaCount === 1) {
            return $post->media->first()->media_type === 'video' ? 'video' : 'image';
        }

        return 'text';
    }

    /**
     * Shape a post + its relations into the format the Vue dashboard/preview expect.
     */
    /**
     * @param array|null $platforms Every platform this post's quick-post
     *   batch was published to (from platformsForGroups()) - one entry per
     *   {platform_key, status, post_id, post_url}. Falls back to a single
     *   entry built from $post itself for callers (e.g. show()) that never
     *   grouped, so this stays a safe default rather than a breaking change.
     */
    private function formatPostSummary(Post $post, ?array $platforms = null): array
    {
        $type = $this->resolvePostType($post);
        $primaryMedia = $post->media->first();

        $platforms ??= [[
            'platform_key'     => $post->platform,
            'status'           => ucfirst($post->status ?? 'draft'),
            'post_id'          => $post->id,
            'post_url'         => $post->post_url,
            'account_name'     => $post->socialAccount->name ?? $post->socialAccount->username ?? null,
            'account_username' => $post->socialAccount->username ?? null,
            'account_avatar'   => $post->socialAccount->avatar_url ?? null,
        ]];

        return [
            'id' => $post->id,
            'platform_key' => $post->platform,
            'platforms' => $platforms,
            'type' => $type,
            'status' => ucfirst($post->status ?? 'draft'),
            'title' => $post->title ?: (Str::limit($post->content ?? '', 60) ?: 'Untitled Post'),
            'content' => $post->content,
            'image' => in_array($type, ['image', 'carousel']) ? ($primaryMedia->media_url ?? null) : null,
            'thumbnail' => $type === 'video' ? ($primaryMedia->thumbnail_url ?? null) : null,
            'video' => $type === 'video' ? ($primaryMedia->media_url ?? null) : null,
            'likes' => (int) ($post->likes ?? 0),
            'comments' => (int) ($post->comments ?? 0),
            'shares' => (int) ($post->shares ?? 0),
            'views' => (int) ($post->views ?? 0),
            'author' => $post->user->name ?? 'You',
            'created_at' => optional($post->created_at)->format('Y-m-d'),
            'account_name' => $post->socialAccount->name ?? $post->socialAccount->username ?? null,
            'account_handle' => $post->socialAccount->username ? '@' . $post->socialAccount->username : null,
        ];
    }

    /**
     * Shape a single comment (and its replies) for the preview page.
     */
    private function formatCommentNode(PostComment $comment): array
    {
        return [
            'id' => $comment->id,
            'author' => $comment->user_name ?: ($comment->user->name ?? 'User'),
            'content' => $comment->content,
            'timeAgo' => optional($comment->posted_at ?? $comment->created_at)->diffForHumans(),
            'likes' => (int) ($comment->likes ?? 0),
            'isOwn' => $comment->sender_type === 'support',
            'replies' => $comment->replies->map(fn ($reply) => [
                'author' => $reply->user_name ?: ($reply->user->name ?? 'User'),
                'content' => $reply->content,
                'timeAgo' => optional($reply->posted_at ?? $reply->created_at)->diffForHumans(),
                'likes' => (int) ($reply->likes ?? 0),
                'isOwn' => $reply->sender_type === 'support',
            ])->values(),
        ];
    }

    /**
     * Shape a post for the dedicated preview page, including engagement + comments.
     */
    private function formatPostForPreview(Post $post): array
    {
        $summary = $this->formatPostSummary($post);

        $summary['platform_url'] = $post->platform_url;

        $summary['engagement'] = [
            'reactionsTotal' => (int) ($post->likes ?? 0),
            'commentsCount' => (int) ($post->comments ?? 0),
            'sharesCount' => (int) ($post->shares ?? 0),
            'viewsCount' => (int) ($post->views ?? 0),
            'impressions' => (int) ($post->impressions ?? 0),
            'bookmarks' => (int) ($post->saves ?? 0),
            'comments' => $post->postComments->map(fn ($comment) => $this->formatCommentNode($comment))->values(),
        ];

        return $summary;
    }

    /**
     * The redesigned Vue Create Post page (PostComposer.vue) - reuses
     * exactly the account/category queries create() already runs, minus
     * the scheduled-posts/user-media data that page's own calendar/
     * media-library sidebar needs and this one doesn't. Submits to the
     * same store() below.
     */
    public function composer()
    {
        $userId = Auth::id();

        $categories = PostCategory::where('user_id', $userId)
            ->orderBy('id', 'desc')
            ->get();

        $accounts = SocialAccount::whereUserId($userId)->where('has_posting_permission', true)->get();

        return view('admin.posts.composer', compact('categories', 'accounts'));
    }

    /**
     * AI Content Assistant (PostComposer.vue / AiAssistantPanel.vue) -
     * proxies to Google Gemini so the API key only ever lives server-side.
     * The previous attempt at this (askOpenAI() above, despite the name)
     * depended on a $nanoBananaAI service that's never actually
     * constructed (see the commented-out assignment in __construct()) and
     * was never routed - this is a fresh, working implementation, not a
     * fix to that one.
     *
     * response_schema forces Gemini to return exactly {title, description,
     * hashtags[]} as real JSON (response_mime_type: application/json) -
     * no regex/parseGeminiResponse()-style scraping of free-form text
     * needed, unlike the old attempt.
     *
     * Model: gemini-3.6-flash, not gemini-1.5-flash - verified live against
     * this app's real API key that 1.5-flash 404s ("no longer available to
     * new users"), and 2.5-flash 404s the same way; Google's own error
     * response for 1.5-flash names 3.6-flash as the replacement, and it's
     * been confirmed working (including with this exact response_schema)
     * over several real calls. Worth re-checking against
     * generativelanguage.googleapis.com/v1beta/models?key=... (list
     * models) if this starts 404ing again in the future - Google has
     * retired every "flash" version tried before this one.
     */
    public function generateAiContent(Request $request)
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
        ]);

        $apiKey = adminSetting('gemini_api_key_free');

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Gemini API key is missing in admin settings.',
            ], 422);
        }

        // PHP's own max_execution_time (30s in this environment) is a
        // wall-clock budget for the WHOLE request, separate from and not
        // reset by Http::timeout() below - it does not pause for HTTP I/O
        // or for the Sleep::for() calls retry() makes between attempts.
        // Confirmed live: 3 retry attempts against a real 429, using
        // Gemini's own retryDelay values (1s, then 10s) plus the HTTP
        // calls themselves, totalled just over 30s and PHP force-killed
        // the script mid-request (storage/logs/laravel.log: "Maximum
        // execution time of 30 seconds exceeded", a FatalError thrown
        // from vendor Sleep.php - not catchable by this method's own
        // try/catch-free error handling below, so it rendered as a raw
        // branded 500 page instead of the intended graceful 422 JSON).
        // Raising the budget for THIS request only (not php.ini/global)
        // gives the retry loop room to actually finish. Paired with the
        // capped per-attempt timeout and capped retry delay below, the
        // realistic worst case (3 attempts x 15s + 2 delays x 12s) is
        // ~69s, comfortably inside this 120s budget.
        set_time_limit(120);

        $response = Http::timeout(15)
            ->retry(3, fn ($attempt, $exception) => RetryPolicy::delayMs($attempt, $exception), fn ($exception) => RetryPolicy::isRetryable($exception), throw: false)
            ->post(
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=' . $apiKey,
                [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => 'Write a social media post based on this request: "' . $validated['prompt'] . '". '
                                    . 'Keep the description punchy and platform-neutral (no more than a couple of short '
                                    . 'paragraphs), and give 5-10 relevant hashtags with no spaces and no leading text.'],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'response_mime_type' => 'application/json',
                        'response_schema' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'title' => ['type' => 'STRING', 'description' => 'A short, engaging post headline, under 100 characters.'],
                                'description' => ['type' => 'STRING', 'description' => 'The main post body, adapted for social media.'],
                                'hashtags' => [
                                    'type' => 'ARRAY',
                                    'items' => ['type' => 'STRING'],
                                    'description' => '5 to 10 relevant hashtags, each starting with # and containing no spaces.',
                                ],
                            ],
                            'required' => ['title', 'description', 'hashtags'],
                        ],
                    ],
                ]
            );

        if (!$response->successful()) {
            Log::warning('Gemini AI content generation failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $response->json('error.message') ?? 'Failed to generate content - please try again.',
            ], 422);
        }

        // candidates[0].content.parts[0].text is a JSON *string* (that's
        // what response_mime_type: application/json actually guarantees -
        // the response TEXT is valid JSON, not that Gemini's own envelope
        // around it changes shape), so it needs a second json_decode().
        $rawText = $response->json('candidates.0.content.parts.0.text');
        $data = $rawText ? json_decode($rawText, true) : null;

        if (!is_array($data) || !isset($data['title'], $data['description'], $data['hashtags'])) {
            Log::warning('Gemini AI content generation returned an unexpected shape.', ['raw' => $rawText]);

            return response()->json([
                'success' => false,
                'message' => 'Gemini returned an unexpected response - please try again.',
            ], 422);
        }

        // Confirmed live over several real calls: Gemini follows the
        // "no spaces" instruction in the schema description most of the
        // time, but not always (one real response came back with a
        // hashtag like "#Entrepreneurship 创业" - stray text appended with
        // a space) - the schema constrains the JSON *shape*, not the
        // content of each string, so this can't be relied on alone.
        // Whitespace is stripped (not the whole item dropped) so a mostly-
        // good hashtag isn't thrown away over one stray trailing word.
        $hashtags = collect((array) $data['hashtags'])
            ->map(fn ($tag) => preg_replace('/\s+/u', '', (string) $tag))
            ->filter()
            ->map(fn ($tag) => str_starts_with($tag, '#') ? $tag : '#' . $tag)
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'title' => $data['title'],
                'description' => $data['description'],
                'hashtags' => $hashtags,
            ],
        ]);
    }

    /**
     * AI Image generation (AiAssistantPanel.vue's "Generate Image" card) -
     * Cloudflare Workers AI, same "credentials never leave the server"
     * shape as generateAiContent() above.
     *
     * Response envelope verified live against this app's real account:
     * {success, errors[], result: {image: "<base64>"}} - success is a
     * real field to check, not just the HTTP status. Confirmed live that
     * this model's own NSFW filter can false-positive on a completely
     * benign prompt ("a simple red circle on white background" got flagged
     * once, then succeeded immediately after on retry with no prompt
     * change) - errors[0].message is surfaced as-is rather than a generic
     * "failed" message, since for this specific failure mode the real
     * Cloudflare message ("Input prompt contains NSFW content") is
     * actionable in a way a generic one wouldn't be (try rephrasing,
     * rather than assume something is broken).
     *
     * result.image has no MIME type in the envelope - every response
     * checked live decoded to a JPEG (the /9j/ base64 signature is
     * flux-1-schnell's actual default output format), so it's uploaded
     * as .jpg rather than sniffed.
     */
    public function generateAiImage(Request $request)
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
        ]);

        $accountId = adminSetting('cloudflare_account_key');
        $apiToken = adminSetting('cloudflare_api_key');

        if (empty($accountId) || empty($apiToken)) {
            return response()->json([
                'success' => false,
                'message' => 'Cloudflare API credentials are not configured in admin settings.',
            ], 422);
        }

        $response = Http::timeout(60)->withToken($apiToken)->post(
            "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/@cf/black-forest-labs/flux-1-schnell",
            [
                'prompt' => $validated['prompt'],
                'steps' => 4,
            ]
        );

        $json = $response->json();

        if (!$response->successful() || !($json['success'] ?? false) || empty($json['result']['image'])) {
            Log::warning('Cloudflare AI image generation failed.', [
                'status' => $response->status(),
                'errors' => $json['errors'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => $json['errors'][0]['message'] ?? 'Failed to generate an image - please try again.',
            ], 422);
        }

        $imageBinary = base64_decode($json['result']['image']);
        $filename = 'ai-image/' . uniqid() . '.jpg';

        Storage::disk('r2')->put($filename, $imageBinary, 'public');

        return response()->json([
            'success' => true,
            'data' => [
                // image_url is kept for anything that wants a permanent,
                // shareable link, but the frontend doesn't fetch() it -
                // cdn.socialeaz.com (the R2 custom domain) sends no
                // Access-Control-Allow-Origin header, so a cross-origin
                // fetch from syncora.test (or any admin domain) is blocked
                // by the browser's CORS policy - confirmed live, not
                // theoretical. image_data_uri carries the same bytes
                // already decoded server-side, so PostComposer.vue can
                // build a File from it with zero extra network request -
                // no CORS surface at all, rather than depending on an R2
                // bucket CORS config change outside this codebase.
                'image_url' => Storage::disk('r2')->url($filename),
                'image_data_uri' => 'data:image/jpeg;base64,' . $json['result']['image'],
            ],
        ]);
    }

    public function create(Request $request)
    {
        $userId = Auth::id();
        $socialPlatform = $request->platform ?? session('platform');
    
        $categories = PostCategory::where('user_id', $userId)
            ->withCount(['posts' => function ($q) {
                $q->where('status', 'published');
            }])
            ->orderBy('id', 'desc')
            ->get();
    
        // has_posting_permission excludes ad-account-only rows (eg. a
        // Facebook act_... Ad Account) - this list feeds the "select a
        // page to post to" picker below, which an ad account can never be.
        $accounts = SocialAccount::whereUserId($userId)->where('has_posting_permission', true)->get();

        // Fetch scheduled posts (past and future) for this platform
        $scheduledPosts = Post::where('user_id', $userId)
            // ->where('platform', $platform)
            ->where('status', 'scheduled')
            ->orderBy('schedule_at', 'asc')
            ->get();
    
        // Fetch all media belonging to the user (across all posts)
        $userMedia = PostMedia::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    
        return view('admin.posts.create', compact('categories', 'accounts', 'socialPlatform', 'scheduledPosts', 'userMedia'));
    }

    public function store(PostRequest $request)
    {
        $userId = Auth::id();
        $validated = $request->validated();

        $results = [];
        $errors = [];
    
        if (!empty($validated['platforms'])) {
    
            try {
                DB::transaction(function () use (
                    $validated,
                    $userId,
                    &$results,
                    &$errors
                ) {
    
                    foreach ($validated['platforms'] as $platform) {
    
                        // has_posting_permission is a second safety net here
                        // (the create page already only offers posting-
                        // permitted accounts as checkboxes) so a tampered
                        // request can't slip an ad-account id through
                        // selected_pages and have it treated as postable.
                        $pages = SocialAccount::where([
                            'user_id' => $userId,
                            'platform' => $platform,
                            'has_posting_permission' => true,
                        ])->whereIn(
                            'id',
                            $validated['selected_pages'][$platform] ?? []
                        )->get();
                   
                        if ($pages->isEmpty()) {
                            $errors[] = [
                                'message' => "No pages found for platform: {$platform}"
                            ];
    
                            throw new \Exception("No pages found");
                        }
    
                        switch ($platform) {
    
                            case 'facebook':
                                $response = $this->metaService->store($validated, $pages);
                                break;
    
                            case 'instagram':
                                $response = $this->instagramService->store($validated, $pages);
                                break;
    
                            case 'google':
                                $response = $this->googleService->store($validated, $pages);
                                break;
    
                            case 'youtube':
                                $response = $this->youtubeService->store($validated, $pages);
                                break;
    
                            case 'x':
                                $response = $this->xService->store($validated, $pages);
                                break;
    
                            case 'linkedin':
                                $response = $this->linkedinService->store($validated, $pages);
                                break;
    
                            case 'tiktok':
                                $response = $this->tiktokService->store($validated, $pages);
                                break;

                            case 'whatsapp':
                                $response = $this->whatsappService->store($validated, $pages);
                                break;

                            case 'threads':
                                $response = $this->threadsService->store($validated, $pages);
                                break;

                            case 'pinterest':
                                $response = $this->pinterestService->store($validated, $pages);
                                break;

                            default:
                                $errors[] = [
                                    'message' => "Unsupported platform: {$platform}"
                                ];
    
                                throw new \Exception("Unsupported platform");
                        }
    
                        if (!$response['success']) {
                            DB::rollback();
                            if (isset($response['errors']) && is_array($response['errors'])) {
                                foreach ($response['errors'] as $error) {
                                    $errors[] = $error;
                                }
                            } else {
                                $errors[] = [
                                    'page_id'   => $pages->first()->external_id ?? null,
                                    'page_name' => $pages->first()->page_name ?? $pages->first()->name ?? null,
                                    'message'   => $response['message'] ?? 'Unknown error occurred'
                                ];
                            }
    
                            // This triggers automatic rollback
                            throw new \Exception($response['message'] ?? 'Publishing failed');
                        }
    
                        $results[] = $response['data'] ?? $response['results'] ?? [];
                    }
                });

                DB::commit();
    
            } catch (\Throwable $e) {
    
                return response()->json([
                    'success' => false,
                    'errors' => $errors
                ], 422);
            }
        }
    
        return response()->json([
            'success' => true,
            'message' => 'Posts published successfully!',
            'results' => $results,
            'redirect_url' => route('admin.posts.dashboard')
        ]);
    }

    /**
     * Uploads a quick-post's media to R2 exactly once, shared across every
     * platform the post is published to. Every *PostService::store()
     * previously called its own uploadMediaToS3() independently, so one
     * quick post to N platforms wrote N duplicate copies of the same file
     * under N platform-namespaced paths. This mirrors
     * MetaPostService::uploadMediaToS3()'s logic/return shape
     * (['success' => bool, 'media' => [[...]]]) so it's a drop-in
     * replacement wherever a service checks $data['uploaded_media'], but
     * writes under a platform-neutral uploads/quick/media/ path instead.
     *
     * @param \Illuminate\Http\UploadedFile[] $files
     */
    private function uploadQuickPostMedia(array $files): array
    {
        try {
            $media = [];

            foreach ($files as $file) {
                $extension = strtolower($file->getClientOriginalExtension());
                $mimeType  = $file->getMimeType();
                $fileSize  = $file->getSize();
                $fileName  = time() . '_' . uniqid() . '.' . $extension;

                $s3Path = "uploads/quick/media/{$fileName}";

                Storage::disk('r2')->put(
                    $s3Path,
                    file_get_contents($file->getRealPath()),
                    ['visibility' => 'public']
                );

                $url = Storage::disk('r2')->url($s3Path);

                $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'avif'];
                $videoExtensions = ['mp4', 'mov', 'avi', 'wmv', 'mkv', 'webm', 'm4v'];

                $mediaType = 'file';

                if (in_array($extension, $imageExtensions)) {
                    $mediaType = $extension === 'gif' ? 'gif' : 'image';
                }

                if (in_array($extension, $videoExtensions)) {
                    $mediaType = 'video';
                }

                $width = null;
                $height = null;
                $duration = null;

                if ($mediaType === 'image' || $mediaType === 'gif') {
                    $imageInfo = @getimagesize($file->getRealPath());

                    if ($imageInfo) {
                        $width = $imageInfo[0];
                        $height = $imageInfo[1];
                    }
                }

                if ($mediaType === 'video' && class_exists(\getID3::class)) {
                    $getID3 = new \getID3();
                    $info = $getID3->analyze($file->getRealPath());

                    $duration = isset($info['playtime_seconds'])
                        ? round($info['playtime_seconds'], 2)
                        : null;

                    $width = $info['video']['resolution_x'] ?? $width;
                    $height = $info['video']['resolution_y'] ?? $height;
                }

                $media[] = [
                    'success'        => true,
                    'media_type'     => $mediaType,
                    'file_name'      => $fileName,
                    'original_name'  => $file->getClientOriginalName(),
                    'extension'      => $extension,
                    'mime_type'      => $mimeType,
                    'file_size'      => $fileSize,
                    'file_size_mb'   => round($fileSize / 1024 / 1024, 2),
                    'width'          => $width,
                    'height'         => $height,
                    'duration_seconds' => $duration,
                    'thumbnail_url'  => null,
                    'alt_text'       => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                    'url'            => $url,
                    'path'           => $s3Path,
                ];
            }

            return [
                'success' => true,
                'media'   => $media,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Publish the Quick Post composer's post straight to every connected
     * account for each selected platform (no page/category picker in that UI).
     */
    public function quickStore(Request $request)
    {
        $userId = Auth::id();
        $quickPlatforms = ['facebook', 'instagram', 'x', 'linkedin', 'tiktok', 'youtube'];
        $scheduleMode = $request->boolean('schedule_mode');

        $validated = $request->validate([
            'content' => ['nullable', 'string', 'max:5000'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['required', 'string', 'in:' . implode(',', $quickPlatforms)],
            'media' => ['nullable', 'file', 'max:10240'],
            'schedule_mode' => ['nullable', 'boolean'],
            'schedule_at' => $scheduleMode
                ? ['required', 'date', 'after:' . now()->addMinutes(10)->toDateTimeString()]
                : ['nullable', 'date'],
        ], [
            'platforms.required' => 'Please select at least one platform.',
            'platforms.*.in' => 'That platform is not supported for quick posting.',
            'schedule_at.required' => 'Schedule date is required when scheduling is enabled.',
            'schedule_at.after' => 'Schedule date must be at least 10 minutes from now.',
        ]);

        if (trim((string) ($validated['content'] ?? '')) === '' && !$request->hasFile('media')) {
            return response()->json([
                'success' => false,
                'errors' => [['message' => 'Please write something or add a photo/video before posting.']],
            ], 422);
        }

        $category = PostCategory::firstOrCreate(
            ['user_id' => $userId, 'name' => 'Quick Posts']
        );

        $data = [
            'content' => $validated['content'] ?? '',
            'media' => $request->hasFile('media') ? [$request->file('media')] : [],
            'category_id' => $category->id,
            'schedule_mode' => $scheduleMode ? 1 : 0,
            'schedule_at' => $scheduleMode ? $validated['schedule_at'] : null,
            'expiry_mode' => 0,
            'expiry_at' => null,
            // Ties every platform's Post row from this submission together
            // so the listing can show them as one card - see
            // buildPostsQuery()'s COALESCE(group_id, id) grouping.
            'group_id' => (string) Str::uuid(),
        ];

        // Uploaded once here rather than by each platform's own
        // uploadMediaToS3() - see uploadQuickPostMedia()'s docblock.
        // $data['uploaded_media'] being set is what tells each service to
        // skip its own re-upload.
        if (!empty($data['media'])) {
            $uploadResult = $this->uploadQuickPostMedia($data['media']);

            if (!$uploadResult['success']) {
                return response()->json([
                    'success' => false,
                    'errors' => [['message' => $uploadResult['message'] ?? 'Failed to upload media.']],
                ], 422);
            }

            $data['uploaded_media'] = $uploadResult['media'];
        }

        $results = [];
        $errors = [];

        foreach ($validated['platforms'] as $platform) {

            // has_posting_permission excludes ad-account-only rows (eg. a
            // Facebook act_... Ad Account, a Google Ads customer) - those
            // share this same platform+user but can't receive a content
            // post, so without this filter quickStore() would try to
            // publish to them too.
            $pages = SocialAccount::where(['user_id' => $userId, 'platform' => $platform])
                ->where('has_posting_permission', true)
                ->get();

            if ($pages->isEmpty()) {
                $errors[] = ['message' => "No connected {$platform} account found. Connect one first."];
                continue;
            }

            switch ($platform) {
                case 'facebook':
                    $response = $this->metaService->store($data, $pages);
                    break;
                case 'instagram':
                    $response = $this->instagramService->store($data, $pages);
                    break;
                case 'youtube':
                    $response = $this->youtubeService->store($data, $pages);
                    break;
                case 'x':
                    $response = $this->xService->store($data, $pages);
                    break;
                case 'linkedin':
                    $response = $this->linkedinService->store($data, $pages);
                    break;
                case 'tiktok':
                    $response = $this->tiktokService->store($data, $pages);
                    break;
            }

            if (!$response['success']) {
                if (isset($response['errors']) && is_array($response['errors'])) {
                    foreach ($response['errors'] as $error) {
                        $errors[] = $error;
                    }
                } else {
                    $errors[] = ['message' => "{$platform}: " . ($response['message'] ?? 'Publishing failed')];
                }
                continue;
            }

            $results[$platform] = $response['data'] ?? $response['results'] ?? [];
        }

        if (empty($results)) {
            return response()->json([
                'success' => false,
                'errors' => $errors,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $scheduleMode ? 'Post scheduled successfully!' : 'Post published successfully!',
            'results' => $results,
            'errors' => $errors,
        ]);
    }

    public function destroy($postId)
    {
        $post = Post::with('socialAccount', 'postComments' ,'media')->find($postId);
        $error = [];
        if (!$post) {
            return response()->json([
                'success' => false,
                'data' => 'Post not found'
            ], 404);
        }

        try {
            switch ($post->platform) {
                case 'facebook':
                    $response = $this->metaService->destroy($post);
                    break;
                case 'instagram':
                   
                    $response = $this->instagramService->destroy($post);
                    break;
                case 'x':
                
                    $response = $this->xService->destroy($post);
                    break;
                case 'google':
            
                    $response = $this->googleService->destroy($post);
                    break;
                case 'youtube':
        
                    $response = $this->youtubeService->destroy($post);
                    break;
                // case 'tiktok':
    
                //     $response = $this->tiktokService->destroy($post);
                //     break;
                case 'linkedin':
                    $response = $this->linkedinService->destroy($post);
                    break;
                case 'whatsapp':
                    $response = $this->whatsappService->destroy($post);
                    break;
                case 'threads':
                    $response = $this->threadsService->destroy($post);
                    break;
                case 'pinterest':
                    $response = $this->pinterestService->destroy($post);
                    break;
                default:
                    return response()->json([
                        'success' => false,
                        'data' => "Unsupported platform: {$post->platform}"
                    ], 400);
            }
           
            // Check external API response
            if ((!$response || !$response['success']) && !in_array($response['status'], ['400', '404'])) {
                return response()->json([
                    'success' => false,
                    'data' => 'Failed to delete post from platform',
                    'error' => $response['message'] ?? $response['error']
                ], 500);
            }
      
            // Delete locally - reference-aware: a media_url can be shared
            // by other PostMedia rows (other platforms in the same quick-
            // post batch, or any other post that happens to reference the
            // same file), since PostController::uploadQuickPostMedia()
            // uploads once and every platform's Post/PostMedia rows point
            // at the same URL. Only delete from R2 once nothing else does.
            if (count($post->media)) {
                foreach ($post->media as $media) {
                    $stillReferenced = PostMedia::where('media_url', $media->media_url)
                        ->where('id', '!=', $media->id)
                        ->exists();

                    if ($stillReferenced) {
                        continue;
                    }

                    $path = parse_url($media->media_url, PHP_URL_PATH);
                    if ($path) {
                        $path = ltrim($path, '/');
                        Storage::disk('r2')->delete($path);
                    }
                }
            }
            $post->delete();
          
            return response()->json([
                'success' => true,
                'data' => 'Post deleted successfully'
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($postId, Request $request)
    {
        $post = Post::with([
            'user',
            'socialAccount',
           // 'category',
            'category',
            'media',
            'postComments' => function ($query) {
                // Fetch top-level parent comments and eager load nested replies with their authors
                $query->topLevel()->with(['replies.user', 'user']);
            }
        ])->findOrFail($postId);

        return view('admin.posts.show', compact('post'));
        // $post = Post::with('socialAccount')->findOrFail($postId);
        // $socialPlatform = $post->socialAccount->platform;

        // $post->load([
        //     'postComments',
        //     'category',
        // ]);

        // return view('admin.posts.show', compact(
        //     'post',
        //     'socialPlatform'
        // ));
    }

    /**
     * JSON summary for the "view post" popup the calendar opens when a day
     * with an existing post is clicked - a lighter version of show() (no
     * nested comment replies, no category) since this only needs to
     * render a quick preview, not the full posts.show page. The popup's
     * own "Open full post" link goes to the real show() page for anything
     * beyond that.
     *
     * Resolves the whole group_id family (same COALESCE(group_id, id)
     * grouping buildPostsQuery()/preview() use), not just the one post id
     * given - a quickStore() submission fanned out across Facebook and
     * Instagram is one logical post, and the popup should show both
     * platforms' accounts, stats, and status, not just whichever platform
     * happened to be clicked in the calendar.
     */
    public function quickView($postId)
    {
        $anchor = Post::where('user_id', Auth::id())->findOrFail($postId);
        $groupKey = $anchor->group_id ?? $anchor->id;

        $members = Post::with(['media', 'socialAccount'])
            ->where('user_id', Auth::id())
            ->where(function ($q) use ($groupKey) {
                $q->where('group_id', $groupKey)->orWhere('id', $groupKey);
            })
            ->get();

        $primary = $members->firstWhere('id', $anchor->id) ?? $members->first();

        return response()->json([
            'success' => true,
            'post' => [
                'id'            => $primary->id,
                'content'       => $primary->content,
                'schedule_mode' => (bool) $primary->schedule_mode,
                'schedule_at'   => $primary->schedule_at?->toIso8601String(),
                'published_at'  => $primary->published_at?->toIso8601String(),
                'created_at'    => $primary->created_at?->toIso8601String(),
                'media'         => $primary->media->map(fn ($m) => [
                    'type' => $m->media_type,
                    'url'  => $m->media_url,
                ]),
                'platforms'     => $members->map(fn ($m) => [
                    'post_id'         => $m->id,
                    'platform'        => $m->platform,
                    'status'          => $m->status,
                    'error_message'   => $m->error_message,
                    'post_url'        => $m->post_url,
                    'account_name'    => $m->socialAccount->name ?? $m->socialAccount->username ?? ucfirst($m->platform),
                    'account_username'=> $m->socialAccount->username ?? null,
                    'account_avatar'  => $m->socialAccount->avatar_url ?? null,
                    'stats' => [
                        'likes'       => (int) $m->likes,
                        'comments'    => (int) $m->comments,
                        'shares'      => (int) $m->shares,
                        'views'       => (int) $m->views,
                        'impressions' => (int) $m->impressions,
                        'reach'       => (int) $m->reach,
                    ],
                ])->values(),
                'edit_url'      => route('admin.posts.show', $primary->id),
            ],
        ]);
    }

    public function getTypePost(Request $request) {
        $query = Post::with(['media', 'socialAccount']) ->where('user_id', Auth::user()->id);
        $type = $request->type;

        switch ($type) {
            case 'scheduled':
                $query->where('schedule_mode', 1)
                    ->where('schedule_at', '<=', now());
                break;
        
            case 'published':
                $query->where('schedule_mode', 0);
                break;
        
            case 'failed':
                $query->where('status', 'failed');
                break;
        
            case 'pending':
                $query->where('status', 'pending');
                break;
        }
        
        $posts = $query->paginate(10);

        $categories = PostCategory::with(['socialAccount', 'posts'])->where([
            'user_id' => Auth::user()->id
        ])->orderBy('id', 'desc')->paginate(50);

        return view($this->_config['view'], compact('posts', 'categories', 'type'));
    }

    /**
     * Generate Content using Gemini with media analysis
     */
    public function askOpenAI(Request $request)
    {
        set_time_limit(300);
        try {
            $prompt = $request->input('prompt');
            $mediaFile = $request->file('media');
        
            $generateImage = $request->input('generate_image', false);
            $mediaDescription = $request->input('media_description', '');

            $imageData = null;
            $mimeType = null;

            // Process media file if provided
            if ($mediaFile) {
                $mimeType = $mediaFile->getMimeType();
                $imageData = base64_encode(file_get_contents($mediaFile->getRealPath()));
            }
           
            // Generate content with Gemini - using the new method that returns structured text AND image
            $response = $this->nanoBananaAI->generateSocialMediaContentWithImage(
                $prompt,
                $imageData,
                $mimeType,
                $mediaDescription
            );
        //     $logos = $this->nanoBananaAI->generateImage($prompt, '16:9', $imageData, $mimeType);
        //     $response = $this->nanoBananaAI->generate($prompt, $imageData, $mimeType);
           // $response = $this->nanoBananaAI->generateImage($prompt, '16:9', $imageData, $mimeType);
      
            // Initialize parsed data
            $parsed = [
                'title' => '',
                'description' => '',
                'hashtags' => '',
                'media_type' => 'image',
                'media_description' => '',
                'media_prompt' => '',
                'media_analysis' => '',
                'generated_image_url' => null,
                'generated_image_data' => null,
                'generated_image_mime' => null
            ];
  
            // Extract all parts from response
            $parts = $this->nanoBananaAI->extractAllParts($response);
         
            $textContent = $parts['text'];
            $imageContent = $parts['image'];
            $imageMimeType = $parts['image_mime'];

            // Parse text content if available - this will extract Title, Description, Hashtags
            if ($textContent) {
                $parsedData = $this->parseGeminiResponse($textContent);
                $parsed = array_merge($parsed, $parsedData);
            }

            // If image was generated, upload to S3 and get URL
            if ($imageContent) {
                // Decode the base64 image data
                $imageBinaryData = base64_decode($imageContent);

                // Generate a unique filename
                $extension = $this->getExtensionFromMimeType($imageMimeType);
                $filename = 'ai-image/' . uniqid() . '.' . $extension;

                // Upload to S3
                Storage::disk('r2')->put($filename, $imageBinaryData, 'public');
                $imageUrl = Storage::disk('r2')->url($filename);
                
                $parsed['generated_image_url'] = $imageUrl;
                $parsed['generated_image_data'] = $imageContent;
                $parsed['generated_image_mime'] = $imageMimeType;
            }

            // If media was provided, return the media analysis
            if ($mediaFile) {
                $parsed['analyzed_media'] = [
                    'filename' => $mediaFile->getClientOriginalName(),
                    'mime_type' => $mediaFile->getMimeType(),
                    'size' => $mediaFile->getSize()
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $parsed
            ]);
        } catch (\Exception $e) {
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Parse Gemini response with media description
     */
    private function parseGeminiResponse($content)
    {
        $data = [
            'title' => '',
            'description' => '',
            'hashtags' => '',
            'media_type' => 'image',
            'media_description' => '',
            'media_prompt' => '',
            'media_analysis' => ''
        ];

        // If content is empty, return default values
        if (empty($content)) {
            return $data;
        }

        // Try to extract structured data
        // Extract Title
        if (preg_match('/Title:\s*(.+?)(?:\n|$)/i', $content, $matches)) {
            $data['title'] = trim($matches[1]);
        }

        // Extract Description - handles multiline content
        if (preg_match('/Description:\s*(.+?)(?:\nHashtags:|\nMedia:|\nMediaDescription:|\Z)/is', $content, $matches)) {
            $data['description'] = trim($matches[1]);
        }

        // Extract Hashtags
        if (preg_match('/Hashtags:\s*(.+?)(?:\nMedia:|\nMediaDescription:|\Z)/is', $content, $matches)) {
            $data['hashtags'] = trim($matches[1]);
        }

        // Extract Media Type
        if (preg_match('/Media:\s*(image|video)/i', $content, $matches)) {
            $data['media_type'] = strtolower(trim($matches[1]));
        }

        // Extract Media Description
        if (preg_match('/MediaDescription:\s*(.+?)(?:\nTitle:|\nDescription:|\nHashtags:|\nMedia:|\Z)/is', $content, $matches)) {
            $data['media_description'] = trim($matches[1]);
        }

        // If no structured data found, try to intelligently parse
        if (empty($data['title']) && empty($data['description'])) {
            // Try to find a title (first line or line with colon)
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && strlen($line) < 80 && !str_contains($line, '#')) {
                    $data['title'] = $line;
                    break;
                }
            }

            // Use remaining content as description
            if (empty($data['description'])) {
                $data['description'] = $content;
            }
        }

        // If no media description found, use a fallback
        if (empty($data['media_description']) && !empty($data['description'])) {
            $data['media_description'] = substr($data['description'], 0, 150) . '...';
        }

        // Generate image prompt from description
        if (!empty($data['description'])) {
            $data['media_prompt'] = $this->createImagePrompt($data);
        }

        // Add media analysis
        if (!empty($data['media_description'])) {
            $data['media_analysis'] = $data['media_description'];
        }

        return $data;
    }

    /**
     * Generate Image using Gemini (standalone)
     */
    public function generateGeminiImage(Request $request)
    {
        set_time_limit(300);
        try {
            $prompt = $request->input('prompt');
            $aspectRatio = $request->input('aspect_ratio', '1:1');
            
            // Truncate prompt if too long
            if (strlen($prompt) > 2000) {
                $prompt = substr($prompt, 0, 2000);
            }
            
            // Call Gemini to generate image
            $response = $this->nanoBananaAI->generateImage($prompt, $aspectRatio);
            
            // Check if response has image data
            if (isset($response['candidates'][0]['content']['parts'])) {
                foreach ($response['candidates'][0]['content']['parts'] as $part) {
                    if (isset($part['inlineData']) && isset($part['inlineData']['data'])) {
                        $imageData = $part['inlineData']['data'];
                        $mimeType = $part['inlineData']['mimeType'] ?? 'image/png';
                        
                        // Upload to S3
                        $imageBinaryData = base64_decode($imageData);
                        $extension = $this->getExtensionFromMimeType($mimeType);
                        $filename = 'ai-image/' . uniqid() . '.' . $extension;
                        
                        Storage::disk('r2')->put($filename, $imageBinaryData, 'public');
                        $imageUrl = Storage::disk('r2')->url($filename);
                        
                        return response()->json([
                            'success' => true,
                            'image_data' => $imageData,
                            'image_url' => $imageUrl,
                            'mime_type' => $mimeType
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'No image generated in response',
                'raw_response' => $response
            ], 500);

        } catch (\Exception $e) {
            \Log::error('generateGeminiImage Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get file extension from mime type
     */
    private function getExtensionFromMimeType($mimeType)
    {
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',
        ];

        return $mimeMap[$mimeType] ?? 'png';
    }

    /**
     * Create image generation prompt from content
     */
    private function createImagePrompt($data)
    {
        $prompt = "Create a professional social media post image for: ";

        if (!empty($data['title'])) {
            $prompt .= $data['title'] . ". ";
        }

        if (!empty($data['media_description'])) {
            $prompt .= "The image should show: " . $data['media_description'] . ". ";
        }

        if (!empty($data['description'])) {
            $prompt .= substr($data['description'], 0, 100) . ". ";
        }

        $prompt .= "Style: Clean, modern, professional. High quality. Social media ready.";

        return $prompt;
    }
}