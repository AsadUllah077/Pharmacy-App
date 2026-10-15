<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\ReturnRequest;
use App\Models\Customer;
use App\Models\Medicine;
use App\Models\Sale;
use App\Services\BranchAccess;
use App\Services\Checkout;
use App\Services\ReturnSale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SaleController extends Controller
{
    public function pos(Request $request)
    {
        return Inertia::render('Pos');
    }

    public function search(Request $request)
    {
        $warehouse = (int) $request->query('warehouse_id');
        $branch = (int) $request->session()->get('branch_id');
        BranchAccess::warehouse($branch, $warehouse);
        $q = mb_substr((string) $request->query('q', ''), 0, 100);

        return Medicine::where('active', true)->withSum(['batches as available_stock' => fn ($b) => $b->sellable()->where('branch_id', $branch)->where('warehouse_id', $warehouse)], 'quantity')->where(fn ($b) => $b->where('name', 'like', "%$q%")->orWhere('generic_name', 'like', "%$q%")->orWhere('sku', 'like', "%$q%")->orWhere('barcode', $q))->orderBy('name')->limit(30)->get(['id', 'sku', 'barcode', 'name', 'generic_name', 'selling_price', 'tax_basis_points', 'prescription_required']);
    }

    public function customers(Request $request)
    {
        $q = mb_substr((string) $request->query('q', ''), 0, 100);

        return Customer::where('active', true)->where('name', 'like', "%$q%")->orderBy('name')->limit(20)->get(['id', 'name']);
    }

    public function checkout(CheckoutRequest $request, Checkout $service)
    {
        $sale = $service->execute($request->user(), $request->session()->get('branch_id'), $request->validated());

        return redirect('/sales/'.$sale->id)->with('success', 'Sale completed.');
    }

    public function index(Request $request)
    {
        $q = mb_substr((string) $request->query('q', ''), 0, 100);

        return Inertia::render('Sales', ['sales' => Sale::where('branch_id', $request->session()->get('branch_id'))->when($q, fn ($b) => $b->where('invoice_number', 'like', "%$q%"))->with('customer', 'cashier:id,name')->latest()->paginate(20)->withQueryString(), 'q' => $q]);
    }

    public function show(Request $request, Sale $sale)
    {
        Gate::authorize('view', $sale);
        abort_unless($sale->branch_id === $request->session()->get('branch_id'), 404);

        return Inertia::render('SaleDetail', ['sale' => $sale->load('items.allocations.batch', 'payments', 'customer', 'cashier:id,name', 'branch', 'returns')]);
    }

    public function refund(ReturnRequest $request, Sale $sale, ReturnSale $service)
    {
        abort_unless($sale->branch_id === $request->session()->get('branch_id'), 404);
        $service->execute($request->user(),$sale,$request->validated());

        return back()->with('success','Cash refund posted. Returned units are quarantined.');
    }
}
