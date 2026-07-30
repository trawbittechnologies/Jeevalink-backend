<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\DonorController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\EmergencyController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\VolunteerController;
use App\Http\Controllers\TechnicalAdminController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\BlockAdminController;
use App\Http\Controllers\UnitSquadController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // ─── Public Authentication & Information Routes ──────────────────────
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::get('/location/pincode/{pincode}', [AuthController::class, 'pincodeLookup']);
    Route::get('/partners', [PartnerController::class, 'index']);

    // ─── Authenticated Group ─────────────────────────────────────────────
    Route::middleware('jwt.auth')->group(function () {

        // ── Auth Endpoints ───────────────────────────────────────────────
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
        Route::patch('/auth/profile', [AuthController::class, 'profile']);
        Route::patch('/auth/toggle-availability', [AuthController::class, 'toggleAvailability']);
        Route::post('/auth/push-token', [AuthController::class, 'pushToken']);

        // ── Technical Admin Endpoints ─────────────────────────────────────
        Route::middleware('jwt.role:technical_admin,admin')->group(function () {
            Route::get('/technical-admin/dashboard', [TechnicalAdminController::class, 'dashboard']);
            Route::get('/technical-admin/metrics', [TechnicalAdminController::class, 'metrics']);
            Route::get('/technical-admin/super-admins', [TechnicalAdminController::class, 'getSuperAdmins']);
            Route::post('/technical-admin/super-admins', [TechnicalAdminController::class, 'createSuperAdmin']);
            Route::put('/technical-admin/super-admins/{id}', [TechnicalAdminController::class, 'updateSuperAdmin']);
            Route::delete('/technical-admin/super-admins/{id}', [TechnicalAdminController::class, 'deleteSuperAdmin']);
            Route::get('/technical-admin/activity-logs', [ActivityLogController::class, 'index']);
            Route::post('/technical-admin/broadcast', [AdminController::class, 'broadcastNotification']);
        });

        // ── Super Admin Endpoints ──────────────────────────────────────────
        Route::middleware('jwt.role:super_admin,technical_admin,admin')->group(function () {
            Route::get('/super-admin/dashboard', [SuperAdminController::class, 'dashboard']);
            Route::get('/super-admin/metrics', [SuperAdminController::class, 'metrics']);
            Route::get('/super-admin/block-admins', [SuperAdminController::class, 'getBlockAdmins']);
            Route::post('/super-admin/block-admins', [SuperAdminController::class, 'createBlockAdmin']);
            Route::put('/super-admin/block-admins/{id}', [SuperAdminController::class, 'updateBlockAdmin']);
            Route::delete('/super-admin/block-admins/{id}', [SuperAdminController::class, 'deleteBlockAdmin']);
        });

        // ── Block Admin Endpoints ──────────────────────────────────────────
        Route::middleware('jwt.role:block_admin,admin,super_admin,technical_admin')->group(function () {
            Route::get('/block-admin/dashboard', [BlockAdminController::class, 'dashboard']);
            Route::get('/block-admin/metrics', [BlockAdminController::class, 'metrics']);
            Route::get('/block-admin/volunteers', [BlockAdminController::class, 'getVolunteers']);
            Route::post('/block-admin/volunteers', [BlockAdminController::class, 'createVolunteer']);
            Route::put('/block-admin/volunteers/{id}', [BlockAdminController::class, 'updateVolunteer']);
            Route::delete('/block-admin/volunteers/{id}', [BlockAdminController::class, 'deleteVolunteer']);
            Route::get('/block-admin/users', [BlockAdminController::class, 'getUsers']);
        });

        // ── Volunteer Endpoints ───────────────────────────────────────────
        Route::middleware('jwt.role:volunteer,block_admin,admin,super_admin,technical_admin')->group(function () {
            Route::get('/volunteer/dashboard', [VolunteerController::class, 'dashboard']);
            Route::get('/volunteer/unit-squads', [VolunteerController::class, 'getUnitSquads']);
            Route::post('/volunteer/unit-squads', [VolunteerController::class, 'createUnitSquad']);
            Route::put('/volunteer/unit-squads/{id}', [VolunteerController::class, 'updateUnitSquad']);
            Route::delete('/volunteer/unit-squads/{id}', [VolunteerController::class, 'deleteUnitSquad']);

            // Volunteer OTP & Update Routes
            Route::post('/volunteer/users', [VolunteerController::class, 'addUser']);
            Route::post('/volunteer/users/{id}/send-otp', [VolunteerController::class, 'sendOtp']);
            Route::post('/volunteer/users/{id}/verify-otp', [VolunteerController::class, 'verifyOtp']);
            Route::patch('/volunteer/users/{id}', [VolunteerController::class, 'updateUser']);
        });

        // ── Unit Squad Endpoints ──────────────────────────────────────────
        Route::middleware('jwt.role:unit_squad,volunteer,block_admin,admin,super_admin,technical_admin')->group(function () {
            Route::get('/unit-squad/dashboard', [UnitSquadController::class, 'dashboard']);
            Route::get('/unit-squad/users', [UnitSquadController::class, 'getUsers']);
            Route::post('/unit-squad/users', [UnitSquadController::class, 'createUser']);
            Route::put('/unit-squad/users/{id}', [UnitSquadController::class, 'updateUser']);
            Route::delete('/unit-squad/users/{id}', [UnitSquadController::class, 'deleteUser']);
        });

        // ── User Specific Endpoints ────────────────────────────────────────
        Route::middleware('jwt.role:user,donor,unit_squad,volunteer,block_admin,admin,super_admin,technical_admin')->group(function () {
            Route::get('/user/profile', [UserController::class, 'getProfile']);
            Route::put('/user/profile', [UserController::class, 'updateProfile']);
            Route::post('/user/blood-requests', [UserController::class, 'createBloodRequest']);
            Route::get('/user/blood-requests', [UserController::class, 'getBloodRequests']);
            Route::post('/user/sos', [UserController::class, 'createSos']);
        });

        // ── Shared Features & General Features ─────────────────────────────
        Route::post('/save-fcm-token', [EmergencyController::class, 'saveFcmToken']);
        Route::post('/emergency/request', [EmergencyController::class, 'createRequest']);
        Route::get('/emergency/history', [EmergencyController::class, 'getHistory']);
        Route::get('/emergency/details/{id}', [EmergencyController::class, 'getDetails']);
        Route::post('/emergency/accept', [EmergencyController::class, 'acceptRequest']);
        Route::post('/emergency/reject', [EmergencyController::class, 'rejectRequest']);
        Route::get('/emergency/nearby-donors', [EmergencyController::class, 'getNearbyDonors']);
        Route::get('/emergency/live-donor-count', [EmergencyController::class, 'getLiveDonorCount']);

        Route::post('/requests', [RequestController::class, 'create']);
        Route::get('/requests', [RequestController::class, 'index']);
        Route::patch('/requests/{id}/accept', [RequestController::class, 'accept']);
        Route::patch('/requests/{id}/fulfill', [RequestController::class, 'fulfill']);
        Route::delete('/requests/{id}', [RequestController::class, 'destroy']);
        Route::patch('/requests/{id}/verify', [RequestController::class, 'verify']);

        Route::get('/donors/search', [DonorController::class, 'search']);
        Route::get('/donors/live-count', [DonorController::class, 'liveCount']);
        Route::post('/donors/eligibility', [DonorController::class, 'saveEligibility']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'read']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'readAll']);

        Route::post('/admin/complaints', [AdminController::class, 'fileComplaint']);
        Route::post('/feedback', [FeedbackController::class, 'store']);
        Route::post('/support/tickets', [SupportTicketController::class, 'store']);

        // Legacy Admin Group Compatibility
        Route::middleware('jwt.role:admin,block_admin,super_admin,technical_admin')->group(function () {
            Route::get('/admin/users', [AdminController::class, 'getUsers']);
            Route::post('/admin/volunteers', [AdminController::class, 'addVolunteer']);
            Route::get('/admin/complaints', [AdminController::class, 'getComplaints']);
            Route::patch('/admin/complaints/{id}/resolve', [AdminController::class, 'resolveComplaint']);
            Route::patch('/admin/users/{id}/status', [AdminController::class, 'updateUserStatus']);
            Route::delete('/admin/users/{id}', [AdminController::class, 'deleteUser']);

            Route::post('/admin/users/{id}/warn', [AdminController::class, 'warnUser']);
            Route::patch('/admin/users/{id}/verify', [AdminController::class, 'verifyUser']);
            Route::patch('/admin/users/{id}/reject', [AdminController::class, 'rejectUser']);
            Route::patch('/admin/users/{id}/eligibility', [AdminController::class, 'updateUserEligibility']);

            Route::get('/admin/feedback', [FeedbackController::class, 'index']);
            Route::post('/admin/feedback/{id}/reply', [FeedbackController::class, 'reply']);
            Route::patch('/admin/feedback/{id}/status', [FeedbackController::class, 'updateStatus']);

            Route::get('/admin/tickets', [SupportTicketController::class, 'index']);
            Route::post('/admin/tickets/{id}/reply', [SupportTicketController::class, 'reply']);
            Route::patch('/admin/tickets/{id}/status', [SupportTicketController::class, 'updateStatus']);

            Route::get('/admin/activity-logs', [ActivityLogController::class, 'index']);
            Route::delete('/admin/activity-logs', [ActivityLogController::class, 'clear']);

            Route::get('/admin/stats', [AdminController::class, 'getDashboardStats']);
            Route::post('/admin/broadcast', [AdminController::class, 'broadcastNotification']);

            Route::post('/admin/partners', [PartnerController::class, 'store']);
            Route::post('/admin/partners/{id}', [PartnerController::class, 'update']);
            Route::delete('/admin/partners/{id}', [PartnerController::class, 'destroy']);
        });
    });
});
