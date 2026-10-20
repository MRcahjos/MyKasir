<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\ReportService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class PosDataController extends Controller
{
    public function summary(Request $request, ReportService $reports): JsonResponse
    {
        $dates = $this->dates($request);
        $from = $dates['from'] ?? now()->startOfMonth()->toDateString();
        $to = $dates['to'] ?? now()->toDateString();

        if ($from > $to || Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 1826) {
            throw ValidationException::withMessages(['to' => 'Rentang laporan harus berurutan dan paling lama 5 tahun.']);
        }

        return response()->json(['data' => $reports->summary($from, $to)]);
    }

    public function lookups(): JsonResponse
    {
        return response()->json(['data' => [
            'products' => Product::with('units')->where('active', true)->orderBy('name')->get(),
            'customers' => Customer::withSum('sales as outstanding', 'balance')->orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]]);
    }

    public function products(Request $request): JsonResponse
    {
        return $this->table($request, Product::with('units')->withExists('movements'), ['sku', 'name', 'category', 'base_unit'],
            ['sku', 'name', 'category', 'base_unit', 'stock', 'minimum_stock', 'average_cost', 'active'],
            fn ($row) => [...$row->toArray(), 'conversion_locked' => $row->stock != 0 || $row->movements_exists]);
    }

    public function suppliers(Request $request): JsonResponse
    {
        return $this->table($request, Supplier::query(), ['name', 'phone', 'address'], ['name', 'phone', 'address'], fn ($row) => $row->toArray());
    }

    public function customers(Request $request): JsonResponse
    {
        return $this->table($request, Customer::withSum('sales as outstanding', 'balance'), ['name', 'phone', 'address'],
            ['name', 'phone', 'address', 'outstanding'], fn ($row) => $row->toArray());
    }

    public function purchases(Request $request): JsonResponse
    {
        return $this->table($request, Purchase::with('supplier'), ['number', 'notes'], ['number', 'date', 'total'],
            fn ($row) => [...$this->transactionRow($row), 'supplier_name' => $row->supplier?->name ?? '—'],
            ['supplier' => ['name']], true);
    }

    public function sales(Request $request): JsonResponse
    {
        return $this->saleTable($request, Sale::query());
    }

    public function receivables(Request $request): JsonResponse
    {
        return $this->saleTable($request, Sale::where('balance', '>', 0));
    }

    public function movements(Request $request): JsonResponse
    {
        return $this->table($request, StockMovement::with('product'), ['type', 'reference', 'notes'],
            ['date', 'type', 'quantity', 'balance', 'unit_cost', 'reference'],
            fn ($row) => [...$this->transactionRow($row), 'product_name' => $row->product?->name ?? '—', 'base_unit' => $row->product?->base_unit ?? ''],
            ['product' => ['name', 'sku']], true);
    }

    public function expenses(Request $request): JsonResponse
    {
        return $this->table($request, Expense::query(), ['category', 'description', 'payment_method'],
            ['date', 'category', 'description', 'amount', 'payment_method'], fn ($row) => $this->transactionRow($row), [], true);
    }

    public function purchase(Purchase $purchase): JsonResponse
    {
        $purchase->load(['supplier', 'items']);

        return response()->json(['data' => [...$this->transactionRow($purchase), 'supplier_name' => $purchase->supplier?->name ?? '—']]);
    }

    public function sale(Sale $sale): JsonResponse
    {
        $sale->load(['customer', 'items', 'payments' => fn ($query) => $query->orderBy('date')->orderBy('id')]);
        $row = $this->saleRow($sale);
        $row['payments'] = $sale->payments->map(fn ($payment) => $this->transactionRow($payment))->all();

        return response()->json(['data' => $row]);
    }

    private function saleTable(Request $request, Builder $query): JsonResponse
    {
        return $this->table($request, $query->with('customer'), ['number', 'notes', 'payment_method'],
            ['number', 'date', 'total', 'paid_amount', 'balance', 'payment_method'],
            fn ($row) => $this->saleRow($row), ['customer' => ['name', 'phone']], true);
    }

    private function saleRow(Sale $sale): array
    {
        return [...$this->transactionRow($sale),
            'customer_name' => $sale->customer?->name ?? 'Pelanggan umum',
            'status' => (float) $sale->balance <= 0 ? 'paid' : ((float) $sale->paid_amount > 0 ? 'partial' : 'unpaid'),
        ];
    }

    private function transactionRow($model): array
    {
        $data = $model->toArray();
        unset($data['request_hash']);
        if ($model->date) {
            $data['date'] = Carbon::parse($model->date)->toDateString();
        }

        return $data;
    }

    private function dates(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ], [
            '*.date_format' => 'Tanggal harus menggunakan format tahun-bulan-hari.',
            'to.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal awal.',
        ]);
    }

    private function table(Request $request, Builder $query, array $searchable, array $sortable, Closure $map, array $relations = [], bool $dated = false): JsonResponse
    {
        $request->validate([
            'draw' => ['nullable', 'integer', 'min:0'],
            'start' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'length' => ['nullable', 'integer', 'min:-1', 'max:100'],
            'search.value' => ['nullable', 'string', 'max:200'],
            'order' => ['nullable', 'array', 'max:5'],
            'order.*.column' => ['required', 'integer', 'min:0', 'max:100'],
            'order.*.dir' => ['required', 'in:asc,desc'],
            'columns' => ['nullable', 'array', 'max:100'],
            'columns.*.data' => ['nullable', 'string', 'max:100'],
        ], [
            '*.integer' => 'Parameter tabel harus berupa bilangan bulat.',
            '*.max' => 'Parameter tabel melebihi batas yang diperbolehkan.',
            '*.min' => 'Parameter tabel di bawah batas yang diperbolehkan.',
            '*.in' => 'Arah pengurutan tabel tidak valid.',
            '*.array' => 'Format parameter tabel tidak valid.',
            '*.string' => 'Format parameter tabel harus berupa teks.',
        ]);

        $total = (clone $query)->count();
        if ($dated) {
            $dates = $this->dates($request);
            if (!empty($dates['from'])) {
                $query->whereDate('date', '>=', $dates['from']);
            }
            if (!empty($dates['to'])) {
                $query->whereDate('date', '<=', $dates['to']);
            }
        }

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($searchable, $relations, $search) {
                foreach ($searchable as $column) {
                    $builder->orWhere($column, 'like', '%'.$search.'%');
                }
                foreach ($relations as $relation => $columns) {
                    $builder->orWhereHas($relation, function (Builder $relationQuery) use ($columns, $search) {
                        $relationQuery->where(function (Builder $fields) use ($columns, $search) {
                            foreach ($columns as $column) {
                                $fields->orWhere($column, 'like', '%'.$search.'%');
                            }
                        });
                    });
                }
            });
        }

        $filtered = (clone $query)->count();
        foreach ($request->input('order', []) as $order) {
            $column = $request->input('columns.'.$order['column'].'.data');
            if (is_string($column) && in_array($column, $sortable, true)) {
                $query->orderBy($column, $order['dir']);
            }
        }
        $query->orderByDesc($query->getModel()->qualifyColumn('id'));
        $requestedLength = (int) $request->input('length', 15);
        $length = $requestedLength < 1 ? 100 : min(100, $requestedLength);
        $rows = $query->skip((int) $request->input('start', 0))->take($length)->get()->map($map);

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows,
        ]);
    }
}
