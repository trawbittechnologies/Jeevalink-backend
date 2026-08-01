<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\BloodRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BlockAdminController extends Controller
{
    /**
     * Get Block Admin dashboard.
     */
    public function dashboard(Request $request)
    {
        return $this->metrics($request);
    }

    /**
     * Get Block Admin metrics.
     */
    public function metrics(Request $request)
    {
        $user = $request->user() ?? auth()->user();
        $city = $user ? $user->city : null;
        $district = $user ? $user->district : null;

        $volunteersQuery = User::where('role', 'volunteer');
        if ($city) $volunteersQuery->where('city', $city);
        elseif ($district) $volunteersQuery->where('district', $district);
        $totalVolunteers = $volunteersQuery->count();

        $usersQuery = User::where('role', 'user');
        if ($city) $usersQuery->where('city', $city);
        elseif ($district) $usersQuery->where('district', $district);
        $totalUsers = $usersQuery->count();

        $requestsQuery = BloodRequest::query();
        if ($city) $requestsQuery->where('city', $city);
        elseif ($district) $requestsQuery->where('district', $district);
        $totalRequests = $requestsQuery->count();
        $pendingRequests = (clone $requestsQuery)->whereRaw('LOWER(status) = ?', ['pending'])->count();
        $fulfilledRequests = (clone $requestsQuery)->whereRaw('LOWER(status) = ?', ['fulfilled'])->count();

        return response()->json([
            'success' => true,
            'data' => [
                'city' => $city ?? 'Global Block',
                'district' => $district ?? 'Global District',
                'total_volunteers' => $totalVolunteers,
                'total_users' => $totalUsers,
                'total_requests' => $totalRequests,
                'pending_requests' => $pendingRequests,
                'fulfilled_requests' => $fulfilledRequests,
            ]
        ]);
    }

    /**
     * Get Volunteers list under this block.
     */
    public function getVolunteers(Request $request)
    {
        $user = $request->user() ?? auth()->user();
        $city = $user ? $user->city : null;
        $district = $user ? $user->district : null;

        $volunteers = User::where('role', 'volunteer')
            ->when($city, function($q) use ($city) {
                return $q->where('city', $city);
            })
            ->when(!$city && $district, function($q) use ($district) {
                return $q->where('district', $district);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $formatted = $volunteers->map(function ($v) {
            return [
                'id' => $v->id,
                'primary_name' => $v->primary_name ?? $v->name,
                'email' => $v->email,
                'mobile' => $v->mobile ?? 'N/A',
                'city' => $v->city ?? 'N/A',
                'district' => $v->district ?? 'N/A',
                'status' => $v->status ?? 'Active',
                'is_verified' => (bool)$v->is_verified,
                'secondary_name' => $v->secondary_name ?? '',
                'secondary_name' => $v->secondary_name ?? '',
                'secondaryContactNumber' => $v->secondary_phone ?? '',
                'secondary_contact_number' => $v->secondary_phone ?? '',
                'secondary_phone' => $v->secondary_phone ?? '',
                'whatsapp_number' => $v->whatsapp_number ?? '',
                'meghala' => $v->city ?? 'N/A',
                'created_at' => $v->created_at ? $v->created_at->toIso8601String() : null
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formatted
        ]);
    }

    /**
     * Create a new Volunteer.
     */
    public function createVolunteer(Request $request)
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

        $password = Str::random(10);

        $volunteer = User::create([
            'name' => $request->primary_name,
            'primary_name' => $request->primary_name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'secondary_name' => $request->secondary_name ?? $request->secondary_name ?? $request->person2Name ?? null,
            'secondary_phone' => $request->secondaryContactNumber ?? $request->secondary_contact_number ?? $request->secondary_phone ?? $request->person2Contact ?? null,
            'whatsapp_number' => $request->whatsapp_number ?? $request->whatsapp ?? null,
            'password_hash' => Hash::make($password),
            'role' => 'volunteer',
            'blood_group' => 'N/A',
            'city' => $request->city,
            'district' => $request->district,
            'status' => 'Active',
            'is_verified' => true,
        ]);

        try {
            Mail::raw(
                "Hello {$request->primary_name},\n\nYou have been registered as a Volunteer for {$request->city}.\n\nLogin credentials:\nEmail: {$request->email}\nPassword: {$password}\n\nLogin at: https://jeevalink-frontend.vercel.app/login",
                function ($mail) use ($request) {
                    $mail->to($request->email)->subject('JeevaLink - Volunteer Account Created');
                }
            );
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'message' => 'Volunteer created successfully.',
            'data' => [
                'user' => User::findById($volunteer->id),
                'generated_password' => $password
            ]
        ], 201);
    }

    /**
     * Update Volunteer.
     */
    public function updateVolunteer(Request $request, $id)
    {
        $volunteer = User::where('role', 'volunteer')->find($id);

        if (!$volunteer) {
            return response()->json([
                'success' => false,
                'message' => 'Volunteer not found.'
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
            $volunteer->primary_name = $request->primary_name;
            $volunteer->name = $request->primary_name;
        }
        if ($request->has('email')) $volunteer->email = $request->email;
        if ($request->has('mobile')) $volunteer->mobile = $request->mobile;
        if ($request->has('city')) $volunteer->city = $request->city;
        if ($request->has('district')) $volunteer->district = $request->district;
        if ($request->has('secondary_name') || $request->has('secondary_name') || $request->has('person2Name')) {
            $volunteer->secondary_name = $request->secondary_name ?? $request->secondary_name ?? $request->person2Name;
        }
        if ($request->has('secondaryContactNumber') || $request->has('secondary_contact_number') || $request->has('secondary_phone') || $request->has('person2Contact')) {
            $volunteer->secondary_phone = $request->secondaryContactNumber ?? $request->secondary_contact_number ?? $request->secondary_phone ?? $request->person2Contact;
        }
        if ($request->has('whatsapp_number')) $volunteer->whatsapp_number = $request->whatsapp_number;
        if ($request->has('status')) $volunteer->status = $request->status;

        $volunteer->save();

        return response()->json([
            'success' => true,
            'message' => 'Volunteer updated successfully.',
            'data' => User::findById($volunteer->id)
        ]);
    }

    /**
     * Delete Volunteer.
     */
    public function deleteVolunteer(Request $request, $id)
    {
        $volunteer = User::where('role', 'volunteer')->find($id);

        if (!$volunteer) {
            return response()->json([
                'success' => false,
                'message' => 'Volunteer not found.'
            ], 404);
        }

        $volunteer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Volunteer deleted successfully.'
        ]);
    }

    /**
     * Get Users in Block.
     */
    public function getUsers(Request $request)
    {
        $user = $request->user() ?? auth()->user();
        $city = $user ? $user->city : null;
        $district = $user ? $user->district : null;

        $users = User::where('role', 'user')
            ->when($city, function($q) use ($city) {
                return $q->where('city', $city);
            })
            ->when(!$city && $district, function($q) use ($district) {
                return $q->where('district', $district);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $formatted = $users->map(function ($u) {
            return User::findById($u->id);
        });

        return response()->json([
            'success' => true,
            'data' => $formatted
        ]);
    }
}
