<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Http\Requests\PurchaseRequest;
use App\Http\Requests\ReceiveRequest;
use App\Models\Medicine;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\BranchAccess;
use App\Services\Purchasing;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Purchases', ['orders' => PurchaseOrder::where('branch_id', $request->session()->get('branch_id'))->with('supplier')->latest()->paginate(20), 'suppliers' => Supplier::where('active', true)->orderBy('name')->get(['id', 'name'])]);
    }

    public function store(PurchaseRequest $request, Purchasing $service)
    {
        $order = $service->order($request->user(), $request->session()->get('branch_id'), $request->validated());

        return redirect('/purchases/'.$order->id)->with('success', 'Purchase order created. Stock is received separately.');
    }

    public function show(Request $request, PurchaseOrder $purchase)
    {
        BranchAccess::check($request->user(), $purchase->branch_id, 'purchases.view');
        abort_unless($purchase->branch_id === $request->session()->get('branch_id'), 404);

        return Inertia::render('PurchaseDetail', ['order' => $purchase->load('supplier', 'items.medicine', 'receipts.batches')]);
    }

    public function receive(ReceiveRequest $request, PurchaseOrder $purchase, Purchasing $service)
    {
        abort_unless($purchase->branch_id === $request->session()->get('branch_id'), 404);
        $service->receive($request->user(), $purchase, $request->validated());

        return back()->with('success', 'Goods received and supplier liability posted.');
    }

    public function pay(PaymentRequest $request, Purchasing $service)
    {
        $service->pay($request->user(), $request->session()->get('branch_id'), $request->validated());

        return back()->with('success', 'Supplier payment posted.');
    }

    public function search(Request $request)
    {
        $q = mb_substr((string) $request->query('q', ''), 0, 100);

        return Medicine::where('active', true)->where(fn ($b) => $b->where('name', 'like', "%$q%")->orWhere('sku', 'like', "%$q%")->orWhere('barcode', $q))->orderBy('name')->limit(20)->get(['id', 'name', 'sku', 'selling_price']);
    }
}
