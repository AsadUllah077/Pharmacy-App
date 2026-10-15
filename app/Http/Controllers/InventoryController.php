<?php

namespace App\Http\Controllers;

use App\Models\MedicineBatch;
use App\Models\StockMovement;
use App\Services\Audit;
use App\Services\BranchAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $branch = $request->session()->get('branch_id');
        $filter = (string) $request->query('filter', 'all');
        $today = now(config('pharmacy.timezone'))->toDateString();
        $q = mb_substr((string) $request->query('q', ''), 0, 100);
        $batches = MedicineBatch::where('branch_id', $branch)->with('medicine', 'warehouse')->when($q, fn ($b) => $b->where(fn ($b) => $b->whereHas('medicine', fn ($m) => $m->where('name', 'like', "%$q%"))->orWhere('lot_number', 'like', "%$q%")));
        if ($filter === 'expired') {
            $batches->whereNotNull('expires_at')->where('expires_at', '<=', $today);
        }
        if ($filter === 'near') {
            $batches->where('expires_at', '>', $today)->where('expires_at', '<=', now(config('pharmacy.timezone'))->addDays(config('pharmacy.expiry_warning_days'))->toDateString());
        }
        if ($filter === 'quarantine') {
            $batches->where('quarantined_quantity', '>', 0);
        }

        return Inertia::render('Inventory', ['batches' => $batches->orderBy('expires_at')->orderBy('id')->paginate(20)->withQueryString(), 'filter' => $filter, 'q' => $q, 'today' => $today]);
    }

    public function quarantine(Request $request, MedicineBatch $batch)
    {
        abort_unless($batch->branch_id === $request->session()->get('branch_id'), 404);
        $data = $request->validate(['reason' => 'required|string|min:3|max:500']);
        DB::transaction(function () use ($request, $batch, $data) {
            BranchAccess::lock($batch->branch_id);
            $batch = MedicineBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $quantity = $batch->quantity;
            if ($quantity > 0) {
                $batch->update(['quantity' => 0, 'quarantined_quantity' => $batch->quarantined_quantity + $quantity, 'status' => 'quarantined']);
                StockMovement::create(['medicine_batch_id' => $batch->id, 'user_id' => $request->user()->id, 'type' => 'quarantine', 'quantity_delta' => -$quantity, 'quarantine_delta' => $quantity, 'reference' => 'quarantine:'.$batch->id, 'reason' => $data['reason']]);
                Audit::record($request->user(), $batch->branch_id, 'inventory.quarantined', 'batch:'.$batch->id);
            }
        });

        return back()->with('success', 'Batch quarantined.');
    }

    public function movements(Request $request)
    {
        return Inertia::render('Records', ['title' => 'Stock movements', 'columns' => ['id', 'type', 'quantity_delta', 'quarantine_delta', 'reference', 'created_at'], 'records' => StockMovement::whereHas('batch', fn ($q) => $q->where('branch_id', $request->session()->get('branch_id')))->latest()->paginate(30)]);
    }
}
