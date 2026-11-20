<?php

use App\Http\Controllers\Blog\BlogController;
use App\Http\Controllers\Career\CareerApplieController;
use App\Http\Controllers\Career\CareerController;
use App\Http\Controllers\Contact\ContactController;
use App\Http\Controllers\Elite\EliteController;
use App\Http\Controllers\Emi\EmiController;
use App\Http\Controllers\Faq\FaqController;
use App\Http\Controllers\Getway\SslCommerzPaymentController;
use App\Http\Controllers\Home\HomePageController;
use App\Http\Controllers\Offer\OfferController;
use App\Http\Controllers\Order\OrderTrackController;
use App\Http\Controllers\OurTeam\OurTeamController;
use App\Http\Controllers\Outlet\OutletController;
use App\Http\Controllers\Policy\PolicyController;
use App\Http\Controllers\PreOwned\PreOwnedController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\Refurbished\RefurbishedController;
use App\Http\Controllers\Seo\SeoController;
use App\Http\Controllers\Setting\SettingController;
use App\Http\Controllers\Shop\ShopController;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return responseMessage('Welcome to Salamat Innovation');
});

// Customer Routes
include_route_files(__DIR__.'/customer/');

Route::group(['prefix' => 'v1', 'as' => 'api.'], function (Router $router) {

    $router->get('search-products', [HomePageController::class, 'searchProduct'])
        ->name('search.product');

    $router->get('search', [HomePageController::class, 'searchProductResult'])
        ->name('search.product-results');

    $router->get('home-categories', [HomePageController::class, 'homeCategory'])
        ->name('categories.home');

    $router->get('categories', [HomePageController::class, 'categories'])
        ->name('categories');

    $router->get('category-tree', [HomePageController::class, 'categoryTree'])
        ->name('category-tree');

    $router->get('brands', [HomePageController::class, 'brand'])
        ->name('brands');

    $router->get('all-brands', [HomePageController::class, 'allBrand'])
        ->name('all-brands');

    $router->get('category-products', [HomePageController::class, 'categoryProducts'])
        ->name('category-products');

    $router->get('sliders', [HomePageController::class, 'slider'])
        ->name('sliders');

    $router->get('flash-sale-products', [HomePageController::class, 'flashSaleProduct'])
        ->name('flash-sale.products');

    $router->get('flash-sales', [HomePageController::class, 'flashSale'])
        ->name('flash.sales');

    $router->get('featured-products', [HomePageController::class, 'featuredProduct'])
        ->name('featured.products');

    $router->get('home-new-arrival-products', [HomePageController::class, 'newArrivalProduct'])
        ->name('home-new-arrival.products');

    $router->get('new-arrival-products', [HomePageController::class, 'newArrival'])
        ->name('new-arrivals.index');

    $router->get('home-pre-owned-products', [HomePageController::class, 'homePreOwnedProduct']);

    $router->get('news', [HomePageController::class, 'news'])
        ->name('news.home');

    $router->get('blogs', [BlogController::class, 'index'])
        ->name('blogs.index');

    $router->get('blogs/{slug}', [BlogController::class, 'show'])
        ->name('blogs.show');

    $router->get('featured-blogs', [BlogController::class, 'featuredBlog'])
        ->name('featured-blogs.index');

    $router->get('recent-blogs', [BlogController::class, 'recentBlog'])
        ->name('recent-blogs.index');

    $router->get('popular-blogs', [BlogController::class, 'popularBlog'])
        ->name('popular-blogs.index');

    $router->get('trending-blogs', [BlogController::class, 'trendingBlog'])
        ->name('trending-blogs.index');

    $router->get('blog-tags/{slug}', [BlogController::class, 'postsByTag'])
        ->name('blog-tags.show');

    $router->get('reels-videos', [HomePageController::class, 'reelsVideo'])
        ->name('reels.home');

    $router->get('shops', [ShopController::class, 'index'])
        ->name('shop-products');

    $router->get('products', [ShopController::class, 'product'])
        ->name('products');

    $router->get('product/{slug}', [ProductController::class, 'detail'])
        ->name('product.detail');

    $router->get('product/{slug}/reviews', [ProductController::class, 'productReview'])
        ->name('products.review');

    $router->get('related/{slug}/products', [ProductController::class, 'relatedProduct'])
        ->name('products.related');

    $router->get('pre-owned-related/{slug}/products', [ProductController::class, 'preOwnedRelatedProduct'])
        ->name('pre-owned-products.related');

    $router->get('category/{slug}', [ProductController::class, 'categoryInfo'])
        ->name('category.info');

    $router->get('category/{slug}/products', [ProductController::class, 'categoryProduct'])
        ->name('category.products');

    $router->get('brand/{slug}/products', [ProductController::class, 'brandProduct'])
        ->name('brand.products');

    $router->get('offers', [OfferController::class, 'index'])
        ->name('offers');

    $router->get('offer/{slug}/products', [OfferController::class, 'offerProduct'])
        ->name('offers.products');

    $router->get('pre-owneds', [PreOwnedController::class, 'index'])
        ->name('pre-owneds.index');

    $router->get('pre-owneds/grouped', [PreOwnedController::class, 'grouped'])
        ->name('pre-owneds.grouped');

    $router->get('pre-owneds/brands', [PreOwnedController::class, 'brands'])
        ->name('pre-owneds.brands');

    $router->get('pre-owneds/{slug}/products', [PreOwnedController::class, 'preOwnedProduct'])
        ->name('pre-owneds.products');

    $router->get('refurbisheds', [RefurbishedController::class, 'index'])
        ->name('refurbisheds.index');

    $router->get('refurbished-brands', [RefurbishedController::class, 'brand'])
        ->name('refurbisheds-brands.index');

    $router->get('policies', [PolicyController::class, 'index'])
        ->name('policies.index');

    $router->get('policies/{type}', [PolicyController::class, 'policyType'])
        ->name('policies.details');

    $router->post('contacts', [ContactController::class, 'store'])
        ->name('contacts.store')
        ->middleware('throttle:public-write');

    $router->get('settings', [SettingController::class, 'index'])
        ->name('settings.index');

    $router->get('seos/{pageSlug}', [SeoController::class, 'seo'])
        ->name('settings.seo');

    $router->get('careers', [CareerController::class, 'index'])
        ->name('careers.index');

    $router->get('careers/{slug}', [CareerController::class, 'show'])
        ->name('careers.show');

    $router->get('faqs', [FaqController::class, 'index'])
        ->name('faqs.index');

    $router->get('outlets', [OutletController::class, 'index'])
        ->name('outlets.index');

    $router->get('outlets/{slug}/detail', [OutletController::class, 'outletDetail'])
        ->name('outlets.view');

    $router->get('our-teams', [OurTeamController::class, 'index'])
        ->name('our-teams.index');

    $router->post('order-tracking', [OrderTrackController::class, 'orderTracking'])
        ->name('order-tracking')
        ->middleware('throttle:invoice-tracking');

    $router->post('product-verify', [OrderTrackController::class, 'productVerify'])
        ->name('product-verify')
        ->middleware('throttle:invoice-tracking');

    $router->get('districts', [SettingController::class, 'district'])
        ->name('addresses.district');

    $router->get('areas/{id}', [SettingController::class, 'area'])
        ->name('addresses.area');

    $router->get('conditions', [SettingController::class, 'condition'])
        ->name('conditions');

    $router->get('storages', [SettingController::class, 'storage'])
        ->name('storages');

    $router->post('job-apply/{slug}', [CareerApplieController::class, 'store'])
        ->name('job.apply')
        ->middleware('throttle:public-write');

    $router->get('emi-list', [EmiController::class, 'index'])
        ->name('emi-list.index');

    $router->get('popup-dialogs', [SettingController::class, 'popupDailog'])
        ->name('popup-dialogs.index');

    $router->get('elite', [EliteController::class, 'index'])
        ->name('elite.index');
});

// SSLCommerz Webhook Routes

Route::group(['prefix' => 'webhook', 'as' => 'api.'], function (Router $router) {

    $router->post('sslcommerz/ipn', [SslCommerzPaymentController::class, 'ipn']);
});
