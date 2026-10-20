<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Pemilik membuat akun melalui halaman setup. Data contoh hanya opt-in
        // melalui DemoSeeder pada database khusus pengujian.
    }
}
