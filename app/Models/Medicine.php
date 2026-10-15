<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    protected $fillable = ['sku', 'barcode', 'name', 'generic_name', 'manufacturer', 'category', 'strength', 'dosage_form', 'unit', 'storage', 'prescription_required', 'active', 'selling_price', 'tax_basis_points', 'reorder_level'];

    protected function casts(): array
    {
        return ['prescription_required' => 'boolean', 'active' => 'boolean', 'selling_price' => 'decimal:2'];
    }

    public function batches()
    {
        return $this->hasMany(MedicineBatch::class);
    }
}
