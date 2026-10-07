<?php

use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRoutes;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use App\Http\Controllers\Admin\AdController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\PostAccountController;
use App\Http\Controllers\Admin\SocialAccountController;
use App\Http\Controllers\Admin\ConnectionHubController;
use App\Http\Controllers\Admin\ChatController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\MessageChannelController;
use App\Http\Controllers\Admin\PostCommentController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\AdminAPIController;
use App\Http\Controllers\Admin\AdCampaignController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\PostCategoryController;
use App\Http\Controllers\Admin\EmailMarketingController;
use App\Http\Controllers\Admin\EmailSetupController;
use App\Http\Controllers\Admin\EmailSegmentController;
use App\Http\Controllers\Admin\EmailListController;
use App\Http\Controllers\Admin\EmailSubscriberController;
use App\Http\Controllers\Admin\EmailTemplateController;
use App\Http\Controllers\Admin\EmailCampaignController;
use App\Http\Controllers\EmailUnsubscribeController;
use App\Http\Controllers\Admin\IntegrationController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\HelpCenterController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\KnowledgeBaseController;
use App\Http\Controllers\Admin\MediaGalleryController;
use App\Http\Controllers\Admin\CopilotController;
use App\Http\Controllers\Admin\AiCopilotSettingController;
use App\Http\Controllers\Admin\BusinessProfileController;
use App\Http\Controllers\Admin\KnowledgeGapController;
use App\Http\Controllers\Admin\AiCopilotAnalyticsController;

Route::get('/payments/tamara/return/{order}', [\App\Http\Controllers\Payments\TamaraController::class, 'returned'])
    ->middleware(['auth', 'signed:orderId,status'])->name('payments.tamara.return');

