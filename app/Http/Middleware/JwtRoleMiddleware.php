<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class JwtRoleMiddleware
{
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

        // Map allowed role aliases
        $hasAccess = false;
        foreach ($roles as $role) {
            $r = strtolower(trim($role));
            if ($userRole === $r) {
                $hasAccess = true;
                break;
            }

            // Role Aliases / Equivalents
            if ($r === 'technical_admin' && in_array($userRole, ['technical_admin', 'admin'], true)) {
                $hasAccess = true;
                break;
            }
            if ($r === 'block_admin' && in_array($userRole, ['block_admin', 'admin'], true)) {
                $hasAccess = true;
                break;
            }
            if ($r === 'user' && in_array($userRole, ['user', 'donor', 'patient'], true)) {
                $hasAccess = true;
                break;
            }
            if ($r === 'donor' && in_array($userRole, ['user', 'donor'], true)) {
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
