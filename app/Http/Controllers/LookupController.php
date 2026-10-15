<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function suppliers(Request $request)
    {
        $q = mb_substr((string) $request->query('q', ''), 0, 100);

        return response()->json(['data' => Supplier::where('active', true)->where('name', 'like', "%$q%")->orderBy('name')->limit(20)->get(['id', 'name'])]);
    }
}
