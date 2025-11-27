<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

use App\Http\Controllers\AuthController;

//Admin
use App\Http\Controllers\Admin\TherapistController as AdminTherapist;
use App\Http\Controllers\Admin\AdminSessionsReportController;
use App\Http\Controllers\Admin\AdminSubscriptionsReportController;
use App\Http\Controllers\Admin\BannerController as AdminBanner;
use App\Http\Controllers\Admin\QuoteController as AdminQuote;
use App\Http\Controllers\Admin\AdminChatController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\RoleController;



// Doctor
use App\Http\Controllers\Doctor\ScheduleController;
use App\Http\Controllers\Doctor\TimeoffController;
use App\Http\Controllers\Doctor\DoctorPackagesController;
use App\Http\Controllers\Doctor\DoctorSessionsController;
use App\Http\Controllers\Doctor\DoctorClientsController;
use App\Http\Controllers\Doctor\ProfileController;
use App\Http\Controllers\Doctor\SingleSessionManageController;
use App\Http\Controllers\Doctor\DoctorChatController;

// Public
use App\Http\Controllers\Public\TherapistController as PublicTherapist;
use App\Http\Controllers\Public\PaymentsController;
use App\Http\Controllers\Public\homeController;
//controlers
use App\Http\Controllers\TherapySessionController;
use App\Http\Controllers\PackageCheckoutController;
use App\Http\Controllers\MePackagesController;
use App\Http\Controllers\UserChatController;
use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\UserNotificationController;
use App\Http\Controllers\UserNotificationSettingController;





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
    //get from therapist tabel {id}
    Route::get('/therapists/{id}',   [AdminTherapist::class, 'show']);

    Route::patch('/therapists/{id}/activate', [AdminTherapist::class, 'activate']);
    // === NEW: Availability & Timeoffs ===
    Route::get('/therapists/{id}/schedules', [AdminTherapist::class, 'schedules']);
    Route::get('/therapists/{id}/timeoffs',  [AdminTherapist::class, 'timeoffs']);

    // === NEW: Performance & Sessions tab ===
    Route::get('/therapists/{id}/sessions',  [AdminTherapist::class, 'sessions']);
    Route::get('/{id}/packages', [AdminTherapist::class, 'packages']);

    // NEW — Single Session
    Route::get('/{id}/single-session', [AdminTherapist::class, 'singleSession']);
});
Route::middleware(['auth:sanctum','role:admin'])->prefix('admin')->group(function () {

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
Route::middleware(['auth:sanctum','role:admin'])->prefix('admin')->group(function () {
    Route::get('chats', [AdminChatController::class, 'index']);
    Route::post('chats/{chat}/assign', [AdminChatController::class, 'assign']);
    Route::post('chats/{chat}/close',  [AdminChatController::class, 'close']);

    // ولو حابة admin يبعت مسدجات من الداشبورد:
    Route::get('chats/{chat}/messages', [ChatMessageController::class, 'index']);
    Route::post('chats/{chat}/messages', [ChatMessageController::class, 'store']);
});
Route::middleware(['auth:sanctum','role:admin'])->prefix('admin')->group(function () {

      Route::get('/notifications',        [AdminNotificationController::class, 'index']);
        Route::get('/notifications/{id}',   [AdminNotificationController::class, 'show']);
        Route::post('/notifications',       [AdminNotificationController::class, 'store']);   // create + send / schedule
        Route::patch('/notifications/{id}', [AdminNotificationController::class, 'update']);  // edit scheduled
        Route::delete('/notifications/{id}',[AdminNotificationController::class, 'destroy']);
});
Route::middleware(['auth:sanctum','role:admin'])->prefix('admin')->group(function () {

    Route::get('/dashboard/stats', [AdminDashboardController::class, 'stats']);

    // Dashboard – Recent activity box
    Route::get('/dashboard/recent-activity', [AdminDashboardController::class, 'recentActivity']);

    // Dashboard – Users graph (This year vs Last year)
    Route::get('/dashboard/graph/users', [AdminDashboardController::class, 'usersGraph']);
});
Route::middleware(['auth:sanctum','role:admin'])->prefix('admin')->group(function () {

   Route::get('/me',              [AdminSettingsController::class, 'show']);
    Route::patch('/me/profile',    [AdminSettingsController::class, 'updateProfile']); // name/email/phone
    Route::post('/me/avatar',     [AdminSettingsController::class, 'updateAvatar']);  // avatar فقط
    Route::patch('/me/password',   [AdminSettingsController::class, 'updatePassword']);
});
Route::middleware(['auth:sanctum','role:admin'])->prefix('admin')->group(function () {

    /* =======================
     *  Clients Management
     * =======================*/
    Route::get('/clients',                [ClientController::class, 'index']);
    Route::get('/clients/{id}',           [ClientController::class, 'show']);
    Route::patch('/clients/{id}/status',  [ClientController::class, 'updateStatus']); // Active/Blocked
    Route::post('/clients/{id}/notify',   [ClientController::class, 'sendNotification']); // زرار Send Notification

    // 👇 دول موجودين أصلاً عندك وهنستخدمهم فى الشاشات:
    // /admin/therapy-sessions?user_id=...
    // /admin/subscriptions?user_id=...

    /* =======================
     *  Admin Users Management
     * =======================*/
    Route::get('/users',                  [AdminUserController::class, 'index']);   // كل الأدمن/الستاف
    Route::post('/users',                 [AdminUserController::class, 'store']);   // Add User
    Route::get('/users/{id}',             [AdminUserController::class, 'show']);    // View Details
    Route::patch('/users/{id}',           [AdminUserController::class, 'update']);  // Edit basic info + status + roles
    Route::patch('/users/{id}/password',  [AdminUserController::class, 'resetPassword']); // Temporary password

    /* =======================
     *  Roles Management
     * =======================*/
    Route::get('/roles',                  [RoleController::class, 'index']);
    Route::post('/roles',                 [RoleController::class, 'store']);
    Route::get('/roles/{id}',             [RoleController::class, 'show']);
    Route::patch('/roles/{id}',           [RoleController::class, 'update']);
    Route::delete('/roles/{id}',          [RoleController::class, 'destroy']);
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
 Route::middleware(['auth:sanctum','role:doctor'])->prefix('doctor')->group(function () {
    Route::get('chats', [DoctorChatController::class, 'index']);
    Route::get('chats/{chat}', [DoctorChatController::class, 'show']);

    Route::get('chats/{chat}/messages', [ChatMessageController::class, 'index']);
    Route::post('chats/{chat}/messages', [ChatMessageController::class, 'store']);
    Route::post('chats/{chat}/read',     [ChatMessageController::class, 'read']);
});

// ===== Public =====
Route::get('/home/banners', [homeController::class, 'homebanner']);
Route::get('/home/quote',   [homeController::class,  'homeQuote']);
Route::get('/therapists',                   [PublicTherapist::class, 'index']);
Route::get('/therapists/{id}',              [PublicTherapist::class, 'show']);
Route::get('/therapists/{id}/packages',     [PublicTherapist::class, 'packages']);
Route::get('/therapists/{id}/single-session',[PublicTherapist::class, 'singleSession']);
Route::get('/therapists/{id}/availability', [PublicTherapist::class, 'availability']);


Route::middleware(['auth:sanctum'])->prefix('me')->group(function () {
    Route::get('/profile',            [UserProfileController::class, 'show']);
  Route::patch('/profile/info', [UserProfileController::class, 'updateProfileInfo']);

    // تحديث الصورة فقط
    Route::post('/profile/avatar', [UserProfileController::class, 'updateAvatar']);
    Route::patch('/profile/password', [UserProfileController::class, 'updatePassword']);
});

Route::middleware(['auth:sanctum'])->group(function () {
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


// Webhook من Paymob (من غير auth)
Route::post('/payments/paymob/webhook', [PaymentsController::class, 'webhook']);

// لازم يكون اليوزر عامل login عشان يدفع
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/payments', [PaymentsController::class, 'create']);

    // Fake success للـ local testing
    Route::post('/payments/{payment}/fake-success', [PaymentsController::class, 'fakeSuccess']);
});
Route::middleware('auth:sanctum')->group(function () {

    // فتح / الحصول على شات الـ support
    Route::post('chats/open-support', [UserChatController::class, 'openSupportChat']);

    Route::get('chats', [UserChatController::class, 'index']);
    Route::get('chats/{chat}', [UserChatController::class, 'show']);

    Route::get('chats/{chat}/messages', [ChatMessageController::class, 'index']);
    Route::post('chats/{chat}/messages', [ChatMessageController::class, 'store']);
    Route::post('chats/{chat}/read',     [ChatMessageController::class, 'read']);
});

Route::middleware(['auth:sanctum'])->group(function () {

        // قائمة النوتيفيكيشن للموبايل
        Route::get('/notifications', [UserNotificationController::class, 'index']);

        // Mark as read لواحدة
        Route::patch('/notifications/{notification}/read', [UserNotificationController::class, 'markAsRead']);

        // (اختياري) Mark all as read
        Route::patch('/notifications/read-all', [UserNotificationController::class, 'markAllAsRead']);


        // Notification Settings screen
        Route::get('/notification-settings', [UserNotificationSettingController::class, 'show']);
        Route::patch('/notification-settings', [UserNotificationSettingController::class, 'update']);
    });


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

