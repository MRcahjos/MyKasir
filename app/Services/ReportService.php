<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use Carbon\CarbonPeriod;

class ReportService
{
    public function summary(string $from, string $to): array
    {
        Operation::validate(compact('from', 'to'), [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.date('Y-m-d', strtotime($from.' + 5 years'))],
        ]);
        $sales = Sale::whereBetween('date', [$from, $to]);
        // Sum decimal strings in PHP rather than SQLite's floating-point SUM.
        $salesTotal = Decimal::sum((clone $sales)->pluck('total'));
        $costTotal = Decimal::sum((clone $sales)->pluck('cost_total'));
        $expenses = Decimal::sum(Expense::whereBetween('date', [$from, $to])->pluck('amount'));
        $loss = Decimal::sum(StockMovement::where('type', 'adjustment')->where('quantity', '<', 0)->whereBetween('date', [$from, $to])->pluck('cost_total'));
        $gross = bcsub($salesTotal, $costTotal, 2);
        $lowStock = Product::where('active', true)->whereColumn('stock', '<=', 'minimum_stock');
        $stockValue = '0.00';
        foreach (Product::select('stock', 'average_cost')->cursor() as $product) {
            $stockValue = bcadd($stockValue, Decimal::round(bcmul((string) $product->stock, $product->average_cost, 8)), 2);
        }
        $dailyTotals = [];
        foreach ((clone $sales)->select('date', 'total')->cursor() as $sale) {
            $day = $sale->date->format('Y-m-d');
            $dailyTotals[$day] = bcadd($dailyTotals[$day] ?? '0', $sale->total, 2);
        }
        $daily = [];
        foreach (CarbonPeriod::create($from, $to) as $date) {
            $day = $date->format('Y-m-d');
            $daily[] = ['date' => $day, 'total' => $dailyTotals[$day] ?? '0.00'];
        }

        return [
            'sales_total' => $salesTotal, 'cost_total' => $costTotal, 'gross_profit' => $gross,
            'expenses_total' => $expenses, 'stock_loss_total' => $loss,
            'net_profit' => bcsub(bcsub($gross, $expenses, 2), $loss, 2),
            'cash_received' => Decimal::sum(Payment::whereBetween('date', [$from, $to])->pluck('amount')),
            'purchases_total' => Decimal::sum(Purchase::whereBetween('date', [$from, $to])->pluck('total')),
            'outstanding_total' => Decimal::sum(Sale::where('balance', '>', 0)->pluck('balance')),
            'stock_value' => $stockValue, 'low_stock_count' => (clone $lowStock)->count(),
            'product_count' => Product::count(), 'sale_count' => (clone $sales)->count(),
            'daily' => $daily,
            'recent_sales' => (clone $sales)->with('customer')->orderByDesc('date')->orderByDesc('id')->limit(6)->get()->map(fn ($sale) => [
                'id' => $sale->id, 'number' => $sale->number, 'date' => $sale->date->format('Y-m-d'),
                'customer_name' => $sale->customer?->name ?? 'Pelanggan umum', 'total' => $sale->total,
                'balance' => $sale->balance, 'status' => bccomp($sale->balance, '0', 2) === 0 ? 'paid' : (bccomp($sale->paid_amount, '0', 2) > 0 ? 'partial' : 'unpaid'),
            ])->all(),
            'low_stock' => (clone $lowStock)->orderBy('stock')->limit(8)->get()->toArray(),
        ];
    }
}
