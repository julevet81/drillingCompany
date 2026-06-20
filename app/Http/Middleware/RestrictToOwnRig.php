<?php

namespace App\Http\Middleware;

use App\Models\Rig;
use Closure;
use Illuminate\Http\Request;

class RestrictToOwnRig
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->hasRole('Rig_manager')) {
            $allowedRigIds = Rig::where('manager_id', $user->id)->pluck('id');
            $request->attributes->set('allowed_rig_ids', $allowedRigIds);
        }

        return $next($request);
    }
}