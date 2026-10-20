<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function storeProduct(Request $request, ProductService $products): JsonResponse
    {
        $product = $products->save($this->productData($request));

        return response()->json(['message' => 'Barang berhasil ditambahkan. Catat pembelian untuk menambah stok.', 'data' => $product->load('units')], 201);
    }

    public function updateProduct(Request $request, Product $product, ProductService $products): JsonResponse
    {
        $product = $products->save($this->productData($request, $product), $product);

        return response()->json(['message' => 'Data barang berhasil diperbarui.', 'data' => $product->load('units')]);
    }

    public function storeSupplier(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Supplier berhasil ditambahkan.', 'data' => Supplier::create($this->contactData($request))], 201);
    }

    public function updateSupplier(Request $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($this->contactData($request));

        return response()->json(['message' => 'Data supplier berhasil diperbarui.', 'data' => $supplier->fresh()]);
    }

    public function storeCustomer(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Pelanggan berhasil ditambahkan.', 'data' => Customer::create($this->contactData($request))], 201);
    }

    public function updateCustomer(Request $request, Customer $customer): JsonResponse
    {
        $customer->update($this->contactData($request));

        return response()->json(['message' => 'Data pelanggan berhasil diperbarui.', 'data' => $customer->fresh()]);
    }

    public function storeExpense(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999999.99'],
            'payment_method' => ['required', Rule::in(['cash', 'transfer', 'qris'])],
        ], $this->messages(), [
            'date' => 'Tanggal', 'category' => 'Kategori', 'description' => 'Keterangan', 'amount' => 'Nominal', 'payment_method' => 'Metode pembayaran',
        ]);

        return response()->json(['message' => 'Pengeluaran berhasil dicatat.', 'data' => Expense::create($data)], 201);
    }

    private function productData(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'sku' => ['required', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($product?->id)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'base_unit' => ['required', 'string', 'max:40'],
            'minimum_stock' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'active' => ['required', 'boolean'],
            'units' => ['required', 'array', 'min:1', 'max:20'],
            'units.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('product_units', 'id')->where('product_id', $product?->id ?? 0)],
            'units.*.name' => ['required', 'string', 'max:40', 'distinct:ignore_case'],
            'units.*.multiplier' => ['required', 'integer', 'min:1', 'max:1000000'],
            'units.*.selling_price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999999.99'],
        ], $this->messages(), [
            'sku' => 'Kode barang', 'name' => 'Nama barang', 'category' => 'Kategori', 'base_unit' => 'Satuan dasar',
            'minimum_stock' => 'Batas stok minimum', 'active' => 'Status barang', 'units' => 'Daftar satuan',
            'units.*.id' => 'Satuan', 'units.*.name' => 'Nama satuan', 'units.*.multiplier' => 'Isi satuan', 'units.*.selling_price' => 'Harga jual',
        ]);
    }

    private function contactData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:1000'],
        ], $this->messages(), ['name' => 'Nama', 'phone' => 'Nomor telepon', 'address' => 'Alamat']);
    }

    private function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'numeric' => ':attribute harus berupa angka.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'boolean' => ':attribute tidak valid.',
            'array' => 'Format :attribute tidak valid.',
            'min.numeric' => ':attribute minimal :min.',
            'max.numeric' => ':attribute maksimal :max.',
            'min.array' => ':attribute harus berisi setidaknya :min data.',
            'max.array' => ':attribute maksimal :max data.',
            'max.string' => ':attribute maksimal :max karakter.',
            'unique' => ':attribute sudah digunakan.',
            'exists' => ':attribute tidak ditemukan atau bukan milik barang ini.',
            'distinct' => ':attribute tidak boleh berulang.',
            'in' => ':attribute tidak valid.',
            'date_format' => ':attribute harus menggunakan format tahun-bulan-hari.',
            'decimal' => ':attribute maksimal dua angka di belakang koma.',
        ];
    }
}
