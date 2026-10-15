<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PartyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission($this->routeIs('suppliers.*') ? 'suppliers.manage' : 'customers.manage');
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:200', 'phone' => 'nullable|string|max:40', 'email' => 'nullable|email|max:200', 'address' => 'nullable|string|max:1000', 'active' => 'required|boolean'];
    }
}
