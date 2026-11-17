<?php

namespace App\Services;

use App\Models\Decision;
use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Identity documents: members upload, staff with the KYC permission approve or reject with a reason. */
final class KycService
{
    public function __construct(private SecureUpload $uploads)
    {
    }

    public function submit(User $member, string $type, UploadedFile $file): KycDocument
    {
        if (! isset(KycDocument::TYPES[$type])) {
            throw ValidationException::withMessages(['type' => 'Choose a document type.']);
        }
        if (KycDocument::where('user_id', $member->id)->where('type', $type)->where('status', 'approved')->exists()) {
            throw ValidationException::withMessages(['file' => 'This document is already approved.']);
        }

        $stored = $this->uploads->store($file, 'kyc', 'file', allowPdf: true);

        return DB::transaction(function () use ($member, $type, $stored) {
            KycDocument::where('user_id', $member->id)->where('type', $type)->whereIn('status', ['pending', 'rejected'])->update(['status' => 'replaced']);
            $doc = KycDocument::create(['user_id' => $member->id, 'type' => $type, 'path' => $stored['path'], 'original_name' => $stored['name'], 'mime' => $stored['mime'], 'size' => $stored['size']]);
            $this->refreshStatus($member);
            Audit::record('kyc.submitted', $doc, null, ['type' => $type], $member->id);

            return $doc;
        });
    }

    public function decide(KycDocument $doc, User $staff, bool $approve, ?string $reasonCode = null, ?string $reasonText = null): KycDocument
    {
        if ($doc->status !== 'pending') {
            throw ValidationException::withMessages(['action' => 'This document has already been reviewed.']);
        }
        if (! $approve && (! $reasonCode || ! trim((string) $reasonText))) {
            throw ValidationException::withMessages(['reason_text' => 'A reason is required when rejecting a document.']);
        }

        return DB::transaction(function () use ($doc, $staff, $approve, $reasonCode, $reasonText) {
            Decision::create([
                'decidable_type' => $doc->getMorphClass(), 'decidable_id' => $doc->id,
                'decision' => $approve ? 'approved' : 'rejected', 'reason_code' => $approve ? null : $reasonCode,
                'reason_text' => $approve ? null : $reasonText, 'decided_by' => $staff->id,
            ]);
            $doc->update(['status' => $approve ? 'approved' : 'rejected', 'reviewed_by' => $staff->id, 'reviewed_at' => now()]);
            $this->refreshStatus($doc->user);
            Audit::record($approve ? 'kyc.approved' : 'kyc.rejected', $doc, ['status' => 'pending'], ['status' => $doc->status, 'reason' => $reasonCode], $staff->id);

            return $doc->refresh();
        });
    }

    /** Verified when the current document of each type is approved. Rejected when a current one was rejected. */
    public function refreshStatus(User $member): void
    {
        $current = KycDocument::where('user_id', $member->id)->where('status', '!=', 'replaced')->get()->groupBy('type');
        $approved = collect(array_keys(KycDocument::TYPES))->every(fn ($t) => $current->get($t, collect())->contains('status', 'approved'));
        $rejected = $current->flatten()->contains('status', 'rejected');
        // A member verified before documents were collected stays verified until a document is turned down.
        if ($member->kyc_status === 'verified' && ! $approved && ! $rejected) {
            return;
        }
        $status = $approved ? 'verified' : ($rejected ? 'rejected' : 'pending');

        if ($member->kyc_status !== $status) {
            $old = $member->kyc_status;
            $member->forceFill(['kyc_status' => $status])->save();
            Audit::record('kyc.status_changed', $member, ['status' => $old], ['status' => $status], null);
        }
    }
}
