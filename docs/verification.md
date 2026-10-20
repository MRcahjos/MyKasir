# Catatan verifikasi — 20 Oktober 2026

## Pemeriksaan otomatis

- PHPUnit: **35 tes, 269 assertion, lulus**, memakai SQLite dalam memori.
- Pemulihan permintaan transaksi: **7 tes JavaScript lulus**.
- Pemeriksaan sintaks: **17 berkas JavaScript lulus**.
- `composer validate --no-check-publish`: valid.

Skenario backend mencakup konversi dus/ecer, modal rata-rata, pembatasan piutang,
pembayaran sebagian, stok tidak negatif, pengulangan permintaan tanpa transaksi
ganda, perubahan harga, tanggal transaksi, otorisasi, dan render halaman.

## Pemeriksaan browser

Pengujian menggunakan akun dan database SQLite contoh
`storage/app/mykasir-preview.sqlite`, terpisah dari konfigurasi database toko.

- Login, dashboard, kasir, struk, pembelian, dan laporan berhasil dibuka.
- Penjualan contoh 1 dus berisi 20 cup seharga Rp330.000 berhasil tersimpan.
  Stok turun dari 31 menjadi 11 cup; uang diterima Rp350.000, kembalian Rp20.000.
- Penjualan contoh 1 cup pada tampilan ponsel berhasil tersimpan. Stok menjadi
  10 cup; laporan menampilkan harga Rp17.000, modal Rp15.250, laba kotor Rp1.750.
- Pelanggan contoh yang masih memiliki piutang Rp51.000 tidak dapat memilih
  pembayaran sebagian atau utang baru, tetapi transaksi lunas dapat diproses.
- Dashboard diurutkan berdasarkan tanggal transaksi terbaru, dengan ID sebagai
  urutan penentu untuk transaksi pada tanggal yang sama.
- Tampilan desktop dan ponsel diperiksa; halaman kasir tidak memiliki luapan
  horizontal. Menu ponsel dapat dibuka.
- Log browser yang diperiksa tidak menampilkan error atau warning.

Tangkapan layar berada di `docs/screenshots/`. Seluruh angka pada tangkapan layar
merupakan data simulasi.

## Pemeriksaan manual yang masih diperlukan

- Konfirmasi akhir formulir pembelian melalui browser: pemeriksaan persetujuan
  otomatis menahan klik karena mengategorikannya sebagai transaksi keuangan.
  Formulir dan perhitungan Rp305.000 per dus sudah diperiksa; pembelian contoh
  tersebut tidak disimpan. Logika pembelian telah diuji oleh PHPUnit.
- Pembayaran cicilan melalui antarmuka dan hasil cetak pada printer toko.
  Aturan pembayaran sebagian telah diuji oleh PHPUnit; printer fisik belum diuji.

Jalankan aplikasi mengikuti `README.md`. Gunakan database latihan terpisah saat
mencoba transaksi contoh.
