<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AutomaticProductSkuTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_products_without_a_code_generates_distinct_codes(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo(now()->setDate(2026, 11, 1));

        $codes = [];
        foreach ([[], ['sku' => ''], ['sku' => null], ['sku' => '   ']] as $override) {
            $product = $this->postJson('/ajax/products', [...$this->productData(), ...$override])
                ->assertCreated()->json('data');

            $this->assertMatchesRegularExpression('/^BRG-261101-[A-Z0-9]{6}$/', $product['sku']);
            $this->assertDatabaseHas('products', ['id' => $product['id'], 'sku' => $product['sku'], 'stock' => 0]);
            $codes[] = $product['sku'];
        }

        $this->assertCount(4, array_unique($codes));
    }

    public function test_generation_skips_a_code_that_is_already_used(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 1));
        $products = app(ProductService::class);
        $products->save([...$this->productData(), 'sku' => 'BRG-261101-ABC123']);
        Str::createRandomStringsUsingSequence(['abc123', 'def456']);

        try {
            $product = $products->save($this->productData());
            $this->assertSame('BRG-261101-DEF456', $product->sku);
            $this->assertDatabaseCount('products', 2);
        } finally {
            Str::createRandomStringsNormally();
        }
    }

    public function test_manual_codes_are_preserved_and_duplicates_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $payload = [...$this->productData(), 'sku' => 'CUP-22'];

        $this->postJson('/ajax/products', $payload)->assertCreated()->assertJsonPath('data.sku', 'CUP-22');
        $this->postJson('/ajax/products', $payload)->assertUnprocessable()->assertJsonValidationErrors('sku');
        $this->assertDatabaseCount('products', 1);
    }

    public function test_editing_preserves_the_code_and_does_not_regenerate_a_blank_code(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->postJson('/ajax/products', $this->productData())->assertCreated()->json('data');
        $payload = [...$this->productData(), 'sku' => $product['sku'], 'name' => 'Cup Baru', 'units' => $product['units']];

        $this->putJson('/ajax/products/'.$product['id'], $payload)->assertOk()
            ->assertJsonPath('data.sku', $product['sku'])->assertJsonPath('data.name', 'Cup Baru');

        foreach ([[], ['sku' => ''], ['sku' => null]] as $override) {
            $withoutSku = $payload;
            unset($withoutSku['sku']);
            $this->putJson('/ajax/products/'.$product['id'], [...$withoutSku, ...$override])
                ->assertUnprocessable()->assertJsonValidationErrors('sku');
        }

        $this->assertSame($product['sku'], Product::findOrFail($product['id'])->sku);
        $this->assertDatabaseCount('products', 1);
    }

    private function productData(): array
    {
        return [
            'name' => 'Cup', 'base_unit' => 'Pcs', 'minimum_stock' => 0, 'active' => true,
            'units' => [['name' => 'Pcs', 'multiplier' => 1, 'selling_price' => 11000]],
        ];
    }
}
