<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MemberRemoval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class MemberRemovalController extends Controller
{
    public function destroy(Request $request, User $user, MemberRemoval $removal)
    {
        Gate::authorize('staff.manageAdmin');
        abort_if($user->isAdmin(), 404);
        $data = $request->validate([
            'member_number' => ['required', 'string'],
            'reason' => ['required', 'string', 'max:500'],
        ], ['reason.required' => 'Write why this member is being removed.']);

        if (! hash_equals((string) $user->member_number, trim($data['member_number']))) {
            return back()->with('toast', ['type' => 'bad', 'message' => 'The member number you typed does not match.']);
        }
        try {
            $result = $removal->remove($user, $request->user(), $data['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->with('toast', ['type' => 'bad', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.members.index')->with('toast', ['type' => 'ok', 'message' => $result === 'erased'
            ? 'Member erased. They had never used their account.'
            : 'Member closed. Sign in is off and personal details are wiped. Their money records are kept.']);
    }
}
