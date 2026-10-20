<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccessAndPagesTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = [
        'dashboard', 'barang', 'supplier', 'pelanggan', 'pembelian', 'kasir',
        'penjualan', 'piutang', 'stok', 'pengeluaran', 'laporan',
    ];

    public function test_all_store_pages_require_owner_login(): void
    {
        foreach (self::PAGES as $page) {
            $this->get('/'.$page)->assertRedirect(route('login'));
        }
    }

    public function test_an_empty_installation_displays_owner_setup(): void
    {
        $this->get('/login')->assertOk()->assertViewIs('auth.login')->assertViewHas('setup', true);
    }

    public function test_owner_setup_hashes_password_and_signs_the_owner_in(): void
    {
        $this->postJson('/setup', [
            'name' => 'Pemilik Toko',
            'email' => 'pemilik@example.test',
            'password' => 'KataSandiToko123!',
            'password_confirmation' => 'KataSandiToko123!',
        ])->assertCreated()->assertJsonPath('redirect', route('dashboard'));

        $owner = User::sole();
        $this->assertSame('Pemilik Toko', $owner->name);
        $this->assertTrue(Hash::check('KataSandiToko123!', $owner->password));
        $this->assertNotSame('KataSandiToko123!', $owner->password);
        $this->assertAuthenticatedAs($owner);
    }

    public function test_owner_setup_rejects_unconfirmed_or_short_passwords(): void
    {
        $this->postJson('/setup', [
            'name' => 'Pemilik Toko', 'email' => 'pemilik@example.test',
            'password' => 'short', 'password_confirmation' => 'different',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_setup_closes_after_the_first_owner_is_created(): void
    {
        User::factory()->create();

        $this->get('/login')->assertOk()->assertViewHas('setup', false);
        $this->postJson('/setup', [
            'name' => 'Pemilik Kedua', 'email' => 'kedua@example.test',
            'password' => 'KataSandiToko123!', 'password_confirmation' => 'KataSandiToko123!',
        ])->assertForbidden();

        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    public function test_owner_can_log_in_through_ajax(): void
    {
        $owner = User::factory()->create(['email' => 'pemilik@example.test', 'password' => 'KataSandiToko123!']);

        $this->postJson('/login', [
            'email' => 'pemilik@example.test', 'password' => 'KataSandiToko123!', 'remember' => true,
        ])->assertOk()->assertJsonPath('redirect', route('dashboard'));

        $this->assertAuthenticatedAs($owner);
    }

    public function test_wrong_credentials_do_not_authenticate_the_owner(): void
    {
        User::factory()->create(['email' => 'pemilik@example.test', 'password' => 'KataSandiToko123!']);

        $this->postJson('/login', [
            'email' => 'pemilik@example.test', 'password' => 'SalahKataSandi123!',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_ajax_logout_removes_access_to_pages_and_data(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson('/logout')->assertOk()->assertJsonPath('redirect', route('login'));

        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->getJson('/ajax/products')->assertUnauthorized();
        $this->postJson('/ajax/sales', [])->assertUnauthorized();
    }

    public function test_all_store_pages_render_for_the_owner(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (self::PAGES as $page) {
            $this->get('/'.$page)->assertOk()->assertViewIs($page.'.index');
        }
    }
}
