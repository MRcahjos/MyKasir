# MyKasir

Aplikasi kasir pribadi berbasis Laravel 13 dan AdminLTE 4, dengan antarmuka Bahasa Indonesia dan sidebar hijau muda. Form dikirim melalui AJAX, daftar data memakai DataTables, dan notifikasi serta konfirmasi memakai SweetAlert2. Aset tampilan tersedia di `public`; halaman kasir ini tidak memerlukan Vite untuk berjalan.

## Menjalankan di Windows

Prasyarat: PHP 8.3 atau lebih baru, Composer, ekstensi BCMath, serta driver PDO sesuai database. Untuk PostgreSQL, server dan database harus sudah tersedia. Untuk SQLite, aktifkan `pdo_sqlite` dan `sqlite3` atau pastikan berkas ekstensinya tersedia dalam instalasi PHP.

Jalankan dari folder proyek:

```powershell
composer install
.\Start-MyKasir.ps1
```

Buka **http://127.0.0.1:8000**. Pada database kosong, halaman awal meminta Anda membuat akun pemilik sendiri. Setelah akun dibuat, halaman ini berubah menjadi halaman masuk. Tidak ada akun atau kata sandi bawaan yang perlu digunakan.

Launcher mempertahankan `.env` yang sudah ada, termasuk konfigurasi PostgreSQL. Jika `.env` belum ada, launcher menyalin `.env.example` dengan SQLite sebagai pilihan awal. Launcher memeriksa ekstensi PHP, membuat kunci aplikasi hanya jika belum terisi, menjalankan migrasi yang belum diterapkan, lalu menyalakan server lokal. Berkas database hanya dibuat ketika koneksi yang dipakai adalah SQLite dan berkasnya belum ada. Launcher tidak memasukkan data contoh.

Untuk port lain:

```powershell
.\Start-MyKasir.ps1 -Port 8001
```

Tekan **Ctrl+C** pada terminal untuk berhenti. Server ini untuk penggunaan lokal. Atur `APP_URL` sesuai alamat aplikasi dan `APP_TIMEZONE` sesuai zona toko; contoh konfigurasi menggunakan `Asia/Jakarta` (WIB).

## Alur pemakaian

1. **Supplier:** simpan supplier tempat kulakan.
2. **Barang:** buat barang, satuan terkecil, batas stok minimum, dan satuan kemasan. Contoh: Cup sebagai satuan dasar, Dus berisi 20 Cup. Harga jual tiap satuan ditentukan sendiri.
3. **Pembelian:** pilih supplier, barang, satuan, jumlah, dan harga kulakan. Menyimpan pembelian berarti barang sudah diterima dan pembelian sudah lunas. Membeli 1 dus isi 20 menambah stok 20 cup.
4. **Kasir:** pilih barang dan satuan jual. Menjual 1 cup mengurangi 1 cup; menjual 1 dus mengurangi 20 cup. Bayar dengan tunai, transfer, atau QRIS. Pelanggan wajib dipilih jika ada sisa pembayaran.
5. **Piutang:** buka tagihan dan catat pembayaran penuh atau cicilan. Pelanggan yang masih berutang tetap boleh belanja lunas, tetapi tidak boleh membuat utang baru sebelum utang lama habis. Pembayaran piutang tidak mengurangi stok atau mencatat penjualan ulang.
6. **Stok:** lihat mutasi dan catat penyesuaian hasil hitung fisik atau barang rusak. Jumlah penyesuaian memakai satuan terkecil dan wajib disertai alasan. Penambahan stok meminta modal per satuan terkecil.
7. **Pengeluaran dan Laporan:** catat beban usaha, lalu pilih periode untuk melihat penjualan, penerimaan pembayaran, modal barang terjual, laba, dan piutang.

Untuk stok yang sudah ada sebelum memakai aplikasi, gunakan penyesuaian stok masuk dengan modal yang benar. Untuk kulakan baru, gunakan menu Pembelian supaya supplier dan biaya pembelian tercatat.

## Cara membaca laba

Modal persediaan menggunakan rata-rata tertimbang. Harga dan modal transaksi penjualan disimpan saat transaksi terjadi, sehingga perubahan harga selanjutnya tidak mengubah laba transaksi lama.

