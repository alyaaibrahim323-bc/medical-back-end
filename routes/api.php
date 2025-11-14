<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
//Admin
use App\Http\Controllers\Admin\TherapistController as AdminTherapist;
use App\Http\Controllers\Admin\AdminPackagesReportController;
use App\Http\Controllers\Admin\AdminSessionsReportController;
use App\Http\Controllers\Admin\AdminSubscriptionsReportController;
use App\Http\Controllers\Admin\BannerController as AdminBanner;
use App\Http\Controllers\Admin\QuoteController as AdminQuote;


// Doctor
use App\Http\Controllers\Doctor\ScheduleController;
use App\Http\Controllers\Doctor\TimeoffController;
use App\Http\Controllers\Doctor\DoctorPackagesController;
use App\Http\Controllers\Doctor\DoctorSessionsController;
use App\Http\Controllers\Doctor\DoctorClientsController;
use App\Http\Controllers\Doctor\ProfileController;
use App\Http\Controllers\Doctor\SingleSessionManageController;
// Public
use App\Http\Controllers\Public\TherapistController as PublicTherapist;
use App\Http\Controllers\Public\PaymentsController;
use App\Http\Controllers\Public\homeController;
//controlers
use App\Http\Controllers\TherapySessionController;
use App\Http\Controllers\PackageCheckoutController;
use App\Http\Controllers\MePackagesController;
use App\Http\Controllers\PaymentWebhookController;



Route::prefix('auth')->group(function () {
    Route::post('/register',          [AuthController::class, 'register']);
    Route::post('/login',             [AuthController::class, 'login']);

    Route::post('/email/verify-otp',  [AuthController::class, 'verifyEmailOtp']);
    Route::post('/email/resend-otp',  [AuthController::class, 'resendEmailOtp']);

    Route::post('/password/email',    [AuthController::class, 'sendResetLink']);
    Route::post('/password/reset',    [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me',    [AuthController::class, 'me']);
        Route::post('/logout',[AuthController::class, 'logout']);
    });
});

// ===== Admin(requires role:admin) =====

Route::middleware(['auth:sanctum','role:admin'])->prefix('admin')->group(function () {
    Route::get('/therapists',        [AdminTherapist::class, 'index']);
    Route::post('/therapists',       [AdminTherapist::class, 'store']);
    //get from therapist tabel {id}
    Route::get('/therapists/{id}',   [AdminTherapist::class, 'show']);
    Route::patch('/therapists/{id}', [AdminTherapist::class, 'update']);
    Route::delete('/therapists/{id}',[AdminTherapist::class, 'destroy']);
    Route::patch('/therapists/{id}/activate', [AdminTherapist::class, 'activate']);
});
Route::middleware(['auth:sanctum','role:admin'])->prefix('admin')->group(function () {
  // Packages by doctor + details
  Route::get('/packages',               [AdminPackagesReportController::class, 'index']);  // ?therapist_id=...
  Route::get('/packages/{id}',          [AdminPackagesReportController::class, 'show']);   // details

  // Sessions by doctor + filters
  Route::get('/therapy-sessions',       [AdminSessionsReportController::class, 'index']);  // ?therapist_id=&status=&from=&to=
  Route::get('/therapy-sessions/{id}',  [AdminSessionsReportController::class, 'show']);

  // Subscriptions (user_packages)
  Route::get('/subscriptions',          [AdminSubscriptionsReportController::class, 'index']); // ?therapist_id=&user_id=
  Route::get('/subscriptions/{id}',     [AdminSubscriptionsReportController::class, 'show']);
});
Route::middleware(['auth:sanctum','role:admin'])->prefix('admin')->group(function () {
    Route::get('/banners',        [AdminBanner::class, 'index']);
    Route::post('/banners',       [AdminBanner::class, 'store']);
    Route::get('/banners/{id}',   [AdminBanner::class, 'show']);
    Route::put('/banners/{id}',   [AdminBanner::class, 'update']);
    Route::delete('/banners/{id}',[AdminBanner::class, 'destroy']);
});
Route::middleware(['auth:sanctum','role:admin'])->prefix('admin')->group(function () {
    Route::get('/quotes',        [AdminQuote::class, 'index']);
    Route::post('/quotes',       [AdminQuote::class, 'store']);
    Route::get('/quotes/{id}',   [AdminQuote::class, 'show']);
    Route::put('/quotes/{id}',   [AdminQuote::class, 'update']);
    Route::delete('/quotes/{id}',[AdminQuote::class, 'destroy']);
});

