<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\Audit;
use App\Services\Features;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    public function index()
    {
        Gate::authorize('staff.manageAdmin');

        return view('admin.announcements', ['items' => Announcement::latest('id')->get(), 'audiences' => ['everyone' => 'All members'] + collect(Features::ALL)->map(fn ($l) => 'Members with '.strtolower($l))->all()]);
    }

    public function store(Request $request)
    {
        Gate::authorize('staff.manageAdmin');
        $a = Announcement::create($this->data($request) + ['created_by' => $request->user()->id]);
        Audit::record('announcement.created', $a, null, ['title' => $a->title], $request->user()->id);

        return back()->with('toast', ['type' => 'ok', 'message' => 'Notice published.']);
    }

    public function update(Request $request, Announcement $announcement)
    {
        Gate::authorize('staff.manageAdmin');
        $announcement->update($this->data($request));
        Audit::record('announcement.updated', $announcement, null, ['title' => $announcement->title], $request->user()->id);

        return back()->with('toast', ['type' => 'ok', 'message' => 'Notice saved.']);
    }

    public function destroy(Request $request, Announcement $announcement)
    {
        Gate::authorize('staff.manageAdmin');
        Audit::record('announcement.deleted', $announcement, ['title' => $announcement->title], null, $request->user()->id);
        $announcement->delete();

        return back()->with('toast', ['type' => 'info', 'message' => 'Notice removed.']);
    }

    private function data(Request $request): array
    {
        $d = $request->validate([
            'title' => ['required', 'string', 'max:120'], 'body' => ['required', 'string', 'max:1000'],
            'type' => ['required', Rule::in(array_keys(Announcement::TYPES))],
            'audience' => ['required', Rule::in(['everyone', ...array_keys(Features::ALL)])],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        return $d + ['dismissible' => $request->boolean('dismissible')];
    }
}
