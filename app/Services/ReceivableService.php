<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Validation\ValidationException;

class ReceivableService
{
    public function pay(Sale $sale, array $data): Payment
    {
        $data = Operation::validate($data, [
            'amount' => Operation::moneyRules(true),
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now()->toDateString()],
            'method' => ['required', 'in:cash,transfer,qris'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'request_id' => ['required', 'uuid'],
        ]);
        $data['sale_id'] = $sale->id;

        return Operation::once(Payment::class, $data['request_id'], $data, function ($hash) use ($sale, $data) {
            // Same lock order as checkout: customer, then the document being changed.
            if ($sale->customer_id) {
                Customer::lockForUpdate()->findOrFail($sale->customer_id);
            }
            $locked = Sale::lockForUpdate()->findOrFail($sale->id);
            $amount = Decimal::money((string) $data['amount']);
            if (bccomp($amount, $locked->balance, 2) > 0) {
                throw ValidationException::withMessages(['amount' => 'Pembayaran melebihi sisa utang sebesar Rp '.number_format((float) $locked->balance, 0, ',', '.').'.']);
            }
            if ($data['date'] < $locked->date->format('Y-m-d')) {
                throw ValidationException::withMessages(['date' => 'Tanggal pembayaran tidak boleh sebelum tanggal penjualan.']);
            }
            $payment = $locked->payments()->create([
                'amount' => $amount, 'date' => $data['date'], 'method' => $data['method'], 'notes' => $data['notes'] ?? null,
                'request_id' => $data['request_id'], 'request_hash' => $hash,
            ]);
            $locked->update([
                'paid_amount' => bcadd($locked->paid_amount, $amount, 2),
                'balance' => bcsub($locked->balance, $amount, 2),
            ]);

            return $payment;
        })->load('sale');
    }
}
