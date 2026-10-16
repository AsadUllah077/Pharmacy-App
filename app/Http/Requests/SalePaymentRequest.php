<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('sales.create');
    }

    public function rules(): array
    {
        return ['request_key' => 'required|uuid', 'amount' => 'required|decimal:0,2|gt:0|max:1000000000', 'method' => 'required|in:cash,bank,card,wallet'];
    }
}
