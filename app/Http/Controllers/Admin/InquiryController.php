<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InquiryController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('staff.answerInquiries');
        $show = $request->query('show') === 'handled' ? 'handled' : 'open';

        return view('admin.inquiries', [
            'show' => $show,
            'inquiries' => Inquiry::when($show === 'open', fn ($q) => $q->whereNull('handled_at'), fn ($q) => $q->whereNotNull('handled_at'))
                ->latest('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function handle(Request $request, Inquiry $inquiry)
    {
        Gate::authorize('staff.answerInquiries');
        $inquiry->forceFill(['handled_at' => now(), 'handled_by' => $request->user()->id])->save();
        Audit::record('inquiry.handled', $inquiry, null, null, $request->user()->id);

        return back()->with('status', 'Marked as handled.');
    }
}
