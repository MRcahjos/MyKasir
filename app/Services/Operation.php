<?php

namespace App\Services;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class Operation
{
    public static function validate(array $data, array $rules): array
    {
        return Validator::make($data, $rules, [
            'required' => ':attribute wajib diisi.',
            'required_if' => ':attribute wajib diisi.',
            'required_with' => ':attribute wajib diisi.',
            'numeric' => ':attribute harus berupa angka.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'decimal' => ':attribute maksimal memiliki dua angka desimal.',
            'min' => ':attribute kurang dari batas minimum :min.',
            'max' => ':attribute melebihi batas maksimum :max.',
            'gt' => ':attribute harus lebih besar dari :value.',
            'exists' => ':attribute tidak ditemukan.',
            'unique' => ':attribute sudah digunakan.',
            'uuid' => 'Identitas permintaan tidak valid. Muat ulang halaman.',
            'date_format' => ':attribute harus menggunakan format tanggal YYYY-MM-DD.',
            'after_or_equal' => ':attribute tidak boleh sebelum :date.',
            'before_or_equal' => ':attribute tidak boleh setelah :date.',
            'not_in' => ':attribute tidak boleh bernilai :values.',
            'in' => 'Pilihan :attribute tidak valid.',
            'array' => ':attribute tidak valid.',
            'distinct' => ':attribute tidak boleh duplikat.',
            'boolean' => ':attribute harus berupa pilihan ya atau tidak.',
            'string' => ':attribute harus berupa teks.',
        ])->validate();
    }

    public static function moneyRules(bool $positive = false): array
    {
        return ['required', 'numeric', 'decimal:0,2', $positive ? 'gt:0' : 'min:0', 'max:'.Decimal::MAX];
    }

    public static function number(string $prefix): string
    {
        return $prefix.'-'.now()->format('ymd').'-'.strtoupper(substr((string) Str::ulid(), -12));
    }

    public static function fingerprint(array $data): string
    {
        $normalize = function ($value) use (&$normalize) {
            if (is_array($value)) {
                if (! array_is_list($value)) {
                    ksort($value);
                }

                return array_map($normalize, $value);
            }

            return is_int($value) || is_float($value) ? (string) $value : $value;
        };

        return hash('sha256', json_encode($normalize($data), JSON_THROW_ON_ERROR));
    }

    /** A database unique key resolves simultaneous retries after rollback. */
    public static function once(string $modelClass, string $requestId, array $payload, Closure $create): Model
    {
        $hash = self::fingerprint($payload);
        $replay = function () use ($modelClass, $requestId, $hash): ?Model {
            $existing = $modelClass::where('request_id', $requestId)->first();
            if ($existing && ! hash_equals($existing->getRawOriginal('request_hash') ?? '', $hash)) {
                throw ValidationException::withMessages(['request_id' => 'Permintaan ini sudah dipakai untuk data berbeda. Muat ulang formulir.']);
            }

            return $existing;
        };
        try {
            return DB::transaction(function () use ($replay, $modelClass, $requestId, $create, $hash) {
                if ($existing = $replay()) {
                    return $existing;
                }
                // Reserve before business checks. A concurrent duplicate waits here,
                // then the unique constraint leads to the committed original result.
                DB::table('operation_requests')->insert([
                    'scope' => $modelClass, 'request_id' => $requestId, 'created_at' => now(),
                ]);

                return $create($hash);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            return $replay() ?? throw $exception;
        }
    }
}
