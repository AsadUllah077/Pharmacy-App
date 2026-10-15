<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MedicineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission($this->route('medicine') ? 'medicines.update' : 'medicines.create');
    }

    public function rules(): array
    {
        $id = $this->route('medicine')?->id;

        return ['name' => 'required|string|max:200', 'sku' => ['required', 'string', 'max:80', Rule::unique('medicines')->ignore($id)], 'barcode' => ['nullable', 'string', 'max:100', Rule::unique('medicines')->ignore($id)], 'generic_name' => 'nullable|string|max:200', 'manufacturer' => 'nullable|string|max:200', 'category' => 'required|string|max:100', 'strength' => 'nullable|string|max:80', 'dosage_form' => 'nullable|string|max:80', 'unit' => 'required|string|max:40', 'storage' => 'nullable|string|max:1000', 'prescription_required' => 'required|boolean', 'active' => 'required|boolean', 'selling_price' => 'required|decimal:0,2|gt:0|max:1000000', 'tax_basis_points' => 'required|integer|min:0|max:10000', 'reorder_level' => 'required|integer|min:0|max:100000'];
    }
}
