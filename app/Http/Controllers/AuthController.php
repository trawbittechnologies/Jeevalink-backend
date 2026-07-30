<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Helpers\JWT;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Handle user registration.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'mobile' => 'required|string|max:20',
            'password' => 'required|string|min:6',
            'role' => 'nullable|in:user,volunteer,unit_squad,block_admin,super_admin,technical_admin',
            'city' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'blood_group' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-,N/A',
            'pincode' => 'nullable|string|max:20',
            'full_address' => 'nullable|string',
            'dob' => 'nullable|date',
            'id_proof_front' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
            'id_proof_back' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
            'sex' => 'nullable|in:male,female,transgender',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if ($validator->fails()) {
            \Illuminate\Support\Facades\Log::error('Registration Validation Failed', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check unique constraints manually to replicate original 409 behavior
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

        // Set status and default role
        $role = $request->role ?? 'user';
        $status = 'Active';
        $available = $request->has('available_for_donation') ? (bool)$request->available_for_donation : true;

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
            'password_hash' => Hash::make($request->password),
            'role' => $role,
            'blood_group' => $request->blood_group ?? 'N/A',
            'city' => $request->city ?? '',
            'district' => $request->district ?? '',
            'pincode' => $request->pincode ?? null,
            'full_address' => $request->address ?? $request->full_address ?? null,
            'weight' => $request->weight ?? null,
            'dob' => $request->dob ?? null,
            'last_donated_date' => $request->last_donated_date ?? null,
            'profile_picture' => $profilePicturePath,
            'id_proof_front' => $idProofFrontPath,
            'id_proof_back' => $idProofBackPath,
            'is_verified' => false,
            'available_for_donation' => $available,
            'status' => $status,
            'sex' => $request->sex ?? null,
        ]);

        $token = JWT::generateToken($user->id, $user->role);

        // Retrieve fresh user info to match findById query results
        $userData = User::findById($user->id);

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully.',
            'data' => [
                'token' => $token,
                'user' => $userData
            ]
        ], 201);
    }

    /**
     * Handle user authentication login.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'credential' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $credential = trim($request->credential);
        $password = $request->password;

        // Find user by email first, then mobile
        $user = null;
        if (filter_var($credential, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', $credential)->first();
        } else {
            $user = User::where('mobile', $credential)->first();
        }

        if (!$user || !Hash::check($password, $user->password_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid login credentials.',
                'errors' => []
            ], 401);
        }

        // Check if user is suspended/rejected
        if (in_array($user->status, ['Suspended', 'Rejected'], true)) {
            return response()->json([
                'success' => false,
                'message' => "Your account has been {$user->status}. Please contact support.",
                'errors' => []
            ], 403);
        }

        $token = JWT::generateToken($user->id, $user->role);
        $profile = User::findById($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Authentication successful.',
            'data' => [
                'token' => $token,
                'user' => $profile
            ]
        ]);
    }

    /**
     * Get details of currently authenticated user.
     */
    public function me(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User profile not found.',
                'errors' => []
            ], 404);
        }

        $profile = User::findById($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Profile retrieved successfully.',
            'data' => [
                'user' => $profile
            ]
        ]);
    }

    /**
     * Update user profile information.
     */
    public function profile(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'errors' => []
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'full_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email,' . $user->id,
            'mobile' => 'nullable|string|max:20|unique:users,mobile,' . $user->id,
            'blood_group' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-,N/A',
            'dob' => 'nullable|date_format:Y-m-d',
            'last_donated_date' => 'nullable|date_format:Y-m-d',
            'weight' => 'nullable|numeric',
            'sex' => 'nullable|in:male,female,transgender',
            'pincode' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $updated = User::updateProfile($user->id, $request->all());

        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'No profile updates were made.',
                'errors' => []
            ], 400);
        }

        $profile = User::findById($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => [
                'user' => $profile
            ]
        ]);
    }

    /**
     * Toggle active availability state for blood donations.
     */
    public function toggleAvailability(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'errors' => []
            ], 404);
        }

        $toggled = User::toggleAvailability($user->id);

        if (!$toggled) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update availability status.',
                'errors' => []
            ], 500);
        }

        $freshUser = User::find($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Availability status updated successfully.',
            'data' => [
                'available_for_donation' => (bool)$freshUser->available_for_donation
            ]
        ]);
    }

    /**
     * Store push notification token.
     */
    public function pushToken(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'errors' => []
            ], 404);
        }

        if (!$request->has('push_token')) {
            return response()->json([
                'success' => false,
                'message' => 'Missing parameter: push_token',
                'errors' => []
            ], 400);
        }

        $updated = User::updatePushToken($user->id, $request->push_token);

        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to store push token.',
                'errors' => []
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Push notification token updated successfully.',
            'data' => []
        ]);
    }

    /**
     * Look up PIN code details.
     */
    public function pincodeLookup($pincode)
    {
        try {
            $response = \Illuminate\Support\Facades\Http::get("https://api.postalpincode.in/pincode/{$pincode}");
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data[0]) && $data[0]['Status'] === 'Success') {
                    $postOffice = $data[0]['PostOffice'][0];
                    return response()->json([
                        'success' => true,
                        'district' => $postOffice['District'],
                        'state' => $postOffice['State'],
                        'country' => $postOffice['Country'] ?? 'India'
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Ignore API exceptions and return default failure
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid PIN Code or Service Unavailable',
            'district' => '',
            'state' => '',
            'country' => 'India'
        ], 200);
    }

    /**
     * Send forgot password email.
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Email not found in our records',
                'errors' => $validator->errors()
            ], 422);
        }

        $token = \Illuminate\Support\Str::random(60);

        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => $token, 'created_at' => now()]
        );

        try {
            $resetUrl = env('FRONTEND_URL', 'http://localhost:5173') . '/reset-password?token=' . $token . '&email=' . urlencode($request->email);
            \Illuminate\Support\Facades\Mail::to($request->email)->send(
                new \App\Mail\ForgotPasswordMail($resetUrl)
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Password reset link sent to your email.'
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info("Reset link for {$request->email}: $resetUrl");
            \Illuminate\Support\Facades\Log::error("Failed to send reset email: " . $e->getMessage());
            
            // Delete the token since email failed
            \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send password reset email. Please try again later.'
            ], 500);
        }
    }

    /**
     * Reset password.
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $reset = \Illuminate\Support\Facades\DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$reset) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired password reset token.',
                'errors' => []
            ], 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->password_hash = Hash::make($request->password);
        $user->save();

        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password has been successfully reset.'
        ]);
    }

    /**
     * Handle user logout.
     */
    public function logout(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.'
        ]);
    }

    /**
     * Refresh JWT authentication token.
     */
    public function refresh(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
                'errors' => []
            ], 401);
        }

        $newToken = JWT::generateToken($user->id, $user->role);
        $profile = User::findById($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully.',
            'data' => [
                'token' => $newToken,
                'user' => $profile
            ]
        ]);
    }

    /**
     * Change authenticated user password.
     */
    public function changePassword(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
                'errors' => []
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!Hash::check($request->current_password, $user->password_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
                'errors' => ['current_password' => ['Current password is incorrect.']]
            ], 400);
        }

        $user->password_hash = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.'
        ]);
    }
}

