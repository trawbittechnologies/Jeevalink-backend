<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class JwtRoleMiddleware
{
    /**
     * Role hierarchy levels (lower number = higher authority).
     */
    protected const ROLE_HIERARCHY = [
        'technical_admin' => 1,
        'super_admin'     => 2,
        'block_admin'     => 3,
        'volunteer'       => 4,
        'unit_squad'      => 5,
        'user'            => 6,
    ];

    /**
     * Handle an incoming request with role checking.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
                'errors' => []
            ], 401);
        }

        $userRole = strtolower(trim($user->role));
        $userLevel = self::ROLE_HIERARCHY[$userRole] ?? 6;

        $hasAccess = false;
        foreach ($roles as $role) {
            $r = strtolower(trim($role));
            
            // Direct exact role match
            if ($userRole === $r) {
                $hasAccess = true;
                break;
            }

            // Hierarchy level check (higher level roles can access lower level endpoints)
            $requiredLevel = self::ROLE_HIERARCHY[$r] ?? null;
            if ($requiredLevel !== null && $userLevel <= $requiredLevel) {
                $hasAccess = true;
                break;
            }
        }

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You do not have permission to perform this action.',
                'errors' => []
            ], 403);
        }

        return $next($request);
    }
}
