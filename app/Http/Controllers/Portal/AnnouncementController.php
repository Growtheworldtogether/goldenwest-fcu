<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnnouncementController extends Controller
{
    public function dismiss(Request $request, Announcement $announcement)
    {
        abort_unless($announcement->dismissible, 403);
        DB::table('announcement_dismissals')->insertOrIgnore(['announcement_id' => $announcement->id, 'user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return back();
    }
}