Route::get('payment/tamara/process', 'WalletController@TamaraCallback')->name('tamara.checkout-status'); //tamara_payment
Route::group(['prefix' => LaravelLocalization::setLocale(), 'middleware' => [
	'web',
	LaravelLocalizationRoutes::class,
	LocaleSessionRedirect::class,
	LocaleCookieRedirect::class,
	LaravelLocalizationRedirectFilter::class,
	LaravelLocalizationViewPath::class,
]], function () {





	/*
	|--------------------------------------------------------------------------
	| Website
	|--------------------------------------------------------------------------
	*/

	Route::view('/', 'front.pages.home_modern')->name('home');

	Route::get('/product', function () {
		return view('front.pages.product');
	})->name('product');


	// These are sections on the homepage itself (no standalone
	// product.ai-copilot / product.channels / product.tools views exist),
	// so route to the matching in-page anchor instead of a 404.
	Route::get('/ai-copilot', function () {
		return redirect(route('home') . '#ai-copilot');
	})->name('ai-copilot');


	Route::get('/channels', function () {
		return redirect(route('home') . '#channels');
	})->name('channels');


	Route::get('/tools', function () {
		return redirect(route('home') . '#tools');
	})->name('tools');


	/*
	|--------------------------------------------------------------------------
	| Pricing
	|--------------------------------------------------------------------------
	*/

	Route::get('/pricing', function () {
		return view('front.pages.pricing', ['plans' => \App\Models\Bundle::where('is_active', true)->where('is_free', false)->orderBy('sort_order')->get()]);
	})->name('pricing');


	/*
	|--------------------------------------------------------------------------
	| Company
	|--------------------------------------------------------------------------
	*/

	Route::get('/about', function () {
		return view('about');
	})->name('about');


	Route::get('/contact', function () {
		return view('contact');
	})->name('contact');


	/*
	|--------------------------------------------------------------------------
	| Resources
	|--------------------------------------------------------------------------
	*/

	Route::get('/guides', function () {
		return view('guides');
	})->name('guides');


	Route::get('/help', function () {
		return view('help');
	})->name('help');


	Route::get('/api', function () {
		return view('api');
	})->name('api');


	/*
	|--------------------------------------------------------------------------
	| Legal
	|--------------------------------------------------------------------------
	*/

	Route::view('/privacy', 'front.pages.privacy')->name('privacy');

	Route::view('/terms', 'front.pages.terms')->name('terms');


	/*
	|--------------------------------------------------------------------------
	| Public post share preview - deliberately OUTSIDE the auth group below.
	| Snap's Creative Kit share flow (and any other social share button)
	| fetches this URL's og and snapchat meta tags server-side, with no
	| session cookie - if this required login it would just see a redirect
	| to /login and the share would show no image/caption at all. Only
	| exposes a post's own public-facing content (caption + first media
	| item), nothing account/owner-identifying.
	|--------------------------------------------------------------------------
	*/
	Route::get('share/posts/{post}', [PostController::class, 'sharePreview'])->name('posts.share');


	/*Route::view('/about', 'front.pages.about');
	Route::view('/services', 'front.pages.services');
	Route::get('/r2-upload', [\App\Http\Controllers\R2Controller::class, 'index']);
	Route::post('/r2-upload', [\App\Http\Controllers\R2Controller::class, 'upload'])->name('r2.upload');*/


	Route::middleware(['auth', 'active.user'])->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'dashboard'])->name('dashboard');

        Route::middleware('role:admin')->group(function () {
            Route::resource('employees', \App\Http\Controllers\Team\EmployeeController::class)->except(['show', 'destroy']);
            Route::resource('plans', \App\Http\Controllers\Team\PlanController::class)->except(['show', 'destroy']);
        });
        Route::get('/subscribers', [\App\Http\Controllers\Team\SubscriberController::class, 'index'])
            ->middleware('role:admin|customer_support')->name('subscribers.index');
        // Team\TicketController (SupportTicket model) was a second, fully
        // parallel ticket implementation duplicating Admin\TicketController
        // (Ticket model, routes/web.php's 'support/tickets' group below) -
        // consolidated onto the latter (the audited, tested, actively-used
        // one) rather than maintaining two. A plain redirect covers old
        // bookmarks/muscle memory for the list/create pages; /tickets/{id}
        // and /tickets/{id}/replies are NOT redirected, since SupportTicket
        // and Ticket ids are different, unrelated sequences - a blind id
        // redirect there could point at the wrong ticket entirely.
        Route::redirect('/tickets', '/support/tickets');
        Route::redirect('/tickets/create', '/support/tickets/create');


		/*
		|--------------------------------------------------------------------------
		| DASHBOARD (NO SUBSCRIPTION REQUIRED)
		|--------------------------------------------------------------------------
		*/
		Route::middleware('seller')
			->name('admin.')
			->group(function () {
				/*
		|--------------------------------------------------------------------------
		| SUBSCRIPTION FLOW (ALWAYS ACCESSIBLE)
		|--------------------------------------------------------------------------
		*/
				// Sellers can manage plans without an active subscription.
				Route::get('/subscription/select', [SubscriptionController::class, 'select'])->name('subscription.select');
				Route::get('/subscription/plans', [SubscriptionController::class, 'plans']);
				Route::get('/subscription/checkout', [SubscriptionController::class, 'showCheckout'])->name('subscription.checkout');
				Route::post('/subscription/checkout', [SubscriptionController::class, 'checkoutProcess'])->name('subscription.checkout.process');
				Route::get('/subscription/checkout-data', [SubscriptionController::class, 'checkoutData'])->name('subscription.checkout.data');

			/*	Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout'])
					->name('subscription.checkout.process');*/
				Route::post('/subscription/activate', [SubscriptionController::class, 'activate']);
				Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel']);

				Route::view('/dashboard/crm', 'admin.crm-dashboard')
					->name('crm-dashboard');

				/*
				|--------------------------------------------------------------------------
				| SUPPORT: HELP CENTER (every seller's read-only window onto System FAQs)
				|--------------------------------------------------------------------------
				| Deliberately outside the ->middleware(['subscription']) group below -
				| a seller whose subscription lapsed should still be able to reach
				| support. See HelpCenterController's docblock.
				*/
				Route::get('help-center', [HelpCenterController::class, 'index'])->name('help-center.index');
				// Level-1 "Ask AI" search over System FAQs - see
				// AiCopilotService::findBestSystemMatch()/HelpCenterController::
				// askAi() docblocks.
				Route::post('help-center/ask-ai', [HelpCenterController::class, 'askAi'])->name('help-center.ask-ai');
			});

		/*
		|--------------------------------------------------------------------------
		| SUPPORT: SYSTEM FAQ MANAGEMENT (admin role only)
		|--------------------------------------------------------------------------
		| Reproduced live: this used to sit inside the 'seller'-only group
		| above. EnsureSeller requires hasRole('seller') && !isTeamMember(),
		| and isTeamMember() = hasRole('admin') || hasRole('customer_support')
		| - so ANY admin account fails EnsureSeller and got a 403 before ever
		| reaching FaqController::authorizeAdmin()'s own (correct)
		| hasRole('admin') check. Net effect: the System FAQ management
		| screens these routes serve were completely unreachable for the
		| only role they're built for, even though the sidebar link
		| (@if(hasRole('admin'))) and the controller's own guard were both
		| already correct in isolation. role:admin here matches the exact
		| pattern already used for Employees/Plans above - the controller's
		| authorizeAdmin() stays in place as defense in depth, this just
		| fixes which requests are even allowed to reach it.
		*/
		Route::middleware('role:admin')
			->name('admin.')
			->group(function () {
				Route::get('faqs', [FaqController::class, 'index'])->name('faqs.index');
				Route::post('faqs', [FaqController::class, 'store'])->name('faqs.store');
				Route::put('faqs/{faq}', [FaqController::class, 'update'])->name('faqs.update');
				Route::delete('faqs/{faq}', [FaqController::class, 'destroy'])->name('faqs.destroy');
				Route::post('faqs/categories', [FaqController::class, 'storeCategory'])->name('faqs.categories.store');

				// Platform-wide API credentials (admin_settings - Gemini key,
				// every platform's client id/secret). Was in the seller +
				// subscription group: any subscribed seller could read/change
				// these global secrets, while admins (team members) were
				// blocked by EnsureSeller. Route names unchanged (admin.apis.*).
				Route::resource('/apis', AdminAPIController::class);
			});

		/*
		|--------------------------------------------------------------------------
		| SUPPORT: SYSTEM A TICKETS (Admin\TicketController) - every seller
		| can file/view their own; admin/customer_support can view and
		| manage all.
		|--------------------------------------------------------------------------
		| Same routing bug as the FAQ group above, reproduced live the same
		| way: sat inside the 'seller'-only group, so no admin/
		| customer_support account could ever reach it even though
		| Admin\TicketController::index()/show()/updateStatus() already
		| correctly branch on hasRole('admin') in code (dead code until
		| now). Moved to the exact role:admin|customer_support|seller
		| pattern already used for Team\TicketController's routes above -
		| the actual per-ticket ownership check
		| (abort_unless($user->hasRole('admin') || $ticket->user_id ===
		| $user->id, 403), in TicketController::show()/storeMessage())
		| still lives in the controller; this only fixes which roles can
		| reach the controller at all.
		|
		| URI deliberately 'support/tickets', not 'tickets' - the plain
		| 'tickets' URI is already claimed by Team\TicketController's
		| resource route above (the separate "file a ticket to Socialeaz
		| support" flow the main seller sidebar also links to via
		| route('tickets.index')) - route names here are unchanged
		| (admin.tickets.index etc), only the middleware moved.
		*/
		Route::middleware('role:admin|customer_support|seller')
			->name('admin.')
			->group(function () {
				Route::get('support/tickets', [TicketController::class, 'index'])->name('tickets.index');
				Route::get('support/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
				Route::post('support/tickets', [TicketController::class, 'store'])->name('tickets.store');
				Route::get('support/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
				Route::post('support/tickets/{ticket}/messages', [TicketController::class, 'storeMessage'])->name('tickets.messages.store');
				Route::patch('support/tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->name('tickets.status');
			});


		/*
		|--------------------------------------------------------------------------
		| PROTECTED SAAS MODULES (SUBSCRIPTION REQUIRED)
		|--------------------------------------------------------------------------
		*/
		Route::middleware(['seller', 'subscription'])
			->name('admin.')
			->group(function () {
				// MEDIA GALLERY - seller's reusable images/videos, shared by
				// the posts composer and the ads campaign forms. The raw
				// file stream (media-gallery.file) is registered outside
				// the localized group at the bottom of this file.
				// Navbar ⌘K search across the seller's own records.
				Route::get('search', \App\Http\Controllers\Admin\GlobalSearchController::class)->name('search');
				Route::get('media-gallery', [MediaGalleryController::class, 'index'])->name('media-gallery.index');
				Route::post('media-gallery', [MediaGalleryController::class, 'store'])->name('media-gallery.store');
				Route::delete('media-gallery/{mediaAsset}', [MediaGalleryController::class, 'destroy'])->name('media-gallery.destroy');

				// ADS
				Route::resource('/platform/ads', AdController::class);
				Route::get('ads/dashboard', [AdController::class, 'dashboard'])
					->name('ads.dashboard');

				Route::get('ads/{platform}/redirect', [AdController::class, 'redirect'])
					->name('ads.redirect');

				Route::get('ads/{platform}/callback', [AdController::class, 'callback'])->name('ads.platform.callback');

				// Registered ahead of the resource route below so this
				// literal segment isn't swallowed by the resource's
				// GET ads/{platform}/campaigns/{campaign} (show) route,
				// which would otherwise treat "create-new" as a campaign ID.
				Route::get('ads/{platform}/campaigns/create-new', [AdCampaignController::class, 'createNew'])
					->name('ads.campaigns.create_new');

				// Registered before the resource route so "sync" isn't
				// matched as GET/POST campaigns/{campaign}. "Sync Now" on
				// the platform campaigns dashboard.
				Route::post('ads/{platform}/campaigns/sync', [AdCampaignController::class, 'sync'])
					->name('ads.campaigns.sync');

				Route::resource('ads/{platform}/campaigns', AdCampaignController::class)
					->names('ads.campaigns');

				Route::patch('ads/{platform}/campaigns/{id}/status', [AdCampaignController::class, 'updateStatus'])
					->name('ads.campaigns.status');

				Route::get('ads/{platform}/identities', [AdCampaignController::class, 'identities'])
					->name('ads.identities');


				// INTEGRATIONS - pixels/analytics/AI/ads services with a
				// pasted ID or API key each (no OAuth - see
				// Integration::getCredentialFieldsAttribute()), scoped per
				// user like everything else here.
				Route::get('integrations', [IntegrationController::class, 'index'])
					->name('integrations.index');
				Route::post('integrations/{integration}', [IntegrationController::class, 'store'])
					->name('integrations.store');
				Route::delete('integrations/connections/{userIntegration}', [IntegrationController::class, 'destroy'])
					->name('integrations.destroy');


				// POSTS
				Route::get('posts/{platform}/redirect', [PostController::class, 'redirect'])
					->name('posts.redirect');
				Route::get('posts/dashboard', [PostController::class, 'dashboard'])->defaults('_config', ['view' => 'admin.posts.dashboard'])->name('posts.dashboard');
				Route::get('posts/listing', [PostController::class, 'index_vue'])->name('posts.index');
				Route::get('posts/data', [PostController::class, 'index'])->name('posts.data');
				Route::get('posts/{post}/preview/{platform}', [PostController::class, 'preview'])->name('posts.preview');
				Route::post('posts/quick', [PostController::class, 'quickStore'])->name('posts.quick');
				// New Vue-based Create Post page (PostComposer.vue) -
				// deliberately a separate route from admin.posts.create
				// (still fully intact below via Route::resource) rather
				// than replacing that page's Blade view outright - that
				// page is 2000+ lines with real, working pieces (the
				// WhatsApp Embedded Signup flow, for one) this redesign
				// doesn't attempt to carry over, and silently dropping
				// them wasn't part of what was asked. Submits to the same
				// admin.posts.store PostController::store() the legacy
				// page already uses.
				Route::get('posts/composer', [PostController::class, 'composer'])->name('posts.composer');
				Route::post('posts/generate-ai-content', [PostController::class, 'generateAiContent'])->name('posts.generate-ai-content');
				Route::post('posts/generate-ai-image', [PostController::class, 'generateAiImage'])->name('posts.generate-ai-image');
					Route::get('posts/{post}/quick-view', [PostController::class, 'quickView'])->name('posts.quick-view');
				Route::post('posts/listing/comments/{comment}/replies', [PostController::class, 'storeReply'])->name('posts.comments.reply');
				Route::post('posts/listing/{post}/comments', [PostController::class, 'storeComment'])->name('posts.comments.store');
				Route::get('posts', [PostController::class, 'dashboard']);
				// ->except(['index']) - an unrestricted Route::resource()
				// here auto-generates its own GET posts/index (named
				// posts.index, prefixed admin.posts.index by this group),
				// which is an exact duplicate of the intentional
				// posts.index above (posts/listing, PostController::
				// index_vue - the real Vue posts list page) and also
				// shadows the bare unnamed GET posts route right above
				// this line. Two routes with the identical final name
				// (admin.posts.index) isn't just "wrong one wins" -
				// php artisan route:cache throws a hard LogicException
				// and refuses to run at all with a real duplicate name,
				// confirmed live. create/store/show/edit/update/destroy
				// below are still genuinely used (see the comment near
				// posts/composer above) - only the accidental index
				// action is removed.
				Route::resource('posts', PostController::class)->except(['index']);
				Route::post('post-accounts/whatsapp', [PostAccountController::class, 'storeWhatsApp'])
					->name('post-accounts.whatsapp.store');
				Route::post('post-accounts/whatsapp/embedded', [PostAccountController::class, 'storeWhatsappEmbedded'])
					->name('post-accounts.whatsapp.embedded');

				Route::get('post-accounts/instagram/redirect', [PostAccountController::class, 'redirectInstagram'])
					->name('post-accounts.instagram.redirect');
				Route::get('post-accounts/instagram/callback', [PostAccountController::class, 'callbackInstagram'])
					->name('post-accounts.instagram.callback');
				Route::get('post-accounts/threads/redirect', [PostAccountController::class, 'redirectThreads'])
					->name('post-accounts.threads.redirect');
				Route::get('post-accounts/threads/callback', [PostAccountController::class, 'callbackThreads'])
					->name('post-accounts.threads.callback');
				// Was registered as 'post-accounts/tiktok/redirect' pointing
				// at redirectPinterest() - a copy/paste error. Since Laravel
				// dispatches to the first route matching a given method+URI,
				// and this was registered before the real TikTok redirect
				// route below, actually visiting /post-accounts/tiktok/redirect
				// in a browser silently ran Pinterest's redirect logic
				// instead - route('post-accounts.tiktok.redirect') (used to
				// build links/redirect_uri strings) still resolved correctly
				// by name, which is why this hid rather than erroring.
				Route::get('post-accounts/pinterest/redirect', [PostAccountController::class, 'redirectPinterest'])
					->name('post-accounts.pinterest.redirect');
				Route::get('post-accounts/pinterest/callback', [PostAccountController::class, 'callbackPinterest'])
					->name('post-accounts.pinterest.callback');
				Route::get('post-accounts/x/redirect', [PostAccountController::class, 'redirectX'])
					->name('post-accounts.x.redirect');
				Route::get('post-accounts/x/callback', [PostAccountController::class, 'callbackX'])
					->name('post-accounts.x.callback');
				Route::delete('post-accounts/{account}', [PostAccountController::class, 'destroy'])
					->name('post-accounts.destroy');
				// TikTok's real connect logic lives in SocialAuthService,
				// reached through SocialAccountController::redirect()/
				// callback() (see the "Unified combined-consent connect
				// flow" block below) - PostAccountController has never had
				// redirectTiktok()/callbackTiktok() methods. These two
				// routes were left pointing at those non-existent methods,
				// so hitting either 500'd with "Call to undefined method
				// PostAccountController::callbackTiktok()" - a real
				// production crash, since this callback URI
				// (SocialAuthService::callbackUrl()) is the exact
				// redirect_uri already registered with TikTok's Developer
				// Portal app, so every real TikTok connect attempt landed
				// here. The UI's "Connect TikTok" link already points at
				// admin.social-accounts.redirect directly, so
				// post-accounts.tiktok.redirect is effectively dead, but
				// it's routed correctly too rather than left as a second
				// landmine. Platform is bound via ->defaults() since
				// neither URI has a {platform} wildcard of its own.
				Route::get('post-accounts/tiktok/redirect', [SocialAccountController::class, 'redirect'])
					->name('post-accounts.tiktok.redirect')->defaults('platform', 'tiktok');
				Route::get('post-accounts/tiktok/callback', [SocialAccountController::class, 'callback'])
					->name('post-accounts.tiktok.callback')->defaults('platform', 'tiktok');
				// Unified combined-consent connect flow (posting + messaging +
				// ads scopes in one redirect) for Facebook, Google, LinkedIn,
				// and TikTok - the platforms whose OAuth model supports
				// requesting all three at once - see SocialAuthService. This
				// is now the ONLY connect route for these four platforms:
				// it replaced their separate post-accounts.*/messaging.auth.*
				// entries (removed below), since every account connected
				// through either used to upsert into the same social_accounts
				// row anyway. Every other platform keeps its existing
				// dedicated route, either because it has no combined-scope
				// option (TikTok Ads has its own separate OAuth app - see
				// ads.redirect) or because it's a genuinely different
				// product (Google Chat vs. YouTube/Business Profile).
				// CONNECTION HUB - connect each platform once for every
				// module (docs/connection-hub-design.md). Steps reuse the
				// callback URLs already registered with each provider.
				Route::get('connections', [ConnectionHubController::class, 'index'])
					->name('connections.index');
				Route::post('connections/wizard', [ConnectionHubController::class, 'startWizard'])
					->name('connections.wizard.start');
				Route::post('connections/wizard/skip', [ConnectionHubController::class, 'skipWizard'])
					->name('connections.wizard.skip');
				Route::delete('connections/wizard', [ConnectionHubController::class, 'finishWizard'])
					->name('connections.wizard.finish');
				Route::get('connections/{platform}/connect/{step}', [ConnectionHubController::class, 'connect'])
					->name('connections.connect');
				Route::patch('connections/assets/{socialAccount}', [ConnectionHubController::class, 'updateAsset'])
					->name('connections.assets.update');
				Route::post('connections/{connection}/check', [ConnectionHubController::class, 'check'])
					->name('connections.check');
				Route::delete('connections/{connection}', [ConnectionHubController::class, 'disconnect'])
					->name('connections.disconnect');
				Route::get('social-accounts/{platform}/redirect', [SocialAccountController::class, 'redirect'])
					->name('social-accounts.redirect');
				Route::get('social-accounts/{platform}/callback', [SocialAccountController::class, 'callback'])
					->name('social-accounts.callback');

				Route::resource('categories', PostCategoryController::class);


				// CHATS - unified messaging inbox (Facebook Messenger,
				// Instagram Direct, WhatsApp, Telegram, X DMs)
				Route::get('chats/dashboard', [ChatController::class, 'dashboard'])
					->name('chats.dashboard');
				Route::get('platform/chats/{conversation}', [ChatController::class, 'show'])
					->name('chats.show');
				Route::post('platform/chats', [ChatController::class, 'store'])
					->name('chats.store');
				Route::patch('platform/chats/{conversation}/read', [ChatController::class, 'markRead'])
					->name('chats.read');
				Route::delete('platform/chats/{conversation}', [ChatController::class, 'destroy'])
					->name('chats.destroy');
				Route::patch('platform/chats/messages/{message}', [ChatController::class, 'updateMessage'])
					->name('chats.messages.update');
				Route::delete('platform/chats/messages/{message}', [ChatController::class, 'destroyMessage'])
					->name('chats.messages.destroy');

				// AI COPILOT - Phase 3 of the AI Copilot + FAQ + Ticket
				// System BRD. Scores a conversation's latest customer
				// message against the seller's own Knowledge Base - see
				// AiCopilotService/CopilotController docblocks for the
				// "suggests, never auto-sends" scope boundary.
				Route::post('platform/chats/{conversation}/copilot/find-answer', [CopilotController::class, 'findAnswer'])
					->name('chats.copilot.find-answer');
				Route::post('platform/copilot-messages/{copilotMessage}/feedback', [CopilotController::class, 'feedback'])
					->name('chats.copilot.feedback');
				// Hands a conversation back to the automatic AI Copilot
				// after a human agent's reply paused it - see
				// ChatController::store()'s ai_paused_at comment.
				Route::post('platform/chats/{conversation}/resume-ai', [ChatController::class, 'resumeAi'])
					->name('chats.resume-ai');

				// NOTIFICATION CENTER - combined unread Comments + Messages
				// badge/dropdown in the navbar. Conversation-type items reuse
				// chats.read above; comments needed their own mark-read route
				// since PostComment had no read-tracking before this.
				Route::get('notifications', [NotificationController::class, 'index'])
					->name('notifications.index');
				Route::patch('platform/comments/{comment}/read', [NotificationController::class, 'markCommentRead'])
					->name('comments.read');

				// CHATS - connected channel management (separate from the
				// conversations themselves)
				Route::get('chats/channels', [MessageChannelController::class, 'index'])
					->name('chats.channels');
				// Facebook Messenger connects through social-accounts.redirect
				// now (platform=facebook) - see the comment above that route.
				Route::get('messaging/auth/instagram/redirect', [MessageChannelController::class, 'redirectInstagram'])
					->name('messaging.auth.instagram.redirect');
				Route::get('messaging/auth/instagram/callback', [MessageChannelController::class, 'callbackInstagram'])
					->name('messaging.auth.instagram.callback');
				Route::get('messaging/auth/x/redirect', [MessageChannelController::class, 'redirectX'])
					->name('messaging.auth.x.redirect');
				Route::get('messaging/auth/x/callback', [MessageChannelController::class, 'callbackX'])
					->name('messaging.auth.x.callback');
				// TikTok DM connect (audit flow #14) removed: it never received a
				// messaging scope (docs/connection-hub-design.md §6). TikTok
				// posting + ads connect through the Connection Hub.
				Route::post('messaging/channels/telegram', [MessageChannelController::class, 'storeTelegram'])
					->name('messaging.channels.telegram.store');
				Route::post('messaging/channels/whatsapp', [MessageChannelController::class, 'storeWhatsApp'])
					->name('messaging.channels.whatsapp.store');
				Route::post('messaging/channels/line', [MessageChannelController::class, 'storeLine'])
					->name('messaging.channels.line.store');
				Route::post('messaging/channels/discord', [MessageChannelController::class, 'storeDiscord'])
					->name('messaging.channels.discord.store');
				Route::get('messaging/auth/discord/redirect', [MessageChannelController::class, 'redirectDiscord'])
					->name('messaging.auth.discord.redirect');
				Route::get('messaging/channels/discord', [MessageChannelController::class, 'callbackDiscord'])
					->name('messaging.channels.discord.callback');
				Route::post('messaging/channels/teams', [MessageChannelController::class, 'storeTeams'])
					->name('messaging.channels.teams.store');
				Route::post('messaging/channels/google-chat', [MessageChannelController::class, 'storeGoogleChat'])
					->name('messaging.channels.google_chat.store');
				Route::get('messaging/auth/google-chat/redirect', [MessageChannelController::class, 'redirectGoogleChatOAuth'])
					->name('messaging.auth.google_chat.redirect');
				Route::get('messaging/auth/google-chat/callback', [MessageChannelController::class, 'callbackGoogleChatOAuth'])
					->name('messaging.auth.google_chat.callback');
				Route::post('messaging/channels/matrix', [MessageChannelController::class, 'storeMatrix'])
					->name('messaging.channels.matrix.store');
				Route::post('messaging/auth/zalo/redirect', [MessageChannelController::class, 'redirectZalo'])
					->name('messaging.auth.zalo.redirect');
				Route::get('messaging/auth/zalo/callback', [MessageChannelController::class, 'callbackZalo'])
					->name('messaging.auth.zalo.callback');
				Route::get('messaging/auth/slack/redirect', [MessageChannelController::class, 'redirectSlack'])
					->name('messaging.auth.slack.redirect');
				Route::get('messaging/auth/slack/callback', [MessageChannelController::class, 'callbackSlack'])
					->name('messaging.auth.slack.callback');
				Route::delete('messaging/channels/{channel}', [MessageChannelController::class, 'destroy'])
					->name('messaging.channels.destroy');
				// Encrypted X Chat: the account owner's X Chat PIN, verified via
				// the XDK worker before it's stored (encrypted). See XChatKeyService.
				Route::post('messaging/channels/{channel}/x-chat', [MessageChannelController::class, 'enableXChat'])
					->middleware('throttle:5,1')
					->name('messaging.channels.x-chat.enable');
				Route::delete('messaging/channels/{channel}/x-chat', [MessageChannelController::class, 'disableXChat'])
					->name('messaging.channels.x-chat.disable');


				// COMMENTS
				Route::resource('/platform/comments', PostCommentController::class);
				Route::get('comments/dashboard', [PostCommentController::class, 'dashboard'])
					->name('comments.dashboard');


				// KNOWLEDGE BASE - seller's own business FAQ (Phase 2 of the
				// AI Copilot + FAQ + Ticket System BRD). Scoped to Auth::id()
				// throughout - see KnowledgeBaseController's docblock.
				Route::get('knowledge-base', [KnowledgeBaseController::class, 'index'])->name('knowledge-base.index');
				Route::post('knowledge-base', [KnowledgeBaseController::class, 'store'])->name('knowledge-base.store');
				Route::put('knowledge-base/{faq}', [KnowledgeBaseController::class, 'update'])->name('knowledge-base.update');
				Route::delete('knowledge-base/{faq}', [KnowledgeBaseController::class, 'destroy'])->name('knowledge-base.destroy');
				Route::post('knowledge-base/categories', [KnowledgeBaseController::class, 'storeCategory'])->name('knowledge-base.categories.store');
				Route::get('knowledge-base/export', [KnowledgeBaseController::class, 'export'])->name('knowledge-base.export');
				Route::post('knowledge-base/import', [KnowledgeBaseController::class, 'import'])->name('knowledge-base.import');
				Route::post('knowledge-base/bulk', [KnowledgeBaseController::class, 'bulk'])->name('knowledge-base.bulk');
				Route::post('knowledge-base/improve', [KnowledgeBaseController::class, 'improve'])->name('knowledge-base.improve');


				// AI COPILOT SETTINGS - per-seller on/off, auto-reply,
				// confidence thresholds, tone (Phase 4 of the AI Copilot +
				// FAQ + Ticket System BRD). See AiCopilotSettingController's
				// docblock.
				Route::get('ai-copilot/settings', [AiCopilotSettingController::class, 'index'])->name('ai-copilot.settings.index');
				Route::put('ai-copilot/settings', [AiCopilotSettingController::class, 'update'])->name('ai-copilot.settings.update');

				// BUSINESS PROFILE - structured facts (hours/policies/
				// contact) the AI Copilot checks before guessing. See
				// BusinessProfileFaqSyncService's docblock for how this
				// actually reaches AiCopilotService::findBestMatch().
				Route::get('ai-copilot/business-profile', [BusinessProfileController::class, 'edit'])->name('ai-copilot.business-profile.edit');
				Route::put('ai-copilot/business-profile', [BusinessProfileController::class, 'update'])->name('ai-copilot.business-profile.update');

				// KNOWLEDGE GAPS - questions the AI Copilot couldn't answer
				// (Phase 5). Populated by ProcessAiCopilotReply, never
				// created directly here. See KnowledgeGapController's
				// docblock for the "convert to FAQ always creates a draft"
				// rule.
				Route::get('ai-copilot/knowledge-gaps', [KnowledgeGapController::class, 'index'])->name('ai-copilot.knowledge-gaps.index');
				Route::post('ai-copilot/knowledge-gaps/{gap}/convert-to-faq', [KnowledgeGapController::class, 'convertToFaq'])->name('ai-copilot.knowledge-gaps.convert-to-faq');
				Route::post('ai-copilot/knowledge-gaps/{gap}/ignore', [KnowledgeGapController::class, 'ignore'])->name('ai-copilot.knowledge-gaps.ignore');
				Route::post('ai-copilot/knowledge-gaps/{gap}/under-review', [KnowledgeGapController::class, 'markUnderReview'])->name('ai-copilot.knowledge-gaps.under-review');
				Route::post('ai-copilot/knowledge-gaps/{gap}/resolve', [KnowledgeGapController::class, 'resolve'])->name('ai-copilot.knowledge-gaps.resolve');

				// AI COPILOT ANALYTICS - KPIs over the existing
				// copilot_messages audit trail, no new tracking.
				Route::get('ai-copilot/analytics', [AiCopilotAnalyticsController::class, 'index'])->name('ai-copilot.analytics.index');


				// EMAIL MARKETING
				Route::get('email/dashboard', [EmailMarketingController::class, 'dashboard'])
					->name('email.dashboard');

				// Setup wizard - SendGrid subaccount -> domain -> DNS ->
				// sender -> ready. Plain synchronous forms (see
				// EmailSetupController's docblock for why, over a JS
				// stepper).
				Route::get('email/setup', [EmailSetupController::class, 'index'])->name('email.setup.index');
				Route::post('email/setup/subaccount', [EmailSetupController::class, 'provisionSubaccount'])->name('email.setup.subaccount');
				Route::post('email/setup/domain', [EmailSetupController::class, 'authenticateDomain'])->name('email.setup.domain');
				Route::post('email/setup/domain/{domain}/verify', [EmailSetupController::class, 'verifyDomain'])->name('email.setup.domain.verify');
				Route::post('email/setup/domain/{domain}/configure-dns', [EmailSetupController::class, 'configureDnsAutomatically'])->name('email.setup.domain.configureDns');
				Route::post('email/setup/sender', [EmailSetupController::class, 'createSender'])->name('email.setup.sender');
				Route::post('email/setup/sender/{sender}/refresh', [EmailSetupController::class, 'refreshSenderStatus'])->name('email.setup.sender.refresh');
				Route::post('email/setup/sender/{sender}/resend', [EmailSetupController::class, 'resendSenderVerification'])->name('email.setup.sender.resend');

				Route::resource('email/segments', EmailSegmentController::class)
					->only(['index', 'store', 'destroy'])
					->names('email.segments');

				Route::get('email/lists', [EmailListController::class, 'index'])->name('email.lists.index');
				Route::post('email/lists', [EmailListController::class, 'store'])->name('email.lists.store');
				Route::patch('email/lists/{list}', [EmailListController::class, 'update'])->name('email.lists.update');
				Route::delete('email/lists/{list}', [EmailListController::class, 'destroy'])->name('email.lists.destroy');

				Route::get('email/lists/{list}/subscribers', [EmailSubscriberController::class, 'index'])->name('email.lists.subscribers.index');
				Route::post('email/lists/{list}/subscribers', [EmailSubscriberController::class, 'store'])->name('email.lists.subscribers.store');
				Route::post('email/lists/{list}/subscribers/import', [EmailSubscriberController::class, 'import'])->name('email.lists.subscribers.import');
				Route::delete('email/lists/{list}/subscribers/{subscriber}', [EmailSubscriberController::class, 'destroy'])->name('email.lists.subscribers.destroy');

				Route::resource('email/templates', EmailTemplateController::class)
					->except(['show'])
					->names('email.templates');
				Route::post('email/templates/{template}/versions/{version}/restore', [EmailTemplateController::class, 'restoreVersion'])->name('email.templates.versions.restore');
				Route::post('email/templates/generate-ai', [EmailTemplateController::class, 'generateAiContent'])->name('email.templates.generateAi');
				Route::post('email/templates/upload-media', [EmailTemplateController::class, 'uploadMedia'])->name('email.templates.uploadMedia');
				Route::post('email/templates/upload-video', [EmailTemplateController::class, 'uploadVideo'])->name('email.templates.uploadVideo');
				Route::post('email/templates/{template}/autosave', [EmailTemplateController::class, 'autosave'])->name('email.templates.autosave');
				Route::post('email/templates/{template}/send-test', [EmailTemplateController::class, 'sendTestEmail'])->name('email.templates.sendTest');

				Route::resource('email/campaigns', EmailCampaignController::class)
					->except(['show'])
					->names('email.campaigns');
				Route::get('email/campaigns/{campaign}', [EmailCampaignController::class, 'show'])->name('email.campaigns.show');
				Route::post('email/campaigns/{campaign}/send', [EmailCampaignController::class, 'sendNow'])->name('email.campaigns.send');
				Route::get('email/campaigns/{campaign}/preflight', [EmailCampaignController::class, 'preflight'])->name('email.campaigns.preflight');
				Route::get('email/campaigns/{campaign}/export', [EmailCampaignController::class, 'exportReport'])->name('email.campaigns.export');
				Route::post('email/campaigns/{campaign}/duplicate', [EmailCampaignController::class, 'duplicate'])->name('email.campaigns.duplicate');
				Route::post('email/campaigns/suppression-groups', [EmailCampaignController::class, 'storeSuppressionGroup'])->name('email.campaigns.suppressionGroups.store');


				// SYSTEM
				Route::resource('/profiles', ProfileController::class);
			});
	});
	require __DIR__ . '/auth.php';
});

// Public unsubscribe link embedded in every campaign email - deliberately
// outside the LaravelLocalization group above so its URL is stable and
// never gains/loses a locale prefix depending on app config, since these
// links are baked into emails that may have already been sent. See
// EmailUnsubscribeController and the CSRF exemption in bootstrap/app.php.
// Media Gallery file stream - outside the LaravelLocalization group on
// purpose: LocaleCookieRedirect calls withCookie() on every response, which
// Symfony's StreamedResponse doesn't have. See MediaGalleryController::file().
Route::middleware(['auth', 'active.user', 'seller'])
	->get('media-gallery/{mediaAsset}/file', [MediaGalleryController::class, 'file'])
	->name('admin.media-gallery.file');

Route::get('/email/unsubscribe/{token}', [EmailUnsubscribeController::class, 'show'])->name('email.unsubscribe');
Route::post('/email/unsubscribe/{token}', [EmailUnsubscribeController::class, 'confirm'])->name('email.unsubscribe.confirm');
