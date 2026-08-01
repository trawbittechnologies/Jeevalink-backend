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
            Mail::to($user->email)->send(new VolunteerUserOtpMail($otp, $user->primary_name));
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
            'primary_name' => 'sometimes|string|max:255',
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

        if ($request->has('primary_name')) $user->primary_name = $request->primary_name;
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
            'message' => 'User details updated successfully',
            'data' => [
                'user' => User::findById($user->id)
            ]
        ]);
    }

    /**
     * Delete a user.
     * Volunteers can only delete users in their scope (same city).
     */
    public function deleteUser(Request $request, $id)
    {
        $auth = $request->user() ?? auth()->user();
        $user = User::find((int)$id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        // Prevent deleting admin or volunteer accounts
        if (in_array($user->role, ['super_admin', 'technical_admin', 'block_admin', 'volunteer'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete admin or volunteer accounts.',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.'
        ]);
    }

    /**
     * Add a new user (donor/patient). Generates a password and sends it via email.
     */
    public function addUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'primary_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'mobile' => 'required|string|max:20',
            'role' => 'nullable|in:user,donor,receiver',
            'city' => 'required|string|max:100',
            'district' => 'required|string|max:100',
            'blood_group' => 'required|string',
            'dob' => 'required|date',
            'sex' => 'required|in:male,female,transgender',
            'pincode' => 'required|string|size:6',
            'full_address' => 'nullable|string|min:5',
            'id_proof_front' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'id_proof_back' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'profile_picture' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if ($validator->fails()) {
            \Illuminate\Support\Facades\Log::error('Add user validation failed:', $validator->errors()->toArray());
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
            'primary_name' => $request->primary_name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'password_hash' => Hash::make($password),
            'role' => $request->role ?? 'user',
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
            'status' => 'Pending Approval',
            'is_verified' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User added successfully! Pending verification by Meghala Volunteer.',
            'data' => [
                'user' => User::findById($user->id),
                'email_sent' => false,
                'generated_password' => null,
            ]
        ], 201);
    }

    /**
     * Verify a user and send login credentials.
     */
    public function verifyUser(Request $request, $id)
    {
        $user = User::find((int)$id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $password = Str::random(10);
        $user->password_hash = Hash::make($password);
        $user->is_verified = true;
        $user->status = 'Active';
        $user->save();

        $emailSent = false;
        try {
            $loginUrl = env('FRONTEND_URL', 'http://localhost:5173') . '/login';
            Mail::to($user->email)->send(
                new UserWelcomeMail($user->primary_name, $user->email, $password, $loginUrl)
            );
            $emailSent = true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("[verifyUser] Failed to send welcome email to {$user->email}: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => $emailSent ? 'User verified and activated! Login credentials sent to email.' : 'User verified, but failed to send credential email.',
            'data' => [
                'user' => User::findById($user->id),
                'email_sent' => $emailSent,
                'generated_password' => $emailSent ? null : $password,
            ]
        ]);
    }

    /**
     * Reject a user registration.
     */
    public function rejectUser(Request $request, $id)
    {
        $user = User::find((int)$id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $user->is_verified = false;
        $user->status = 'Rejected';
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User registration rejected.',
            'data' => [
                'user' => User::findById($user->id)
            ]
        ]);
    }


    /**
     * Get Volunteer dashboard metrics.
     */
    public function dashboard(Request $request)
    {
        $user = $request->user() ?? auth()->user();
        $city = $user ? $user->city : null;

        $unitSquadsCount = User::where('role', 'unit_squad')
            ->when($city, function($q) use ($city) {
                return $q->where('city', $city);
            })->count();

        $usersCount = User::where('role', 'user')
            ->when($city, function($q) use ($city) {
                return $q->where('city', $city);
            })->count();

        return response()->json([
            'success' => true,
            'data' => [
                'city' => $city ?? 'Local Unit',
                'total_unit_squads' => $unitSquadsCount,
                'total_users' => $usersCount,
                'verified_donors' => User::where('role', 'user')->where('is_verified', true)->count(),
            ]
        ]);
    }

    /**
     * Get Unit Squad list under this volunteer.
     */
    public function getUnitSquads(Request $request)
    {
        $user = $request->user() ?? auth()->user();
        $city = $user ? $user->city : null;

        $unitSquads = User::where('role', 'unit_squad')
            ->when($city, function($q) use ($city) {
                return $q->where('city', $city);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $formatted = $unitSquads->map(function ($us) {
            return [
                'id' => $us->id,
                'primary_name' => $us->primary_name ?? $us->name,
                'email' => $us->email,
                'mobile' => $us->mobile ?? 'N/A',
                'city' => $us->city ?? 'N/A',
                'district' => $us->district ?? 'N/A',
                'status' => $us->status ?? 'Active',
                'secondary_name' => $us->secondary_name ?? '',
                'secondary_name' => $us->secondary_name ?? '',
                'secondaryContactNumber' => $us->secondary_phone ?? '',
                'secondary_contact_number' => $us->secondary_phone ?? '',
                'secondary_phone' => $us->secondary_phone ?? '',
                'whatsapp_number' => $us->whatsapp_number ?? '',
                'created_at' => $us->created_at ? $us->created_at->toIso8601String() : null
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formatted
        ]);
    }

    /**
     * Create a Unit Squad user.
     */
    public function createUnitSquad(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'primary_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'mobile' => 'required|string|unique:users,mobile',
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

        $password = $request->password ?: Str::random(10);

        $unitSquad = User::create([
            'name' => $request->primary_name,
            'primary_name' => $request->primary_name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'secondary_name' => $request->secondary_name ?? $request->secondary_name ?? null,
            'secondary_phone' => $request->secondaryContactNumber ?? $request->secondary_contact_number ?? $request->secondary_phone ?? null,
            'whatsapp_number' => $request->whatsapp_number ?? $request->whatsapp ?? null,
            'password_hash' => Hash::make($password),
            'role' => 'unit_squad',
            'blood_group' => 'N/A',
            'city' => $request->city,
            'district' => $request->district,
            'status' => 'Active',
            'is_verified' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Unit Squad created successfully.',
            'data' => [
                'user' => User::findById($unitSquad->id),
                'generated_password' => $password
            ]
        ], 201);
    }

    /**
     * Update Unit Squad.
     */
    public function updateUnitSquad(Request $request, $id)
    {
        $unitSquad = User::where('role', 'unit_squad')->find($id);

        if (!$unitSquad) {
            return response()->json([
                'success' => false,
                'message' => 'Unit Squad member not found.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'primary_name' => 'sometimes|string',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'mobile' => 'sometimes|string|unique:users,mobile,' . $id,
            'city' => 'sometimes|string',
            'district' => 'sometimes|string',
            'status' => 'sometimes|string|in:Active,Inactive,Suspended',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->has('primary_name')) {
            $unitSquad->primary_name = $request->primary_name;
        }
        if ($request->has('email')) $unitSquad->email = $request->email;
        if ($request->has('mobile')) $unitSquad->mobile = $request->mobile;
        if ($request->has('city')) $unitSquad->city = $request->city;
        if ($request->has('district')) $unitSquad->district = $request->district;
        if ($request->has('secondary_name') || $request->has('secondary_name')) {
            $unitSquad->secondary_name = $request->secondary_name ?? $request->secondary_name;
        }
        if ($request->has('secondaryContactNumber') || $request->has('secondary_contact_number') || $request->has('secondary_phone')) {
            $unitSquad->secondary_phone = $request->secondaryContactNumber ?? $request->secondary_contact_number ?? $request->secondary_phone;
        }
        if ($request->has('whatsapp_number')) $unitSquad->whatsapp_number = $request->whatsapp_number;
        if ($request->has('status')) $unitSquad->status = $request->status;

        $unitSquad->save();

        return response()->json([
            'success' => true,
            'message' => 'Unit Squad updated successfully.',
            'data' => User::findById($unitSquad->id)
        ]);
    }

    /**
     * Update Unit Squad status and handle credential resets.
     */
    public function updateUnitSquadStatus(Request $request, $id)
    {
        $unitSquad = User::where('role', 'unit_squad')->find($id);

        if (!$unitSquad) {
            return response()->json([
                'success' => false,
                'message' => 'Unit Squad member not found.'
            ], 404);
        }

        if ($request->has('status')) {
            $unitSquad->status = $request->status;
        }

        $generatedPassword = null;
        if ($request->boolean('resend_credentials') || $request->has('password')) {
            $rawPass = $request->password ?: Str::random(10);
            $unitSquad->password_hash = Hash::make($rawPass);
            $unitSquad->password = $rawPass;
            $generatedPassword = $rawPass;
        }

        $unitSquad->save();

        return response()->json([
            'success' => true,
            'message' => 'Unit Squad status updated successfully.',
            'data' => [
                'squad' => User::findById($unitSquad->id),
                'generated_password' => $generatedPassword
            ]
        ]);
    }

    /**
     * Delete Unit Squad.
     */
    public function deleteUnitSquad(Request $request, $id)
    {
        $unitSquad = User::where('role', 'unit_squad')->find($id);

        if (!$unitSquad) {
            return response()->json([
                'success' => false,
                'message' => 'Unit Squad member not found.'
            ], 404);
        }

        $unitSquad->delete();

        return response()->json([
            'success' => true,
            'message' => 'Unit Squad member deleted successfully.'
        ]);
    }

    /**
     * Fetch pending requests for volunteers.
     */
    public function pendingRequests(Request $request)
    {
        $requests = \App\Models\BloodRequest::getAll(['verified' => '0']);

        return response()->json([
            'success' => true,
            'message' => 'Pending requests retrieved successfully.',
            'data' => [
                'requests' => $requests
            ]
        ]);
    }

    /**
     * Public endpoint to fetch volunteers and block committee contacts for visitors.
     *
     * Hierarchy (flat users table):
     *   District  → users.district
     *   Block     → city of block_admin users in that district
     *   Meghala   → city of volunteer / unit_squad users in that district
     *
     * Query params:
     *   district            (string) — filter by district name
     *   blockCommitteeName  (string) — the block admin's city (used for district scope)
     *   meghala             (string) — the volunteer/unit_squad's city
     */
    public function publicVolunteerDirectory(Request $request)
    {
        $district  = trim($request->query('district', ''));
        $blockName = trim($request->query('blockCommitteeName') ?: $request->query('block', ''));
        $meghala   = trim($request->query('meghala') ?: $request->query('unit', ''));

        // Show block_admins, volunteers, and unit_squads in the public directory
        $query = User::whereIn('role', ['block_admin', 'volunteer', 'unit_squad'])
            ->where('status', 'Active');

        // Always scope by district when provided
        if ($district !== '') {
            $query->where('district', $district);
        }

        // If a specific meghala (volunteer city) is selected, show ONLY that meghala's contacts.
        // Use exact case-insensitive match so "cheemeni" does NOT match "cheemeni east".
        if ($meghala !== '' && $meghala !== 'All' && $meghala !== 'All Meghala Units') {
            $query->whereRaw('LOWER(city) = ?', [strtolower($meghala)]);
        }
        // If only a block is selected (no meghala filter) — return everyone in the district:
        // the block admin(s) and all volunteers/unit_squads in the same district.
        // (No additional filter needed — district filter above already scopes correctly.)

        $users = $query
            ->select([
                'id', 'primary_name', 'mobile', 'secondary_phone',
                'whatsapp_number', 'blood_group', 'city', 'district',
                'role', 'status', 'remarks', 'organization_name',
            ])
            ->orderByRaw("
                CASE
                    WHEN role = 'block_admin'  THEN 0
                    WHEN role = 'unit_squad'   THEN 1
                    WHEN role = 'volunteer'    THEN 2
                    ELSE 3
                END
            ")
            ->take(100)
            ->get()
            ->map(function ($u) {
                return [
                    'id'              => $u->id,
                    'name'            => $u->primary_name ?? 'Volunteer',
                    'primary_name'    => $u->primary_name ?? 'Volunteer',
                    'mobile'          => $u->mobile ?? '',
                    'phone'           => $u->mobile ?? '',
                    'secondary_phone' => $u->secondary_phone ?? '',
                    'whatsapp_number' => $u->whatsapp_number ?? '',
                    'blood_group'     => $u->blood_group ?? 'N/A',
                    'city'            => $u->city ?? '',
                    'district'        => $u->district ?? '',
                    'meghala'         => $u->city ?? '',  // city IS the meghala/area name
                    'role'            => $u->role,
                    'status'          => $u->status,
                    'remarks'         => $u->remarks ?? '',
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Volunteer directory fetched successfully.',
            'data'    => $users,
        ]);
    }

    /**
     * Public endpoint to fetch distinct database options for the volunteer directory.
     *
     * Returns:
     *   blocks_by_district  — { "Kasaragod": ["Nileswar", ...], ... }
     *     Derived from block_admin users: their `city` field IS their Block Committee name.
     *
     *   meghalas_by_block   — { "Nileswar": ["cheemeni", "cheemeni east", ...], ... }
     *     Derived from volunteer / unit_squad users: their `city` field IS their Meghala name.
     *     Meghalas are grouped under every block that belongs to the same district,
     *     since the flat users table has no direct block↔volunteer foreign key.
     */
    public function publicVolunteerOptions(Request $request)
    {
        // ── Step 1: Build blocks_by_district from block_admin users ──────────────
        // Each block_admin's `city` field holds the name of their Block Committee.
        $blockAdmins = User::where('role', 'block_admin')
            ->whereNotNull('district')->where('district', '!=', '')
            ->whereNotNull('city')->where('city', '!=', '')
            ->select('district', 'city')
            ->get();

        $blocksByDistrict = [];
        foreach ($blockAdmins as $ba) {
            $dist      = trim($ba->district);
            $blockName = trim($ba->city);
            if ($dist && $blockName) {
                if (!isset($blocksByDistrict[$dist])) {
                    $blocksByDistrict[$dist] = [];
                }
                if (!in_array($blockName, $blocksByDistrict[$dist], true)) {
                    $blocksByDistrict[$dist][] = $blockName;
                }
            }
        }

        // ── Step 2: Build meghalas_by_block from volunteer / unit_squad users ────
        // Each volunteer/unit_squad's `city` field holds their Meghala (local unit) name.
        // Because the flat schema has no block_id FK on volunteers, we scope meghalas
        // to every block that belongs to the same district.
        $volunteerRows = User::whereIn('role', ['volunteer', 'unit_squad'])
            ->whereNotNull('district')->where('district', '!=', '')
            ->whereNotNull('city')->where('city', '!=', '')
            ->select('district', 'city')
            ->get();

        // Index volunteer cities by district for fast lookup
        $meghalasByDistrict = [];
        foreach ($volunteerRows as $vr) {
            $dist    = trim($vr->district);
            $meghala = trim($vr->city);
            if ($dist && $meghala) {
                if (!isset($meghalasByDistrict[$dist])) {
                    $meghalasByDistrict[$dist] = [];
                }
                if (!in_array($meghala, $meghalasByDistrict[$dist], true)) {
                    $meghalasByDistrict[$dist][] = $meghala;
                }
            }
        }

        // Map meghalas under each block (all meghalas in a district → all blocks in that district)
        $meghalasByBlock = [];
        foreach ($blocksByDistrict as $dist => $blocks) {
            $distMeghalas = $meghalasByDistrict[$dist] ?? [];
            foreach ($blocks as $block) {
                if (!isset($meghalasByBlock[$block])) {
                    $meghalasByBlock[$block] = [];
                }
                foreach ($distMeghalas as $meghala) {
                    if (!in_array($meghala, $meghalasByBlock[$block], true)) {
                        $meghalasByBlock[$block][] = $meghala;
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'blocks_by_district' => $blocksByDistrict,
                'meghalas_by_block'  => $meghalasByBlock,
            ],
        ]);
    }
}


