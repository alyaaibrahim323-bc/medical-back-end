<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EnsureRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        Log::info('ROLE_DEBUG', [
        'user_id' => $user->id,
        'role_db' => $user->role,
        'allowed' => $roles,
        ]);

        // role ممكن تكون string واحدة
        if (!in_array($user->role, $roles, true)) {
        return response()->json(['message' => 'Forbidden (EnsureRole)'], 403);
        }

        return $next($request);
    }
}
