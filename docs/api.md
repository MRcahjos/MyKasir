# API MyKasir v1

Base URL lokal: `http://127.0.0.1:8000/api/v1`.

## Persiapan dan autentikasi

Jalankan `composer install` dan `php artisan migrate` sebelum menggunakan API. Migrasi menambahkan tabel `personal_access_tokens`. Buat akun pemilik melalui halaman web pada instalasi baru; API memakai akun yang sama.

Kirim `Accept: application/json` dan, untuk body JSON, `Content-Type: application/json`. Login:

```http
POST /api/v1/login
Content-Type: application/json
Accept: application/json

{
  "email": "pemilik@example.com",
  "password": "kata-sandi-anda",
  "device_name": "Kasir Android"
}
```

Respons `200` berisi `message`, `token_type: "Bearer"`, `access_token`, `expires_at`, dan `data` pengguna. Simpan `access_token` dengan aman di perangkat; token lengkap hanya diberikan saat login. Token berlaku 30 hari. Semua endpoint selain login memerlukan:

```http
Authorization: Bearer <access_token>
```

API tidak memerlukan cookie sesi atau CSRF. Setiap token memberikan akses pemilik ke seluruh fitur kasir. `GET /user` mengembalikan pengguna aktif; `POST /logout` mencabut token yang sedang digunakan tanpa mencabut token perangkat lain. Login ulang setelah token kedaluwarsa atau dicabut. Tidak ada endpoint pendaftaran publik.

Login dibatasi 10 permintaan per menit per IP. Endpoint terautentikasi dibatasi 120 permintaan per menit per pengguna. Saat menerima `429`, tunggu sesuai header `Retry-After`.

## Endpoint

Semua path di bawah diawali `/api/v1`. `{product}`, `{supplier}`, `{customer}`, `{purchase}`, dan `{sale}` adalah ID numerik.

| Metode | Path | Fungsi |
| --- | --- | --- |
| POST | `/login` | Mendapatkan token |
| GET | `/user` | Pengguna aktif |
| POST | `/logout` | Mencabut token aktif |
| GET | `/summary` | Ringkasan laporan; filter `from`, `to` |
| GET | `/lookups` | Barang aktif beserta satuan, pelanggan, supplier |
| GET, POST | `/products` | Daftar / tambah barang |
| PUT | `/products/{product}` | Memperbarui barang dan satuan |
| GET, POST | `/suppliers` | Daftar / tambah supplier |
| PUT | `/suppliers/{supplier}` | Memperbarui supplier |
| GET, POST | `/customers` | Daftar / tambah pelanggan |
| PUT | `/customers/{customer}` | Memperbarui pelanggan |
| GET, POST | `/purchases` | Daftar / catat pembelian |
| GET | `/purchases/{purchase}` | Detail pembelian |
| GET, POST | `/sales` | Daftar / catat penjualan |
| GET | `/sales/{sale}` | Detail penjualan beserta pembayaran |
| GET | `/receivables` | Penjualan dengan sisa piutang |
| POST | `/receivables/{sale}/payments` | Mencatat pembayaran piutang |
| GET | `/movements` | Mutasi stok |
| POST | `/stock-adjustments` | Mencatat penyesuaian stok |
| GET, POST | `/expenses` | Daftar / catat pengeluaran |

## Format daftar dan respons

Daftar memakai format yang sama dengan AJAX/DataTables:

```json
{
  "draw": 0,
  "recordsTotal": 1,
  "recordsFiltered": 1,
  "data": [{"id": 1, "name": "Contoh"}]
}
```

Gunakan `start` sebagai offset (awal `0`), `length` sebagai jumlah data (awal `15`, maksimal `100`), dan `search[value]` untuk pencarian. Contoh: `GET /products?start=0&length=20&search[value]=Cup`. Nilai `length=0` atau `-1` tetap dibatasi 100 data. `recordsTotal` dihitung sebelum filter; `recordsFiltered` setelah filter.

Pengurutan: `columns[0][data]=name&order[0][column]=0&order[0][dir]=asc`. Hanya kolom yang diizinkan controller yang dapat digunakan. Daftar pembelian, penjualan, piutang, mutasi, dan pengeluaran mendukung `from`/`to` dalam format `YYYY-MM-DD`. Ringkasan tanpa filter memakai awal bulan sampai hari ini; rentang ringkasan maksimal 1826 hari.

Detail dan lookup mengembalikan `{ "data": ... }`; penyimpanan mengembalikan `{ "message": "...", "data": ... }`. Nilai uang tersimpan dikirim sebagai string desimal. Jumlah barang harus bilangan bulat.

