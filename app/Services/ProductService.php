<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function save(array $data, ?Product $product = null): Product
    {
        $data = Operation::validate($data, [
            'sku' => ['required', 'string', 'max:80', Rule::unique('products')->ignore($product?->id)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'base_unit' => ['required', 'string', 'max:40'],
            'minimum_stock' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'active' => ['required', 'boolean'],
            'units' => ['required', 'array', 'min:1', 'max:20'],
            'units.*.id' => ['nullable', 'integer', 'distinct'],
            'units.*.name' => ['required', 'string', 'max:40', 'distinct'],
            'units.*.multiplier' => ['required', 'integer', 'min:1', 'max:1000000'],
            'units.*.selling_price' => Operation::moneyRules(),
        ]);
        $base = array_filter($data['units'], fn ($unit) => (int) $unit['multiplier'] === 1 && $unit['name'] === $data['base_unit']);
        if (count($base) !== 1 || count(array_filter($data['units'], fn ($unit) => (int) $unit['multiplier'] === 1)) !== 1) {
            throw ValidationException::withMessages(['units' => 'Harus ada tepat satu satuan dasar dengan isi 1 dan nama sesuai satuan terkecil.']);
        }

        return DB::transaction(function () use ($data, $product) {
            $product = $product ? Product::lockForUpdate()->findOrFail($product->id) : new Product;
            $existing = $product->exists ? $product->units()->get()->keyBy('id') : collect();
            $hasHistory = $product->exists && ($product->stock !== 0 || $product->movements()->exists());
            if ($hasHistory && $data['base_unit'] !== $product->base_unit) {
                throw ValidationException::withMessages(['base_unit' => 'Satuan terkecil tidak dapat diganti setelah ada riwayat stok.']);
            }
            $keep = [];
            foreach ($data['units'] as $index => $unit) {
                if (! empty($unit['id'])) {
                    $old = $existing->get($unit['id']);
                    if (! $old) {
                        throw ValidationException::withMessages(["units.$index.id" => 'Satuan tidak dimiliki barang ini.']);
                    }
                    if ($hasHistory && $old->multiplier !== (int) $unit['multiplier']) {
                        throw ValidationException::withMessages(["units.$index.multiplier" => 'Isi satuan tidak dapat diganti setelah ada riwayat stok. Tambahkan satuan baru.']);
                    }
                    $keep[] = $old->id;
                }
            }
            $removed = $existing->except($keep);
            foreach ($removed as $unit) {
                if ($hasHistory || PurchaseItem::where('unit_id', $unit->id)->exists() || SaleItem::where('unit_id', $unit->id)->exists()) {
                    throw ValidationException::withMessages(['units' => 'Satuan tidak dapat dihapus setelah barang memiliki riwayat stok.']);
                }
            }
            $product->fill(collect($data)->except('units')->all())->save();
            foreach ($removed as $unit) {
                $unit->delete();
            }
            foreach ($data['units'] as $unit) {
                $values = ['name' => $unit['name'], 'multiplier' => (int) $unit['multiplier'], 'selling_price' => Decimal::money((string) $unit['selling_price'])];
                if (! empty($unit['id'])) {
                    $existing->get($unit['id'])->update($values);
                } else {
                    $product->units()->create($values);
                }
            }

            return $product->load('units');
        }, 3);
    }
}
