<?php

use App\Http\Controllers\Admin\DashboardSummaryController;
use App\Http\Controllers\Admin\GuestController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\WeddingEventContentController;
use App\Http\Controllers\Admin\WeddingFaqContentController;
use App\Http\Controllers\Admin\WeddingGalleryContentController;
use App\Http\Controllers\Admin\WeddingHeroContentController;
use App\Http\Controllers\Admin\WeddingSettingsController;
use App\Http\Controllers\Admin\WeddingStoryContentController;
use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\PublicInvitationController;
use App\Http\Controllers\PublicRsvpController;
use App\Http\Controllers\WeddingController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'Wedding RSVP Platform API',
    ]);
});

Route::get('/wedding', [WeddingController::class, 'show']);
Route::get('/invitations/{token}', [PublicInvitationController::class, 'show'])
    ->middleware('throttle:public-invitation');
Route::put('/invitations/{token}/rsvp', [PublicRsvpController::class, 'update'])
    ->middleware('throttle:public-rsvp');

Route::post('/auth/login', [AuthenticationController::class, 'login'])
    ->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthenticationController::class, 'logout']);
    Route::get('/auth/me', [AuthenticationController::class, 'me']);

    Route::get('/admin/ping', fn () => response()->json(['status' => 'ok']));
    Route::get('/admin/dashboard/summary', DashboardSummaryController::class);
    Route::get('/admin/wedding', [WeddingSettingsController::class, 'show']);
    Route::put('/admin/wedding', [WeddingSettingsController::class, 'update']);

    Route::get('/admin/wedding-content/hero', [WeddingHeroContentController::class, 'show']);
    Route::put('/admin/wedding-content/hero', [WeddingHeroContentController::class, 'update']);
    Route::get('/admin/wedding-content/story', [WeddingStoryContentController::class, 'index']);
    Route::post('/admin/wedding-content/story', [WeddingStoryContentController::class, 'store']);
    Route::get('/admin/wedding-content/story/{storyEntry}', [WeddingStoryContentController::class, 'show']);
    Route::put('/admin/wedding-content/story/{storyEntry}', [WeddingStoryContentController::class, 'update']);
    Route::delete('/admin/wedding-content/story/{storyEntry}', [WeddingStoryContentController::class, 'destroy']);
    Route::get('/admin/wedding-content/events', [WeddingEventContentController::class, 'index']);
    Route::post('/admin/wedding-content/events', [WeddingEventContentController::class, 'store']);
    Route::get('/admin/wedding-content/events/{event}', [WeddingEventContentController::class, 'show']);
    Route::put('/admin/wedding-content/events/{event}', [WeddingEventContentController::class, 'update']);
    Route::delete('/admin/wedding-content/events/{event}', [WeddingEventContentController::class, 'destroy']);
    Route::get('/admin/wedding-content/faq', [WeddingFaqContentController::class, 'index']);
    Route::post('/admin/wedding-content/faq', [WeddingFaqContentController::class, 'store']);
    Route::get('/admin/wedding-content/faq/{faqEntry}', [WeddingFaqContentController::class, 'show']);
    Route::put('/admin/wedding-content/faq/{faqEntry}', [WeddingFaqContentController::class, 'update']);
    Route::delete('/admin/wedding-content/faq/{faqEntry}', [WeddingFaqContentController::class, 'destroy']);
    Route::get('/admin/wedding-content/gallery', [WeddingGalleryContentController::class, 'index']);
    Route::post('/admin/wedding-content/gallery', [WeddingGalleryContentController::class, 'store']);
    Route::get('/admin/wedding-content/gallery/{galleryEntry}', [WeddingGalleryContentController::class, 'show']);
    Route::put('/admin/wedding-content/gallery/{galleryEntry}', [WeddingGalleryContentController::class, 'update']);
    Route::delete('/admin/wedding-content/gallery/{galleryEntry}', [WeddingGalleryContentController::class, 'destroy']);

    Route::get('/admin/invitations', [InvitationController::class, 'index']);
    Route::post('/admin/invitations', [InvitationController::class, 'store']);
    Route::get('/admin/invitations/{invitation}', [InvitationController::class, 'show']);
    Route::put('/admin/invitations/{invitation}', [InvitationController::class, 'update']);
    Route::delete('/admin/invitations/{invitation}', [InvitationController::class, 'destroy']);
    Route::post('/admin/invitations/{invitation}/mark-ready', [InvitationController::class, 'markReady']);
    Route::post('/admin/invitations/{invitation}/lock', [InvitationController::class, 'lock']);
    Route::post('/admin/invitations/{invitation}/reopen', [InvitationController::class, 'reopen']);
    Route::post('/admin/invitations/{invitation}/archive', [InvitationController::class, 'archive']);
    Route::post('/admin/invitations/{invitation}/regenerate-token', [InvitationController::class, 'regenerateToken']);

    Route::post('/admin/invitations/{invitation}/guests', [GuestController::class, 'store']);
    Route::put('/admin/invitations/{invitation}/guests/{guest}', [GuestController::class, 'update']);
    Route::delete('/admin/invitations/{invitation}/guests/{guest}', [GuestController::class, 'destroy']);
});
