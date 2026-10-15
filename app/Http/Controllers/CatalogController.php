<?php

namespace App\Http\Controllers;

use App\Http\Requests\MedicineRequest;
use App\Http\Requests\PartyRequest;
use App\Models\Customer;
use App\Models\Medicine;
use App\Models\Supplier;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CatalogController extends Controller
{
    public function medicines(Request $request)
    {
        $q = mb_substr((string) $request->query('q', ''), 0, 100);
        $query = Medicine::query()->when($q, fn ($b) => $b->where(fn ($b) => $b->where('name', 'like', "%$q%")->orWhere('generic_name', 'like', "%$q%")->orWhere('sku', 'like', "%$q%")->orWhere('barcode', 'like', "%$q%")->orWhere('manufacturer', 'like', "%$q%")));

        return Inertia::render('Catalog', ['kind' => 'medicines', 'records' => $query->orderBy('name')->paginate(20)->withQueryString(), 'q' => $q]);
    }

    public function storeMedicine(MedicineRequest $request)
    {
        DB::transaction(function () use ($request) {
            $m = Medicine::create($request->validated());
            Audit::record($request->user(), $request->session()->get('branch_id'), 'medicine.created', 'medicine:'.$m->id);
        });

        return back()->with('success', 'Medicine created.');
    }

    public function updateMedicine(MedicineRequest $request, Medicine $medicine)
    {
        DB::transaction(function () use ($request, $medicine) {
            $medicine->update($request->validated());
            Audit::record($request->user(), $request->session()->get('branch_id'), 'medicine.updated', 'medicine:'.$medicine->id);
        });

        return back()->with('success', 'Medicine updated.');
    }

    public function parties(Request $request)
    {
        $supplier = $request->routeIs('suppliers.*');
        $model = $supplier ? Supplier::class : Customer::class;
        $q = mb_substr((string) $request->query('q', ''), 0, 100);

        return Inertia::render('Catalog', ['kind' => $supplier ? 'suppliers' : 'customers', 'records' => $model::when($q, fn ($b) => $b->where('name', 'like', "%$q%"))->orderBy('name')->paginate(20)->withQueryString(), 'q' => $q]);
    }

    public function storeParty(PartyRequest $request)
    {
        $model = $request->routeIs('suppliers.*') ? Supplier::class : Customer::class;
        DB::transaction(function () use ($request, $model) {
            $p = $model::create($request->validated());
            Audit::record($request->user(), $request->session()->get('branch_id'), 'party.created', $p->getTable().':'.$p->id);
        });

        return back()->with('success', 'Record created.');
    }

    public function updateParty(PartyRequest $request, int $party)
    {
        $model = $request->routeIs('suppliers.*') ? Supplier::class : Customer::class;
        DB::transaction(function () use ($request, $model, $party) {
            $p = $model::findOrFail($party);
            $p->update($request->validated());
            Audit::record($request->user(), $request->session()->get('branch_id'), 'party.updated', $p->getTable().':'.$p->id);
        });

        return back()->with('success','Record updated.');
    }
}
