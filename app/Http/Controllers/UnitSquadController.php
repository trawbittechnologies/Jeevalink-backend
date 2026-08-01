<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Str;

class UnitSquadController extends Controller
{
    /**
     * Get Unit Squad dashboard metrics.
     */
    public function dashboard(Request $request)
    {
        $user = $request->user() ?? auth()->user();
        $city = $user ? $user->city : null;

        $usersQuery = User::where('role', 'user');
        if ($city) $usersQuery->where('city', $city);
        $totalUsers = $usersQuery->count();

        return response()->json([
            'success' => true,
            'data' => [
                'unit' => $city ?? 'Local Unit',
                'total_users' => $totalUsers,
                'available_donors' => (clone $usersQuery)->where('available_for_donation', true)->count(),
                'verified_users' => (clone $usersQuery)->where('is_verified', true)->count(),
            ]
        ]);
    }

    /**
     * Get local users list.
     */
    public function getUsers(Request $request)
    {
        $user = $request->user() ?? auth()->user();
        $city = $user ? $user->city : null;

        $users = User::where('role', 'user')
            ->when($city, function($q) use ($city) {
                return $q->where('city', $city);
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

    /**
     * Create/Add a new user by Unit Squad.
     */
    public function createUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'primary_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'mobile' => 'required|string|unique:users,mobile',
            'blood_group' => 'required|string',
            'city' => 'required|string',
            'district' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $password = Str::random(10);

        $user = User::create([
            'name' => $request->primary_name,
            'primary_name' => $request->primary_name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'password_hash' => Hash::make($password),
            'role' => 'user',
            'blood_group' => $request->blood_group,
            'city' => $request->city,
            'district' => $request->district,
            'status' => 'Active',
            'is_verified' => true,
            'available_for_donation' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data' => [
                'user' => User::findById($user->id),
                'generated_password' => $password
            ]
        ], 201);
    }

    /**
     * Update user details.
     */
    public function updateUser(Request $request, $id)
    {
        $user = User::where('role', 'user')->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'primary_name' => 'sometimes|string',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'mobile' => 'sometimes|string|unique:users,mobile,' . $id,
            'blood_group' => 'sometimes|string',
            'available_for_donation' => 'sometimes|boolean',
            'is_verified' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->has('primary_name')) {
            $user->primary_name = $request->primary_name;
        }
        if ($request->has('email')) $user->email = $request->email;
        if ($request->has('mobile')) $user->mobile = $request->mobile;
        if ($request->has('blood_group')) $user->blood_group = $request->blood_group;
        if ($request->has('available_for_donation')) $user->available_for_donation = $request->available_for_donation;
        if ($request->has('is_verified')) $user->is_verified = $request->is_verified;

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => User::findById($user->id)
        ]);
    }

    /**
     * Delete user.
     */
    public function deleteUser(Request $request, $id)
    {
        $user = User::where('role', 'user')->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.'
        ]);
    }
}
