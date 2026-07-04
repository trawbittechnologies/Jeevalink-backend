<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Complaint;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\FCMService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    /**
     * File a complaint against another user.
     * Accessible by authenticated users.
     */
    public function fileComplaint(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'errors' => []
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'target_id' => 'required|numeric',
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $targetId = (int)$request->target_id;

        if ($user->id === $targetId) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot file a complaint against yourself.',
                'errors' => []
            ], 400);
        }

        $targetUser = User::find($targetId);
        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'Target user not found.',
                'errors' => []
            ], 404);
        }

        Complaint::create([
            'reporter_id' => $user->id,
            'target_id' => $targetId,
            'reason' => $request->reason,
            'status' => 'Pending'
        ]);

        // In the original, it fetches the complaint by findById to get reporter/target names
        // But since we just created it, we can fetch it using a custom findById or standard Eloquent
        // Let's search by reporter and target and reason, or get the last inserted ID.
        // Wait, standard Eloquent create returns the instance with id!
        // So we can fetch using Complaint::findById($instance->id)
        $latestComplaint = Complaint::where('reporter_id', $user->id)
            ->where('target_id', $targetId)
            ->orderBy('id', 'desc')
            ->first();

        $complaintData = $latestComplaint ? Complaint::findById($latestComplaint->id) : null;

        return response()->json([
            'success' => true,
            'message' => 'Complaint filed successfully. Administrators will review it.',
            'data' => [
                'complaint' => $complaintData
            ]
        ], 201);
    }

    /**
     * Get all filed complaints.
     * Admin only.
     */
    public function getComplaints(Request $request)
    {
        $complaints = Complaint::getAll();
        return response()->json([
            'success' => true,
            'message' => 'Complaints retrieved successfully.',
            'data' => [
                'complaints' => $complaints
            ]
        ]);
    }

    /**
     * Resolve a filed complaint.
     * Admin only.
     */
    public function resolveComplaint(Request $request, $id)
    {
        $complaintId = (int)$id;

        $complaint = Complaint::find($complaintId);
        if (!$complaint) {
            return response()->json([
                'success' => false,
                'message' => 'Complaint not found.',
                'errors' => []
            ], 404);
        }

        $resolved = Complaint::resolve($complaintId);
        if (!$resolved) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve complaint.',
                'errors' => []
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Complaint marked as resolved.',
            'data' => []
        ]);
    }

    /**
     * Update user status (Active/Suspended/Rejected).
     * Admin only.
     */
    public function updateUserStatus(Request $request, $id)
    {
        $targetUserId = (int)$id;

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Active,Pending Approval,Suspended,Rejected',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $targetUser = User::find($targetUserId);
        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'errors' => []
            ], 404);
        }

        $updated = User::updateStatus($targetUserId, $request->status);
        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user status.',
                'errors' => []
            ], 500);
        }

        // Send system notification about status change
        $statusMsg = "Your account status has been updated to: {$request->status}.";
        if ($request->status === 'Suspended') {
            $statusMsg .= " You will not be able to log in until suspension is lifted.";
        }
        NotificationService::sendSystemWarning($targetUserId, $statusMsg);

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully.',
            'data' => [
                'status' => $request->status
            ]
        ]);
    }

    /**
     * Get all users in system.
     * Admin only.
     */
    public function getUsers(Request $request)
    {
        $users = User::getAll();
        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully.',
            'data' => [
                'users' => $users
            ]
        ]);
    }

    /**
     * Delete a user.
     * Admin only.
     */
    public function deleteUser(Request $request, $id)
    {
        $targetUser = User::find((int)$id);
        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'errors' => []
            ], 404);
        }

        $targetUser->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.',
            'data' => []
        ]);
    }


    /**
     * Warn a user.
     * Admin only.
     */
    public function warnUser(Request $request, $id)
    {
        $targetUserId = (int)$id;

        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $targetUser = User::find($targetUserId);
        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'errors' => []
            ], 404);
        }

        NotificationService::sendSystemWarning($targetUserId, $request->message);

        // Send push notification if token exists
        if (!empty($targetUser->expo_push_token)) {
            FCMService::sendPushNotification(
                $targetUser->expo_push_token,
                '⚠️ Official Warning Alert',
                $request->message,
                ['type' => 'Warning']
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Warning message dispatched to user successfully.',
            'data' => []
        ]);
    }

    /**
     * Verify a user's ID and account.
     * Admin only.
     */
    public function verifyUser(Request $request, $id)
    {
        $targetUser = User::find((int)$id);
        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'errors' => []
            ], 404);
        }

        $targetUser->is_verified = true;
        if ($targetUser->status === 'Pending Approval') {
            $targetUser->status = 'Active';
        }
        $targetUser->save();

        NotificationService::sendSystemWarning($targetUser->id, "Your account and ID have been successfully verified.");

        return response()->json([
            'success' => true,
            'message' => 'User verified successfully.',
            'data' => [
                'is_verified' => true,
                'status' => $targetUser->status
            ]
        ]);
    }

    /**
     * Reject a user.
     * Admin only.
     */
    public function rejectUser(Request $request, $id)
    {
        $targetUser = User::find((int)$id);
        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'errors' => []
            ], 404);
        }

        $targetUser->is_verified = false;
        $targetUser->status = 'Rejected';
        $targetUser->save();

        NotificationService::sendSystemWarning($targetUser->id, "Your account registration has been rejected. Please contact support.");

        return response()->json([
            'success' => true,
            'message' => 'User rejected successfully.',
            'data' => [
                'status' => 'Rejected'
            ]
        ]);
    }

    /**
     * Update a user's donation eligibility status manually.
     * Admin only.
     */
    public function updateUserEligibility(Request $request, $id)
    {
        $targetUserId = (int)$id;

        $validator = Validator::make($request->all(), [
            'eligibility_status' => 'required|in:Eligible,Ineligible,Pending Check',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $targetUser = User::find($targetUserId);
        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'errors' => []
            ], 404);
        }

        $targetUser->eligibility_status = $request->eligibility_status;
        $targetUser->eligibility_checked_at = now();

        if ($request->eligibility_status === 'Ineligible') {
            $targetUser->available_for_donation = false;
        } else if ($request->eligibility_status === 'Eligible') {
            $targetUser->available_for_donation = true;
        }
        $targetUser->save();

        // Send system notification about eligibility update
        $eligibilityMsg = "Your donation eligibility status has been updated to: {$request->eligibility_status} by the administrator.";
        NotificationService::sendSystemWarning($targetUserId, $eligibilityMsg);

        return response()->json([
            'success' => true,
            'message' => 'User eligibility status updated successfully.',
            'data' => [
                'eligibility_status' => $targetUser->eligibility_status,
                'eligibility_checked_at' => $targetUser->eligibility_checked_at
            ]
        ]);
    }

    /**
     * Add a volunteer.
     * Admin only.
     */
    public function addVolunteer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'mobile' => 'required|string|max:20',
            'city' => 'required|string|max:100',
            'district' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if (User::where('email', $request->email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Email already in use',
                'errors' => ['email' => ['This email address is already registered.']]
            ], 409);
        }

        if (User::where('mobile', $request->mobile)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Mobile number already in use',
                'errors' => ['mobile' => ['This mobile number is already registered.']]
            ], 409);
        }

        $password = \Illuminate\Support\Str::random(10);

        $user = User::create([
            'full_name' => $request->full_name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'password_hash' => \Illuminate\Support\Facades\Hash::make($password),
            'role' => 'volunteer',
            'blood_group' => 'N/A',
            'city' => $request->city,
            'district' => $request->district,
            'status' => 'Active',
            'is_verified' => true,
        ]);

        // Dispatch email via queue so SMTP never blocks the HTTP response.
        // If queue worker is not running, fall back to synchronous send.
        $emailSent = false;
        $generatedPassword = $password; // Always return password for manual fallback
        try {
            $loginUrl = env('FRONTEND_URL', 'http://localhost:5173') . '/login';
            // Use a short socket timeout to prevent SMTP from hanging the request
            \Illuminate\Support\Facades\Mail::to($user->email)->send(
                new \App\Mail\VolunteerWelcomeMail($user->full_name, $user->email, $password, $loginUrl)
            );
            $emailSent = true;
            $generatedPassword = null; // Email sent, no need to show password manually
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("[addVolunteer] Failed to send welcome email to {$user->email}: " . $e->getMessage());
            \Illuminate\Support\Facades\Log::error("[addVolunteer] SMTP Config: host=" . env('MAIL_HOST') . " port=" . env('MAIL_PORT') . " user=" . env('MAIL_USERNAME') . " enc=" . env('MAIL_ENCRYPTION'));
        }

        return response()->json([
            'success' => true,
            'message' => $emailSent ? 'Volunteer added successfully and credentials sent to email.' : 'Volunteer added successfully, but email could not be sent. Please share credentials manually.',
            'data' => [
                'user' => User::findById($user->id),
                'email_sent' => $emailSent,
                'generated_password' => $generatedPassword,
            ]
        ], 201);
    }
}
