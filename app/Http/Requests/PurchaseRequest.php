<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('purchases.create');
    }

    public function rules(): array
    {
        return ['supplier_id' => 'required|integer|exists:suppliers,id', 'items' => 'required|array|min:1|max:100', 'items.*.medicine_id' => 'required|integer|distinct|exists:medicines,id', 'items.*.quantity' => 'required|integer|min:1|max:100000', 'items.*.unit_cost' => 'required|decimal:0,2|gt:0|max:1000000'];
    }
}
