<?php

namespace App\Http\Controllers;

use App\Http\Resources\LedgerEntryResource;
use App\Models\LedgerEntry;
use App\Models\Pool;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LedgerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $entries = LedgerEntry::where('pool_id', Pool::sole()->id)
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return LedgerEntryResource::collection($entries);
    }

    public function all(): AnonymousResourceCollection
    {
        $entries = LedgerEntry::where('pool_id', Pool::sole()->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        return LedgerEntryResource::collection($entries);
    }

    public function markPaid(LedgerEntry $ledgerEntry, Request $request): LedgerEntryResource
    {
        $ledgerEntry->update($ledgerEntry->is_paid
            ? ['is_paid' => false, 'paid_at' => null, 'marked_paid_by_user_id' => null]
            : ['is_paid' => true, 'paid_at' => now(), 'marked_paid_by_user_id' => $request->user()->id],
        );

        return new LedgerEntryResource($ledgerEntry);
    }
}
