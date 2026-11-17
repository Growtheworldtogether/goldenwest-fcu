<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Features;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FeatureController extends Controller
{
    public function toggle(Request $request, User $user, string $feature)
    {
        Gate::authorize('staff.toggleFeatures');
        abort_if($user->isAdmin(), 404);
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:200']]);

        Features::set($user, $feature, (bool) $data['enabled'], $request->user(), $data['note'] ?? null);

        return back()->with('status', Features::ALL[$feature].($data['enabled'] ? ' turned on for ' : ' turned off for ').$user->name.'.');
    }
}
