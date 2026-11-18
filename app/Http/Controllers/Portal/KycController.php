<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\KycDocument;
use App\Models\ReasonCode;
use App\Services\KycService;
use Illuminate\Http\Request;

class KycController extends Controller
{
    public function show(Request $request)
    {
        $docs = KycDocument::with('decisions')->where('user_id', $request->user()->id)->where('status', '!=', 'replaced')->latest('id')->get()->keyBy('type');

        return view('portal.verification', ['docs' => $docs, 'reasons' => ReasonCode::pluck('label', 'code'), 'status' => $request->user()->kyc_status, 'types' => KycDocument::uploadableFor($request->user())]);
    }

    public function store(Request $request, KycService $kyc)
    {
        $data = $request->validate(['type' => ['required', 'string'], 'file' => ['required', 'file']], ['file.required' => 'Choose a photo, scan or PDF to upload.']);
        $doc = $kyc->submit($request->user(), $data['type'], $request->file('file'));
        if (($admin = \App\Services\StaffDirectory::actingSuperAdmin()) && $doc->fresh()->status === 'pending') {
            $kyc->decide($doc->fresh(), $admin, true);

            return redirect()->route('kyc.show')->with('status', 'Document approved.');
        }

        return redirect()->route('kyc.show')->with('status', 'Thank you. We received your document and will review it within one business day.');
    }
}
