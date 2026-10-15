<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('sales.refund');
    }

    public function rules(): array
    {
        return ['request_key' => 'required|uuid', 'reason' => 'required|string|min:3|max:1000', 'items' => 'required|array|min:1|max:200', 'items.*.allocation_id' => 'required|integer|distinct', 'items.*.quantity' => 'required|integer|min:1|max:100000'];
    }
}
