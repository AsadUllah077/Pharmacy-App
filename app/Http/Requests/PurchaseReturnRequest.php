<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('purchases.return');
    }

    public function rules(): array
    {
        return ['request_key' => 'required|uuid', 'reason' => 'required|string|min:3|max:1000', 'settlement' => 'required|in:credit,refund', 'method' => 'required_if:settlement,refund|nullable|in:cash,bank,card,wallet', 'items' => 'required|array|min:1|max:100', 'items.*.batch_id' => 'required|integer|distinct', 'items.*.stock_bucket' => 'required|in:available,quarantine', 'items.*.quantity' => 'required|integer|min:1|max:100000'];
    }
}
