<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Http\Requests\PurchaseRequest;
use App\Http\Requests\PurchaseReturnRequest;
use App\Http\Requests\ReceiveRequest;
use App\Models\Medicine;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\BranchAccess;
use App\Services\PurchaseAccount;
use App\Services\Purchasing;
use App\Services\ReturnPurchase;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Purchases', ['orders' => PurchaseOrder::where('branch_id', $request->session()->get('branch_id'))->with('supplier')->latest()->paginate(20)->through(fn ($order) => $order->setAttribute('payment_summary', PurchaseAccount::summary($order->id))), 'suppliers' => Supplier::where('active', true)->orderBy('name')->get(['id', 'name'])]);
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

        return Inertia::render('PurchaseDetail', ['order' => $purchase->load('supplier', 'items.medicine', 'receipts.batches.medicine')->setAttribute('payment_summary', PurchaseAccount::summary($purchase->id)), 'paymentHistory' => DB::table('supplier_payment_allocations as a')->join('supplier_payments as p', 'p.id', '=', 'a.supplier_payment_id')->where('a.purchase_order_id', $purchase->id)->orderByDesc('p.id')->get(['p.id', 'a.amount', 'p.method', 'p.created_at']), 'purchaseReturns' => DB::table('purchase_returns')->where('purchase_order_id', $purchase->id)->orderByDesc('id')->get(), 'supplierCredit' => Money::decimal(PurchaseAccount::availableCredit($purchase->branch_id, $purchase->supplier_id))]);
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

    public function payments(Request $request)
    {
        $branch = (int) $request->session()->get('branch_id');
        $filters = $request->validate(['supplier_id' => 'nullable|integer|exists:suppliers,id']);
        $supplier = $filters['supplier_id'] ?? null;
        $orders = PurchaseOrder::where('branch_id', $branch)->when($supplier, fn ($q, $id) => $q->where('supplier_id', $id))->with('supplier')->latest()->paginate(20)->withQueryString()->through(fn ($order) => $order->setAttribute('payment_summary', PurchaseAccount::summary($order->id)));
        $suppliers = Supplier::whereHas('orders', fn ($q) => $q->where('branch_id', $branch))->orderBy('name')->get(['id', 'name'])->map(function ($supplier) use ($branch) {
            $supplier->setAttribute('due', Money::decimal(PurchaseOrder::where('branch_id', $branch)->where('supplier_id', $supplier->id)->get()->sum(fn ($order) => Money::minor(PurchaseAccount::summary($order->id)['due']))));
            $supplier->setAttribute('credit', Money::decimal(PurchaseAccount::availableCredit($branch, $supplier->id)));

            return $supplier;
        });
        $history = DB::table('supplier_payments')->join('suppliers', 'suppliers.id', '=', 'supplier_payments.supplier_id')->where('branch_id', $branch)->when($supplier, fn ($q, $id) => $q->where('supplier_id', $id))->select('supplier_payments.*', 'suppliers.name as supplier_name')->orderByDesc('supplier_payments.id')->paginate(20, ['*'], 'payments_page')->withQueryString();

        return Inertia::render('SupplierPayments', ['orders' => $orders, 'suppliers' => $suppliers, 'history' => $history, 'supplierId' => $supplier ? (string) $supplier : '']);
    }

    public function returnGoods(PurchaseReturnRequest $request, PurchaseOrder $purchase, ReturnPurchase $service)
    {
        abort_unless($purchase->branch_id === $request->session()->get('branch_id'), 404);
        $service->execute($request->user(), $purchase, $request->validated());

        return back()->with('success', 'Purchase return posted; stock and supplier balance updated.');
    }

    public function search(Request $request)
    {
        $q = mb_substr((string) $request->query('q', ''), 0, 100);

        return Medicine::where('active', true)->where(fn ($b) => $b->where('name', 'like', "%$q%")->orWhere('sku', 'like', "%$q%")->orWhere('barcode', $q))->orderBy('name')->limit(20)->get(['id', 'name', 'sku', 'selling_price']);
    }
}
