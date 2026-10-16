<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('sales.create');
    }

    public function rules(): array
    {
        return ['allow_partial' => 'sometimes|boolean', 'warehouse_id' => 'required|integer', 'customer_id' => 'nullable|integer|exists:customers,id', 'request_key' => 'required|uuid', 'items' => 'required|array|min:1|max:100', 'items.*.medicine_id' => 'required|integer|distinct|exists:medicines,id', 'items.*.quantity' => 'required|integer|min:1|max:100000', 'items.*.unit_discount' => 'nullable|decimal:0,2|min:0|max:1000000', 'payments' => 'present|array|max:4', 'payments.*.method' => 'required|in:cash,card,bank,wallet', 'payments.*.amount' => 'required|decimal:0,2|gt:0|max:1000000000'];
    }
}
