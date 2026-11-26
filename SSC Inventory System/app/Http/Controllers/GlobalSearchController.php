<?php

namespace App\Http\Controllers;

use App\Models\Acquisition;
use App\Models\BorrowingRequest;
use App\Models\InventoryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GlobalSearchController extends Controller
{
    public function quick(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q'));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $user = $request->user();
        $results = [];

        $items = InventoryItem::query()
            ->with('category')
            ->where(function ($query) use ($q): void {
                $query->where('property_code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('serial_number', 'like', "%{$q}%")
                    ->orWhere('brand_model', 'like', "%{$q}%");
            })
            ->limit(5)
            ->get();

        foreach ($items as $item) {
            $results[] = [
                'title' => "{$item->property_code} — {$item->name}",
                'subtitle' => ($item->category?->name ?? 'Uncategorized') . " · Avail: {$item->available_quantity}/{$item->quantity}",
                'badge' => 'Item',
                'url' => route('inventory.show', $item),
            ];
        }

        $borrowings = BorrowingRequest::query()
            ->when(! $user->hasPermission('borrowings.view_all'), fn ($query) => $query->where('requester_user_id', $user->id))
            ->where(function ($query) use ($q): void {
                $query->where('transaction_code', 'like', "%{$q}%")
                    ->orWhere('borrower_name', 'like', "%{$q}%")
                    ->orWhere('school_id', 'like', "%{$q}%");
            })
            ->limit(4)
            ->get();

        foreach ($borrowings as $br) {
            $results[] = [
                'title' => "{$br->transaction_code} — {$br->borrower_name}",
                'subtitle' => "{$br->department_organization} · {$br->status_label}",
                'badge' => 'Borrowing',
                'url' => route('borrowings.show', $br),
            ];
        }

        if ($user->hasPermission('procurement.view')) {
            $acquisitions = Acquisition::query()
                ->where(function ($query) use ($q): void {
                    $query->where('purchase_reference', 'like', "%{$q}%")
                        ->orWhere('item_name', 'like', "%{$q}%");
                })
                ->limit(3)
                ->get();

            foreach ($acquisitions as $acq) {
                $results[] = [
                    'title' => "{$acq->purchase_reference} — {$acq->item_name}",
                    'subtitle' => "Qty: {$acq->quantity_acquired} · ₱" . number_format((float) $acq->total_cost, 2),
                    'badge' => 'Procurement',
                    'url' => route('procurement.index', ['search' => $acq->purchase_reference]),
                ];
            }
        }

        return response()->json(['results' => $results]);
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->input('q'));
        $user = $request->user();

        $items = collect();
        $borrowings = collect();
        $acquisitions = collect();

        if ($q !== '') {
            $items = InventoryItem::with(['category', 'condition', 'location'])
                ->where(function ($query) use ($q): void {
                    $query->where('property_code', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%")
                        ->orWhere('brand_model', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%");
                })
                ->limit(25)
                ->get();

            $borrowings = BorrowingRequest::with('items.inventoryItem')
                ->when(! $user->hasPermission('borrowings.view_all'), fn ($query) => $query->where('requester_user_id', $user->id))
                ->where(function ($query) use ($q): void {
                    $query->where('transaction_code', 'like', "%{$q}%")
                        ->orWhere('borrower_name', 'like', "%{$q}%")
                        ->orWhere('school_id', 'like', "%{$q}%")
                        ->orWhere('purpose', 'like', "%{$q}%");
                })
                ->limit(25)
                ->get();

            if ($user->hasPermission('procurement.view')) {
                $acquisitions = Acquisition::with('supplier')
                    ->where(function ($query) use ($q): void {
                        $query->where('purchase_reference', 'like', "%{$q}%")
                            ->orWhere('item_name', 'like', "%{$q}%")
                            ->orWhere('supplier_name', 'like', "%{$q}%");
                    })
                    ->limit(20)
                    ->get();
            }
        }

        return view('search.index', compact('q', 'items', 'borrowings', 'acquisitions'));
    }
}