- **Laba kotor:** penjualan dikurangi modal barang yang terjual.
- **Laba bersih:** laba kotor dikurangi pengeluaran tercatat dan kerugian penyesuaian stok keluar.
- **Uang diterima:** pembayaran penjualan dan pembayaran piutang pada tanggal pembayaran, termasuk tunai, transfer, dan QRIS.
- **Piutang:** total tagihan yang belum lunas saat ini, bukan hanya transaksi pada periode laporan.
- **Nilai stok:** nilai persediaan saat ini; pembelian barang yang belum terjual tidak langsung dibebankan seluruhnya sebagai modal penjualan.

Contoh: 1 dus berisi 20 cup dibeli Rp305.000, berarti modal awal Rp15.250 per cup. Penjualan 1 cup seharga Rp17.000 menghasilkan laba kotor Rp1.750. Penjualan berutang tetap tercatat pada tanggal penjualan; uang masuk baru tercatat saat pelanggan membayar. Laba bersih bergantung pada kelengkapan pengeluaran yang Anda masukkan.

## Struktur kode

```text
resources/views/          Halaman Blade menurut fitur
public/scripts/
  auth/                  Login dan pembuatan akun pertama
  layouts/               Interaksi layout
  shared/                AJAX/CSRF, format, dan DataTables
  barang/, supplier/, pelanggan/, pembelian/, kasir/,
  penjualan/, piutang/, stok/, pengeluaran/, laporan/, dashboard/
public/css/app.css       Tampilan aplikasi
public/assets/           AdminLTE dan pustaka frontend lokal
app/Http/Controllers/    Permintaan HTTP dan respons JSON
app/Services/            Aturan bisnis, transaksi, stok, piutang, dan laporan
routes/pos.php           Endpoint AJAX dengan sesi login dan CSRF
tests/Feature/           Pengujian alur bisnis, HTTP, dan akses halaman
```

JavaScript dikelompokkan mengikuti nama view dan dimuat per halaman. Aturan harga, stok, konversi satuan, dan kelayakan berutang diperiksa kembali oleh backend. Service menggunakan transaksi database dan menyimpan nilai uang dengan perhitungan desimal.

Service disiapkan agar dapat dipakai kembali oleh controller API aplikasi mobile. **API mobile dengan versi endpoint dan autentikasi token belum dibuat**; endpoint `/ajax` saat ini menggunakan autentikasi sesi web dan CSRF.

## Pengujian

Pengujian memakai SQLite dalam memori melalui `phpunit.xml`. Jalankan PHPUnit langsung agar opsi ekstensi PHP diteruskan ke proses pengujian:

```powershell
php artisan config:clear
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit
```

Jika BCMath belum aktif, tambahkan `-d extension=bcmath`. Hilangkan opsi `-d extension=...` untuk ekstensi yang sudah aktif di `php.ini` agar tidak muncul peringatan dimuat dua kali. Menjalankan `php -d ... artisan test` dapat membuat proses anak PHPUnit kehilangan opsi ekstensi tersebut. Gunakan konfigurasi pengujian bawaan dan jangan menimpanya dengan koneksi database toko.

Data contoh bersifat opsional melalui `DemoSeeder`; gunakan hanya pada database latihan yang terpisah. Jangan menjalankan seeder contoh pada database toko sebenarnya.

## Menyimpan cadangan

Simpan cadangan sebelum pembaruan aplikasi atau migrasi, dan lakukan secara rutin setelah operasional toko. Simpan pula `.env` secara pribadi karena berisi kunci aplikasi dan pengaturan koneksi.

- **SQLite:** hentikan aplikasi dan seluruh proses yang menulis ke database, lalu salin berkas database yang dikonfigurasi ke lokasi cadangan bertanggal. Jika database masih sedang digunakan, gunakan fasilitas backup SQLite; jangan hanya menyalin berkas yang masih aktif ditulis.
- **PostgreSQL:** gunakan fitur Backup di pgAdmin atau `pg_dump` untuk database toko yang dikonfigurasi. Simpan hasil dump di lokasi berbeda dan uji pemulihan ke database baru yang terpisah.

Jangan menggunakan `migrate:fresh`, `db:wipe`, atau menghapus berkas database pada data toko yang ingin dipertahankan. Migrasi biasa pada launcher menerapkan perubahan skema yang belum dijalankan.

## Batas versi ini

Pembelian langsung diterima dan lunas; belum ada draf pembelian atau utang supplier. Retur, pembatalan transaksi yang sudah tersimpan, diskon, beberapa cabang, pembagian peran kasir, dan API mobile belum tersedia. Pembayaran transfer/QRIS dicatat manual, belum terhubung ke penyedia pembayaran. Jumlah barang menggunakan bilangan bulat; barang timbang dengan jumlah pecahan belum didukung.
