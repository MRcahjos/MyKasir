<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\InventoryService;
use App\Services\PurchaseService;
use App\Services\ReceivableService;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function purchase(Request $request, PurchaseService $purchases): JsonResponse
    {
        $data = $request->validate([
            ...$this->transactionRules(),
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'items.*.unit_price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999999.99'],
        ], $this->messages(), $this->attributes());

        $purchase = $purchases->create($data)->load(['supplier', 'items']);

        return response()->json(['message' => 'Pembelian berhasil dicatat dan stok sudah bertambah.', 'data' => $purchase], 201);
    }

    public function sale(Request $request, SaleService $sales): JsonResponse
    {
        $data = $request->validate([
            ...$this->transactionRules(),
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'received_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'payment_method' => ['required', Rule::in(['cash', 'transfer', 'qris'])],
            'items.*.expected_unit_price' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
        ], $this->messages(), $this->attributes());

        $sale = $sales->create($data)->load(['customer', 'items', 'payments']);

        return response()->json(['message' => 'Penjualan berhasil disimpan.', 'data' => $sale], 201);
    }

    public function payment(Request $request, Sale $sale, ReceivableService $receivables): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999999.99'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$sale->date->toDateString()],
            'method' => ['required', Rule::in(['cash', 'transfer', 'qris'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'request_id' => ['required', 'uuid'],
        ], $this->messages(), $this->attributes());

        $payment = $receivables->pay($sale, $data);

        return response()->json(['message' => 'Pembayaran berhasil dicatat.', 'data' => $payment->load('sale')], 201);
    }

    public function adjustment(Request $request, InventoryService $inventory): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'between:-1000000,1000000', 'not_in:0'],
            'unit_cost' => [Rule::requiredIf((int) $request->input('quantity', 0) > 0), 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'notes' => ['required', 'string', 'min:3', 'max:1000'],
            'date' => ['required', 'date_format:Y-m-d'],
            'request_id' => ['required', 'uuid'],
        ], $this->messages(), $this->attributes());

        $movement = $inventory->adjust($data)->load('product');

        return response()->json(['message' => 'Penyesuaian stok berhasil dicatat.', 'data' => $movement], 201);
    }

    private function transactionRules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'request_id' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:product_units,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    private function attributes(): array
    {
        return [
            'date' => 'Tanggal', 'supplier_id' => 'Supplier', 'customer_id' => 'Pelanggan', 'notes' => 'Catatan',
            'request_id' => 'Kode permintaan', 'items' => 'Daftar barang', 'items.*.product_id' => 'Barang',
            'items.*.unit_id' => 'Satuan', 'items.*.quantity' => 'Jumlah barang', 'items.*.unit_price' => 'Harga beli',
            'received_amount' => 'Uang diterima', 'payment_method' => 'Metode pembayaran', 'amount' => 'Nominal pembayaran',
            'method' => 'Metode pembayaran', 'product_id' => 'Barang', 'quantity' => 'Perubahan stok', 'unit_cost' => 'Modal per satuan dasar',
        ];
    }

    private function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'required_if' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'numeric' => ':attribute harus berupa angka.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'array' => 'Format :attribute tidak valid.',
            'min.numeric' => ':attribute minimal :min.',
            'max.numeric' => ':attribute maksimal :max.',
            'min.array' => ':attribute harus berisi setidaknya :min data.',
            'max.array' => ':attribute maksimal :max data.',
            'min.string' => ':attribute minimal :min karakter.',
            'max.string' => ':attribute maksimal :max karakter.',
            'between' => ':attribute harus antara :min dan :max.',
            'exists' => ':attribute tidak ditemukan. Muat ulang halaman dan pilih kembali.',
            'in' => ':attribute tidak valid.',
            'not_in' => ':attribute tidak boleh nol.',
            'date_format' => ':attribute harus menggunakan format tahun-bulan-hari.',
            'after_or_equal' => 'Tanggal pembayaran tidak boleh sebelum tanggal penjualan.',
            'decimal' => ':attribute maksimal dua angka di belakang koma.',
            'uuid' => 'Kode permintaan tidak valid. Muat ulang halaman dan coba lagi.',
        ];
    }
}