// ===== Doctor (requires role:doctor) =====
Route::middleware(['auth:sanctum','role:doctor'])->prefix('doctor')->group(function () {
    // schedules
    Route::get('/schedules',          [ScheduleController::class, 'index']);
    //add day must be array []
    Route::post('/schedules',         [ScheduleController::class, 'store']);
    // edit in on day {id from table therapist-schedules}
    Route::patch('/schedules/{id}',   [ScheduleController::class, 'update']);
    Route::delete('/schedules/{id}',  [ScheduleController::class, 'destroy']);
    // timeoffs
    Route::get('/timeoffs',           [TimeoffController::class, 'index']);
    Route::post('/timeoffs',          [TimeoffController::class, 'store']);
    Route::delete('/timeoffs/{id}',   [TimeoffController::class, 'destroy']);
});
Route::middleware(['auth:sanctum','role:doctor'])->prefix('doctor')->group(function () {
  // Packages CRUD (doctor-owned)
  Route::get   ('/packages',        [DoctorPackagesController::class, 'index']);
  Route::post  ('/packages',        [DoctorPackagesController::class, 'store']);
  Route::get   ('/packages/{id}',   [DoctorPackagesController::class, 'show']);
  Route::patch ('/packages/{id}',   [DoctorPackagesController::class, 'update']);
  Route::delete('/packages/{id}',   [DoctorPackagesController::class, 'destroy']);

  // Sessions for this doctor
  Route::get  ('/sessions',         [DoctorSessionsController::class, 'index']);
  Route::get  ('/sessions/{id}',    [DoctorSessionsController::class, 'show']);
  // (اختياري) تغيير الحالة يدوياً
  Route::patch('/sessions/{id}/status', [DoctorSessionsController::class, 'updateStatus']);
  Route::post('/sessions/{id}/zoom', [DoctorSessionsController::class, 'createZoom']);
  Route::post('/sessions/{id}/link', [DoctorSessionsController::class, 'addSessionLink']);

  // Subscribed clients (user_packages tied to this doctor)
  Route::get ('/subscriptions',     [DoctorClientsController::class, 'subscriptions']);
  Route::get ('/subscriptions/{id}',[DoctorClientsController::class, 'subscriptionShow']);
  Route::get('subscriptions/{id}/sessions', [DoctorClientsController::class, 'subscriptionSessions']);

});

Route::middleware(['auth:sanctum','role:doctor'])->prefix('doctor')->group(function () {
    Route::get  ('/profile', [ProfileController::class,'show']);
    Route::patch('/profile', [ProfileController::class,'update']);
    Route::patch('/password',[ProfileController::class,'updatePassword']); // ← جديد


    Route::get   ('/single-session',         [SingleSessionManageController::class, 'show']);
    Route::post  ('/single-session',         [SingleSessionManageController::class, 'store']);
    Route::patch ('/single-session',         [SingleSessionManageController::class, 'update']);
    Route::post  ('/single-session/disable', [SingleSessionManageController::class, 'deactivate']);
});

// ===== Public =====
Route::get('/home/banners', [homeController::class, 'homebanner']);
Route::get('/home/quote',   [homeController::class,  'homeQuote']);
Route::get('/therapists',                   [PublicTherapist::class, 'index']);
Route::get('/therapists/{id}',              [PublicTherapist::class, 'show']);
Route::get('/therapists/{id}/packages',     [PublicTherapist::class, 'packages']);
Route::get('/therapists/{id}/single-session',[PublicTherapist::class, 'singleSession']);


Route::get('/therapists/{id}/availability', [PublicTherapist::class, 'availability']);
// Public: Packages catalog
use App\Http\Controllers\Public\PackagesController as PublicPackages;
Route::get('/packages',      [PublicPackages::class, 'index']);
Route::get('/packages/{id}', [PublicPackages::class, 'show']);


Route::middleware(['auth:sanctum','verified.api'])->group(function () {
    // Therapy sessions
    Route::get('/therapy-sessions',         [TherapySessionController::class, 'index']);
    Route::post('/therapy-sessions',        [TherapySessionController::class, 'store']);
    Route::get('/therapy-sessions/{id}',    [TherapySessionController::class, 'show']);
    Route::delete('/therapy-sessions/{id}', [TherapySessionController::class, 'cancel']);

    // Packages purchase & mine
    Route::post('/packages/{id}/checkout',  [PackageCheckoutController::class, 'checkout']);
    Route::get('/me/packages',              [MePackagesController::class, 'index']);
    Route::get('/me/packages/{id}',         [MePackagesController::class, 'show']);
});
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/payments', [PaymentsController::class, 'create']); // بدء دفع
});

// Webhook (بدون Auth لكن مع HMAC)
Route::post('/payments/paymob/webhook', [PaymentsController::class, 'webhook']);

// (اختياري) صفحات تأكيد
Route::get('/payments/success', [PaymentsController::class, 'success']);
Route::get('/payments/failed',  [PaymentsController::class, 'failed']);

// Paymob webhook
Route::match(['GET','POST'], '/payments/webhook/paymob', [PaymentWebhookController::class, 'paymob'])
    ->middleware('throttle:60,1');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
