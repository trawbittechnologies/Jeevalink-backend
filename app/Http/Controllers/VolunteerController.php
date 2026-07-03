<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Mail\VolunteerUserOtpMail;
use App\Mail\UserWelcomeMail;

class VolunteerController extends Controller
{
    /**
     * Send OTP to a user's email for profile update verification.
     */
    public function sendOtp(Request $request, $id)
    {
        $user = User::find((int)$id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'errors' => []
            ], 404);
        }

        // Generate a 6-digit OTP
        $otp = sprintf("%06d", mt_rand(1, 999999));
        
        // Cache the OTP for 10 minutes
        Cache::put('volunteer_update_otp_' . $user->id, $otp, now()->addMinutes(10));

        // Send email
        try {
            Mail::to($user->email)->send(new VolunteerUserOtpMail($otp, $user->full_name));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send OTP email: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP email.',
                'errors' => []
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP sent to user\'s email successfully.',
            'data' => []
        ]);
    }

    /**
     * Verify the OTP provided by the volunteer.
     */
    public function verifyOtp(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::find((int)$id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'errors' => []
            ], 404);
        }

        $cachedOtp = Cache::get('volunteer_update_otp_' . $user->id);

        if (!$cachedOtp || $cachedOtp !== $request->otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
                'errors' => []
            ], 400);
        }

        // OTP is valid. Mark verification as complete in cache for 15 minutes.
        // This allows the subsequent update request to pass.
        Cache::put('volunteer_update_verified_' . $user->id, true, now()->addMinutes(15));
        
        // Clear the OTP
        Cache::forget('volunteer_update_otp_' . $user->id);

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully.',
            'data' => []
        ]);
    }

    /**
     * Update user details (requires prior OTP verification).
     */
    public function updateUser(Request $request, $id)
    {
        $user = User::find((int)$id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'errors' => []
            ], 404);
        }

        // Check if OTP was verified
        $isVerified = Cache::get('volunteer_update_verified_' . $user->id);
        if (!$isVerified) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized update. Please verify OTP first.',
                'errors' => []
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'full_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'mobile' => 'sometimes|string|unique:users,mobile,' . $user->id,
            'blood_group' => 'sometimes|string',
            'district' => 'sometimes|string',
            'city' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->has('full_name')) $user->full_name = $request->full_name;
        if ($request->has('email')) $user->email = $request->email;
        if ($request->has('mobile')) $user->mobile = $request->mobile;
        if ($request->has('blood_group')) $user->blood_group = $request->blood_group;
        if ($request->has('district')) $user->district = $request->district;
        if ($request->has('city')) $user->city = $request->city;

        $user->save();

        // Clear verification to require it again next time
        Cache::forget('volunteer_update_verified_' . $user->id);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => [
                'user' => User::findById($user->id)
            ]
        ]);
    }

    /**
     * Add a new user (donor/patient). Generates a password and sends it via email.
     */
    public function addUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'mobile' => 'required|string|max:20',
            'role' => 'nullable|in:donor,patient',
            'city' => 'required|string|max:100',
            'district' => 'required|string|max:100',
            'blood_group' => 'required|string',
            'dob' => 'required|date',
            'sex' => 'required|in:male,female,transgender',
            'pincode' => 'required|string|size:6',
            'full_address' => 'required|string|min:5',
            'id_proof_front' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
            'id_proof_back' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
            'profile_picture' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
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

        $password = Str::random(10);

        $idProofFrontPath = null;
        if ($request->hasFile('id_proof_front')) {
            $idProofFrontPath = $request->file('id_proof_front')->store('id_proofs', 'public');
        }

        $idProofBackPath = null;
        if ($request->hasFile('id_proof_back')) {
            $idProofBackPath = $request->file('id_proof_back')->store('id_proofs', 'public');
        }

        $profilePicturePath = null;
        if ($request->hasFile('profile_picture')) {
            $profilePicturePath = $request->file('profile_picture')->store('profile_pictures', 'public');
        }

        $user = User::create([
            'full_name' => $request->full_name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'password_hash' => Hash::make($password),
            'role' => $request->role ?? 'donor',
            'blood_group' => $request->blood_group,
            'city' => $request->city,
            'district' => $request->district,
            'dob' => $request->dob,
            'sex' => $request->sex,
            'pincode' => $request->pincode,
            'full_address' => $request->full_address,
            'id_proof_front' => $idProofFrontPath,
            'id_proof_back' => $idProofBackPath,
            'profile_picture' => $profilePicturePath,
            'status' => 'Active',
            'is_verified' => true,
        ]);

        $emailSent = false;
        try {
            $loginUrl = env('FRONTEND_URL', 'http://localhost:5173') . '/login';
            Mail::to($user->email)->send(
                new UserWelcomeMail($user->full_name, $user->email, $password, $loginUrl)
            );
            $emailSent = true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("[addUser] Failed to send welcome email to {$user->email}: " . $e->getMessage());
            \Illuminate\Support\Facades\Log::error("[addUser] SMTP Config: host=" . env('MAIL_HOST') . " port=" . env('MAIL_PORT') . " user=" . env('MAIL_USERNAME') . " enc=" . env('MAIL_ENCRYPTION'));
            \Illuminate\Support\Facades\Log::error($e->getTraceAsString());
        }

        return response()->json([
            'success' => true,
            'message' => $emailSent ? 'User added successfully and credentials sent to email.' : 'User added successfully, but failed to send credentials email. Please share the password manually.',
            'data' => [
                'user' => User::findById($user->id),
                'email_sent' => $emailSent,
                'generated_password' => $emailSent ? null : $password,
            ]
        ], 201);
    }
}
