<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_requests', function (Blueprint $table) {
            $table->string('scope', 150);
            $table->uuid('request_id');
            $table->timestamp('created_at');
            $table->primary(['scope', 'request_id']);
        });
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 80)->unique();
            $table->string('name');
            $table->string('category', 100)->nullable();
            $table->string('base_unit', 40);
            $table->bigInteger('stock')->default(0);
            $table->unsignedInteger('minimum_stock')->default(0);
            $table->decimal('average_cost', 18, 6)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('name', 40);
            $table->unsignedInteger('multiplier');
            $table->decimal('selling_price', 18, 2);
            $table->timestamps();
            $table->unique(['product_id', 'name']);
        });
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->date('date')->index();
            $table->decimal('total', 18, 2);
            $table->text('notes')->nullable();
            $table->uuid('request_id')->unique();
            $table->string('request_hash', 64);
            $table->timestamps();
        });
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('date')->index();
            $table->decimal('total', 18, 2);
            $table->decimal('cost_total', 18, 2);
            $table->decimal('paid_amount', 18, 2);
            $table->decimal('balance', 18, 2)->index();
            $table->decimal('received_amount', 18, 2);
            $table->decimal('change_amount', 18, 2)->default(0);
            $table->string('payment_method', 20);
            $table->text('notes')->nullable();
            $table->uuid('request_id')->unique();
            $table->string('request_hash', 64);
            $table->timestamps();
        });
        foreach (['purchase', 'sale'] as $kind) {
            Schema::create($kind.'_items', function (Blueprint $table) use ($kind) {
                $table->id();
                $table->foreignId($kind.'_id')->constrained($kind.'s')->restrictOnDelete();
                $table->foreignId('product_id')->constrained()->restrictOnDelete();
                $table->foreignId('unit_id')->constrained('product_units')->restrictOnDelete();
                $table->string('product_name');
                $table->string('unit_name', 40);
                $table->unsignedInteger('multiplier');
                $table->unsignedInteger('quantity');
                $table->decimal('unit_price', 18, 2);
                $table->decimal('subtotal', 18, 2);
                if ($kind === 'sale') {
                    $table->decimal('cost_total', 18, 2);
                }
                $table->timestamps();
            });
        }
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->date('date')->index();
            $table->string('method', 20);
            $table->text('notes')->nullable();
            $table->uuid('request_id')->unique();
            $table->string('request_hash', 64);
            $table->timestamps();
        });
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->bigInteger('quantity');
            $table->bigInteger('balance');
            $table->decimal('unit_cost', 18, 6);
            $table->decimal('cost_total', 18, 2)->default(0);
            $table->string('reference', 60);
            $table->text('notes')->nullable();
            $table->date('date')->index();
            $table->uuid('request_id')->nullable()->unique();
            $table->string('request_hash', 64)->nullable();
            $table->timestamps();
        });
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('category', 100);
            $table->string('description');
            $table->decimal('amount', 18, 2);
            $table->string('payment_method', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['expenses', 'stock_movements', 'payments', 'sale_items', 'purchase_items', 'sales', 'purchases', 'product_units', 'products', 'customers', 'suppliers', 'operation_requests'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
