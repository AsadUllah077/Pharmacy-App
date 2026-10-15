<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('inventory.receive');
    }

    public function rules(): array
    {
        return ['warehouse_id' => 'required|integer', 'request_key' => 'required|uuid', 'items' => 'required|array|min:1|max:100', 'items.*.item_id' => 'required|integer|distinct', 'items.*.quantity' => 'required|integer|min:1|max:100000', 'items.*.lot_number' => 'required|string|max:100', 'items.*.expires_at' => 'nullable|date_format:Y-m-d|after:today'];
    }
}
