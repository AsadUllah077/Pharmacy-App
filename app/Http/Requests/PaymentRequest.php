<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('accounting.manage');
    }

    public function rules(): array
    {
        return ['request_key' => 'required|uuid', 'amount' => 'required|decimal:0,2|gt:0|max:1000000000', 'method' => 'required|in:cash,bank,card,wallet', 'supplier_id' => ($this->routeIs('supplier-payments.store') ? 'required' : 'nullable').'|integer|exists:suppliers,id', 'category' => ($this->routeIs('expenses.store') ? 'required' : 'nullable').'|string|max:100', 'description' => ($this->routeIs('expenses.store') ? 'required' : 'nullable').'|string|max:200'];
    }
}
