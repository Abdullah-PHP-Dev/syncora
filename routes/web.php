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
use App\Http\Controllers\Admin\CommentController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\AdminAPIController;
use App\Http\Controllers\Admin\AdCampaignController;
use App\Http\Controllers\Admin\SubscriptionController;

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


				// POSTS
				Route::resource('/platform/posts', PostController::class);
				Route::get('posts/dashboard', [PostController::class, 'dashboard'])
					->name('posts.dashboard');


				// CHATS
				Route::resource('/platform/chats', ChatController::class);
				Route::get('chats/dashboard', [ChatController::class, 'dashboard'])
					->name('chats.dashboard');


				// COMMENTS
				Route::resource('/platform/comments', CommentController::class);
				Route::get('comments/dashboard', [CommentController::class, 'dashboard'])
					->name('comments.dashboard');


				// SYSTEM
				Route::resource('/apis', AdminAPIController::class);
				Route::resource('/profiles', ProfileController::class);
			});
	});
	require __DIR__ . '/auth.php';
});