| Status | Arti |
| --- | --- |
| 200 | Berhasil membaca, login, logout, atau memperbarui |
| 201 | Berhasil menyimpan, termasuk retry transaksi yang sama |
| 401 | Token tidak ada, salah, kedaluwarsa, atau telah dicabut |
| 404 | Route atau data tidak ditemukan |
| 422 | Validasi gagal; lihat `message` dan `errors` per field |
| 429 | Batas permintaan tercapai |

Error `/api/*` selalu berupa JSON, termasuk tanpa header `Accept`.

## Payload

### Barang

`POST /products` atau `PUT /products/{product}`:

```json
{
  "sku": "CUP-01",
  "name": "Cup 22 oz",
  "category": "Kemasan",
  "base_unit": "Pcs",
  "minimum_stock": 5,
  "active": true,
  "units": [
    {"name": "Pcs", "multiplier": 1, "selling_price": 17000},
    {"name": "Dus", "multiplier": 20, "selling_price": 330000}
  ]
}
```

Saat menambah barang, `sku` boleh dihilangkan atau dikosongkan untuk membuat kode otomatis dengan format `BRG-<tanggal ymd>-<6 huruf/angka>`, misalnya `BRG-261101-A7K2Q9`. Kode manual tetap bisa dikirim dan harus unik. Saat memperbarui, `sku` wajib dikirim.

Saat memperbarui, kirim seluruh field wajib dan daftar satuan, termasuk `id` setiap satuan lama. Konversi satuan tidak boleh berubah setelah ada stok atau riwayat transaksi. Barang baru memiliki stok nol; gunakan pembelian atau penyesuaian untuk menambah stok.

### Supplier dan pelanggan

`POST /suppliers`, `PUT /suppliers/{supplier}`, `POST /customers`, atau `PUT /customers/{customer}` menerima `name` (wajib), `phone`, dan `address` (opsional).

### Pembelian

```json
{
  "supplier_id": 1,
  "date": "2026-10-29",
  "request_id": "50c719a6-3894-47ee-a0ad-ed2d51b0227c",
  "items": [{"product_id": 1, "unit_id": 2, "quantity": 1, "unit_price": 305000}]
}
```

Kirim ke `POST /purchases`. `unit_price` adalah harga beli per satuan yang dipilih. Pembelian langsung diterima, dianggap lunas, dan menambah stok.

### Penjualan

```json
{
  "customer_id": null,
  "date": "2026-10-29",
  "request_id": "232fd3f8-530a-483e-9df9-f92cd445a9b7",
  "items": [{"product_id": 1, "unit_id": 1, "quantity": 2, "expected_unit_price": 17000}],
  "received_amount": 40000,
  "payment_method": "cash"
}
```

Kirim ke `POST /sales`. Harga jual ditentukan server; `expected_unit_price` opsional membantu menolak keranjang dengan harga lama. Pelanggan wajib jika pembayaran kurang dari total. Pelanggan dengan piutang aktif harus melunasi transaksi baru. Pembayaran lebih hanya diperbolehkan untuk tunai. `payment_method` adalah `cash`, `transfer`, atau `qris`.

### Pembayaran piutang

`POST /receivables/{sale}/payments`:

```json
{
  "amount": 10000,
  "date": "2026-10-29",
  "method": "transfer",
  "request_id": "21c04303-5407-4fae-a466-99352331af78"
}
```

Tanggal tidak boleh sebelum penjualan. Pembayaran mengurangi piutang tanpa mencatat ulang penjualan atau mengurangi stok.

### Penyesuaian stok

`POST /stock-adjustments`:

```json
{
  "product_id": 1,
  "quantity": 5,
  "unit_cost": 15250,
  "notes": "Stok awal",
  "date": "2026-10-29",
  "request_id": "39672510-50c0-4beb-abda-70e8ff2c85f8"
}
```

`quantity` adalah perubahan stok dalam satuan dasar, positif untuk masuk dan negatif untuk keluar. `unit_cost` wajib untuk penambahan; `notes` wajib minimal 3 karakter.

### Pengeluaran

`POST /expenses` menerima `date`, `category`, `description`, `amount` (positif), dan `payment_method` (`cash`, `transfer`, atau `qris`), semuanya wajib.

### Retry transaksi

Pembelian, penjualan, pembayaran piutang, dan penyesuaian stok wajib memakai `request_id` UUID unik. Buat UUID baru untuk setiap operasi baru; jika koneksi terputus, ulangi dengan UUID dan payload yang persis sama. Server mengembalikan transaksi lama tanpa menggandakan perubahan stok atau pembayaran. UUID yang sama dengan payload berbeda ditolak dengan `422`. Pembuatan barang, supplier, pelanggan, dan pengeluaran belum mendukung retry idempoten. Pembelian, penjualan, dan pembayaran mendukung `notes` opsional.
