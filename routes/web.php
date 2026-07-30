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
use App\Http\Controllers\Admin\ChatController;
use App\Http\Controllers\Admin\MessageChannelController;
use App\Http\Controllers\Admin\PostCommentController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\AdminAPIController;
use App\Http\Controllers\Admin\AdCampaignController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\PostCategoryController;


Route::group(['prefix' => LaravelLocalization::setLocale(), 'middleware' => [
	'web',
	LaravelLocalizationRoutes::class,
	LocaleSessionRedirect::class,
	LocaleCookieRedirect::class,
	LaravelLocalizationRedirectFilter::class,
	LaravelLocalizationViewPath::class,
]], function () {

	Route::view('/', 'front.pages.home');
	Route::view('/about', 'front.pages.about');
	Route::view('/services', 'front.pages.services');
	Route::view('/product', 'front.pages.product');
	Route::view('/pricing', 'front.pages.pricing');



	Route::middleware(['auth'])->group(function () {
		/*
		|--------------------------------------------------------------------------
		| DASHBOARD (NO SUBSCRIPTION REQUIRED)
		|--------------------------------------------------------------------------
		*/
		Route::prefix('admin')
			->name('admin.')
			->group(function () {
				/*
		|--------------------------------------------------------------------------
		| SUBSCRIPTION FLOW (ALWAYS ACCESSIBLE)
		|--------------------------------------------------------------------------
		*/
				// subscription flow (NO middleware restriction)
				Route::get('/subscription/select', [SubscriptionController::class, 'select']);
				Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout']);
				Route::post('/subscription/activate', [SubscriptionController::class, 'activate']);
				Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel']);

				Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'dashboard'])
					->name('dashboard');

				Route::view('/dashboard/crm', 'admin.crm-dashboard')
					->name('crm-dashboard');
			});


		/*
		|--------------------------------------------------------------------------
		| PROTECTED SAAS MODULES (SUBSCRIPTION REQUIRED)
		|--------------------------------------------------------------------------
		*/
		Route::middleware(['subscription'])
			->prefix('admin')
			->name('admin.')
			->group(function () {

				// ADS
				Route::resource('/platform/ads', AdController::class);
				Route::get('ads/dashboard', [AdController::class, 'dashboard'])
					->name('ads.dashboard');

				Route::get('ads/{platform}/redirect', [AdController::class, 'redirect'])
					->name('ads.redirect');

				Route::get('ads/{platform}/callback', [AdController::class, 'callback']);

				Route::resource('ads/{platform}/campaigns', AdCampaignController::class)
					->names('ads.campaigns');

				Route::patch('ads/{platform}/campaigns/{id}/status', [AdCampaignController::class, 'updateStatus'])
					->name('ads.campaigns.status');


				// POSTS
				Route::get('posts/dashboard', [PostController::class, 'dashboard'])->defaults('_config', ['view' => 'admin.posts.dashboard'])->name('posts.dashboard');
				Route::get('posts/vue_index', [PostController::class, 'index_vue']);
				Route::get('posts/{post}/preview/{platform}', [PostController::class, 'preview'])->name('posts.preview');
				Route::get('posts', [PostController::class, 'dashboard']);
				Route::resource('posts', PostController::class);
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

				// CHATS - connected channel management (separate from the
				// conversations themselves)
				Route::get('chats/channels', [MessageChannelController::class, 'index'])
					->name('chats.channels');
				Route::get('messaging/auth/meta/redirect', [MessageChannelController::class, 'redirectMeta'])
					->name('messaging.auth.meta.redirect');
				Route::get('messaging/auth/meta/callback', [MessageChannelController::class, 'callbackMeta'])
					->name('messaging.auth.meta.callback');
				Route::get('messaging/auth/x/redirect', [MessageChannelController::class, 'redirectX'])
					->name('messaging.auth.x.redirect');
				Route::get('messaging/auth/x/callback', [MessageChannelController::class, 'callbackX'])
					->name('messaging.auth.x.callback');
				Route::post('messaging/channels/telegram', [MessageChannelController::class, 'storeTelegram'])
					->name('messaging.channels.telegram.store');
				Route::post('messaging/channels/whatsapp', [MessageChannelController::class, 'storeWhatsApp'])
					->name('messaging.channels.whatsapp.store');
				Route::delete('messaging/channels/{channel}', [MessageChannelController::class, 'destroy'])
					->name('messaging.channels.destroy');


				// COMMENTS
				Route::resource('/platform/comments', PostCommentController::class);
				Route::get('comments/dashboard', [PostCommentController::class, 'dashboard'])
					->name('comments.dashboard');


				// SYSTEM
				Route::resource('/apis', AdminAPIController::class);
				Route::resource('/profiles', ProfileController::class);
			});
	});
	require __DIR__ . '/auth.php';
});
