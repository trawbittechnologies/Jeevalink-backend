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
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

Route::prefix('v1')->group(function () {
    // ─── Authentication Routes ──────────────────────────────────────────
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::get('/location/pincode/{pincode}', [AuthController::class, 'pincodeLookup']);
    Route::post('/test-notification', [NotificationController::class, 'testNotification']);
    Route::get('/partners', [PartnerController::class, 'index']);

    // ─── Authenticated Routes Group ──────────────────────────────────────
    Route::middleware('jwt.auth')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::patch('/auth/profile', [AuthController::class, 'profile']);
        Route::patch('/auth/toggle-availability', [AuthController::class, 'toggleAvailability']);
        Route::post('/auth/push-token', [AuthController::class, 'pushToken']);
        
        // ─── Emergency Blood Alert Routes ───────────────────────────────────
        Route::post('/save-fcm-token', [EmergencyController::class, 'saveFcmToken']);
        Route::post('/emergency/request', [EmergencyController::class, 'createRequest']);
        Route::get('/emergency/history', [EmergencyController::class, 'getHistory']);
        Route::get('/emergency/details/{id}', [EmergencyController::class, 'getDetails']);
        Route::post('/emergency/accept', [EmergencyController::class, 'acceptRequest']);
        Route::post('/emergency/reject', [EmergencyController::class, 'rejectRequest']);
        Route::get('/emergency/nearby-donors', [EmergencyController::class, 'getNearbyDonors']);
        Route::get('/emergency/live-donor-count', [EmergencyController::class, 'getLiveDonorCount']);

        // ─── Blood Request Routes ───────────────────────────────────────────
        Route::post('/requests', [RequestController::class, 'create']);
        Route::get('/requests', [RequestController::class, 'index']);
        Route::patch('/requests/{id}/accept', [RequestController::class, 'accept']);
        Route::patch('/requests/{id}/fulfill', [RequestController::class, 'fulfill']);
        Route::delete('/requests/{id}', [RequestController::class, 'destroy']);

        // ─── Donor Routes ───────────────────────────────────────────────────
        Route::get('/donors/search', [DonorController::class, 'search']);
        Route::get('/donors/live-count', [DonorController::class, 'liveCount']);
        Route::post('/donors/eligibility', [DonorController::class, 'saveEligibility']);

        // ─── Notification Routes ────────────────────────────────────────────
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'read']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'readAll']);

        // ─── Complaint Submission Route ─────────────────────────────────────
        Route::post('/admin/complaints', [AdminController::class, 'fileComplaint']);

        // ─── Feedback Routes (public submit, admin management) ──────────────
        Route::post('/feedback', [FeedbackController::class, 'store']);

        // ─── Support Ticket Routes (user submission) ────────────────────────
        Route::post('/support/tickets', [SupportTicketController::class, 'store']);

        // ─── Admin/Volunteer Shared Routes ──────────────────────────────────
        Route::middleware('jwt.role:admin,volunteer')->group(function () {
            Route::patch('/requests/{id}/verify', [RequestController::class, 'verify']);
            Route::get('/admin/users', [AdminController::class, 'getUsers']);
            Route::post('/admin/volunteers', [AdminController::class, 'addVolunteer']); // Generic user add
            
            // Volunteer OTP & Update Routes
            Route::post('/volunteer/users/{id}/send-otp', [VolunteerController::class, 'sendOtp']);
            Route::post('/volunteer/users/{id}/verify-otp', [VolunteerController::class, 'verifyOtp']);
            Route::patch('/volunteer/users/{id}', [VolunteerController::class, 'updateUser']);
        });

        // ─── Admin Only Routes ──────────────────────────────────────────────
        Route::middleware('jwt.role:admin')->group(function () {

            // Existing Admin Routes
            Route::get('/admin/complaints', [AdminController::class, 'getComplaints']);
            Route::patch('/admin/complaints/{id}/resolve', [AdminController::class, 'resolveComplaint']);
            Route::patch('/admin/users/{id}/status', [AdminController::class, 'updateUserStatus']);
            Route::delete('/admin/users/{id}', [AdminController::class, 'deleteUser']);

            Route::post('/admin/users/{id}/warn', [AdminController::class, 'warnUser']);
            Route::patch('/admin/users/{id}/verify', [AdminController::class, 'verifyUser']);
            Route::patch('/admin/users/{id}/reject', [AdminController::class, 'rejectUser']);
            Route::patch('/admin/users/{id}/eligibility', [AdminController::class, 'updateUserEligibility']);

            // ── Feedback Management ──────────────────────────────────────────
            Route::get('/admin/feedback', [FeedbackController::class, 'index']);
            Route::post('/admin/feedback/{id}/reply', [FeedbackController::class, 'reply']);
            Route::patch('/admin/feedback/{id}/status', [FeedbackController::class, 'updateStatus']);

            // ── Support Ticket Management ────────────────────────────────────
            Route::get('/admin/tickets', [SupportTicketController::class, 'index']);
            Route::post('/admin/tickets/{id}/reply', [SupportTicketController::class, 'reply']);
            Route::patch('/admin/tickets/{id}/status', [SupportTicketController::class, 'updateStatus']);

            // ── Activity Logs ────────────────────────────────────────────────
            Route::get('/admin/activity-logs', [ActivityLogController::class, 'index']);
            Route::delete('/admin/activity-logs', [ActivityLogController::class, 'clear']);

            // ── Dashboard Stats ──────────────────────────────────────────────
            Route::get('/admin/stats', [AdminController::class, 'getDashboardStats']);

            // ── Notifications Broadcast ──────────────────────────────────────
            Route::post('/admin/broadcast', [AdminController::class, 'broadcastNotification']);

            // ── Partners/Collaborators Management ────────────────────────────
            Route::post('/admin/partners', [PartnerController::class, 'store']);
            Route::post('/admin/partners/{id}', [PartnerController::class, 'update']);
            Route::delete('/admin/partners/{id}', [PartnerController::class, 'destroy']);
        });
    });
});
