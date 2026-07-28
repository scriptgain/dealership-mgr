<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Inspection;
use App\Models\InspectionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The customer's side of a digital vehicle inspection. Reached from an
 * unguessable token in a text message or email, with no account and no password,
 * because a customer standing in a car park will not create a login.
 *
 * The whole point is per-line decisions: yes to the brakes, no to the wipers.
 */
class InspectionController extends Controller
{
    public function publicShow(string $token)
    {
        $inspection = $this->findOrFail($token);

        $inspection->load(['vehicle', 'items.photos', 'technician']);

        return view('shop.inspection', [
            'inspection' => $inspection,
            // Findings that need an answer, separated from the reassuring ones.
            'actionable' => $inspection->items->filter(fn (InspectionItem $i) => $i->isActionable()),
            'passed' => $inspection->items->filter(fn (InspectionItem $i) => ! $i->isActionable()),
            'shopName' => config('dealership.store_name') ?: config('brand.name'),
        ]);
    }

    /** Approve or decline one finding. */
    public function decide(Request $request, string $token, InspectionItem $item)
    {
        $inspection = $this->findOrFail($token);

        abort_unless($item->inspection_id === $inspection->id, 404);

        if (! $inspection->isOpenForReview()) {
            return back()->with('error', 'This inspection is closed. Please call the shop.');
        }

        $decision = $request->string('decision')->toString();
        abort_unless(in_array($decision, ['approved', 'declined'], true), 422);

        // A line already turned into billable work cannot be taken back here; the
        // customer has to talk to the shop, which is the honest behaviour.
        if ($item->work_order_item_id) {
            return back()->with('error', 'That work has already been started. Please call the shop to change it.');
        }

        $item->update(['decision' => $decision, 'decided_at' => now()]);

        if ($inspection->status === 'sent') {
            $inspection->update(['status' => 'reviewed', 'reviewed_at' => now()]);
        }

        $inspection->recordActivity(
            'decision',
            ($decision === 'approved' ? 'Approved' : 'Declined').': '.$item->name,
            [],
            null,
            $inspection->customer?->name ?? 'Customer'
        );

        return back()->with('status', $decision === 'approved'
            ? 'Approved. The shop has been told.'
            : 'Declined. The shop has been told.');
    }

    /** Approve everything still awaiting a decision, for the customer in a hurry. */
    public function approveAll(Request $request, string $token)
    {
        $inspection = $this->findOrFail($token);

        if (! $inspection->isOpenForReview()) {
            return back()->with('error', 'This inspection is closed. Please call the shop.');
        }

        $pending = $inspection->items
            ->filter(fn (InspectionItem $i) => $i->isActionable() && $i->decision === 'pending' && ! $i->work_order_item_id);

        foreach ($pending as $item) {
            $item->update(['decision' => 'approved', 'decided_at' => now()]);
        }

        if ($pending->isNotEmpty()) {
            $inspection->update(['status' => 'reviewed', 'reviewed_at' => now()]);
            $inspection->recordActivity(
                'decision',
                'Approved all '.$pending->count().' outstanding '.\Illuminate\Support\Str::plural('item', $pending->count()),
                [],
                null,
                $inspection->customer?->name ?? 'Customer'
            );
        }

        return back()->with('status', 'Everything approved. The shop has been told.');
    }

    /**
     * Stream a photo for the customer. Authorised by the review token and by the
     * photo genuinely belonging to a finding on that inspection, so a token
     * cannot be used to fish for other files.
     */
    public function photo(string $token, Attachment $attachment)
    {
        $inspection = $this->findOrFail($token);

        abort_unless($attachment->attachable_type === InspectionItem::class, 404);
        abort_unless($inspection->items->pluck('id')->contains((int) $attachment->attachable_id), 404);

        $disk = Storage::disk($attachment->disk ?: 'local');
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response($attachment->path, $attachment->filename, [
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    /** A draft is not visible to anyone outside the shop. */
    private function findOrFail(string $token): Inspection
    {
        $inspection = Inspection::where('review_token', $token)->firstOrFail();

        abort_unless($inspection->isSent(), 404);

        return $inspection;
    }
}
