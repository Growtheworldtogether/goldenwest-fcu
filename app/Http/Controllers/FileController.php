<?php

namespace App\Http\Controllers;

use App\Models\Deposit;
use App\Models\KycDocument;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/** Serves private check images and identity documents to the owner and to staff who may review them. Never public. */
class FileController extends Controller
{
    public function check(Request $request, Deposit $deposit, string $side)
    {
        abort_unless(in_array($side, ['front', 'back'], true), 404);
        $owner = $deposit->user_id === $request->user()->id;
        abort_unless($owner || Gate::allows('staff.viewQueue'), 403);
        if (! $owner) {
            Audit::record('deposit.image_viewed', $deposit, null, ['side' => $side], $request->user()->id);
        }

        return $this->send($deposit->{$side.'_path'});
    }

    public function kyc(Request $request, KycDocument $document)
    {
        $owner = $document->user_id === $request->user()->id;
        abort_unless($owner || Gate::allows('staff.reviewKyc'), 403);
        if (! $owner) {
            Audit::record('kyc.document_viewed', $document, null, ['type' => $document->type], $request->user()->id);
        }

        return $this->send($document->path, $document->mime);
    }

    private function send(?string $path, ?string $mime = null)
    {
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        $pdf = $mime === 'application/pdf';

        // Images show inline under a locked-down policy. PDFs always download, so nothing in them runs in the browser.
        $response = response()->file(Storage::disk('local')->path($path), array_filter([
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",
            'Content-Disposition' => $pdf ? 'attachment; filename="document.pdf"' : 'inline',
        ]));

        // Never cached by the browser or a shared proxy: these are private documents.
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
