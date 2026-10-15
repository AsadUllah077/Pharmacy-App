<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireBranch
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user->active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403, 'Account is inactive.');
        }
        $branch = (int) $request->session()->get('branch_id', 0);
        if (! $branch) {
            $branch = (int) $user->branches()->orderBy('branches.id')->value('branches.id');
            $request->session()->put('branch_id', $branch);
        }
        abort_unless($branch && $user->hasBranch($branch), 403, 'No authorized branch selected.');
        $request->session()->put('branch_id', $branch);

        return $next($request);
    }
}
