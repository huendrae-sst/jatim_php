# Bank Jatim - JIMS (Jatim Inventory Management System)

Aplikasi Sistem Informasi Manajemen Logistik, Pengadaan, dan Distribusi Terpadu Bank Jatim berbasis Laravel.

---

## 📋 Prasyarat Sistem (Prerequisites)

Sebelum menjalankan aplikasi di komputer lokal Anda, pastikan telah terpasang:
- **PHP** >= 8.2 (Direkomendasikan PHP 8.2, 8.3, atau 8.4)
  - Ekstensi PHP yang dibutuhkan: `pdo`, `pdo_pgsql` (atau `pdo_sqlite`), `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `curl`.
- **Composer** >= 2.2
- **Node.js** >= 18.x & **NPM**
- **Database Server**: PostgreSQL (sesuai konfigurasi Bank Jatim) atau SQLite untuk pengujian lokal cepat.
- **Git**

---

## 🚀 Panduan Instalasi Langkah-demi-Langkah (Setup Setelah Clone Git)

Ketika Anda baru saja mengambil (*clone*) repositori ini dari Git, folder `vendor/`, `node_modules/`, dan file `.env` **sengaja tidak disertakan di Git** demi alasan keamanan dan efisiensi.

Ikuti urutan langkah berikut agar project dapat berjalan langsung tanpa error:

### 1. Clone Repositori
Buka terminal dan clone repositori:
```bash
git clone git@github.com:huendrae-sst/jatim_php.git
cd jatim_php
```

### 2. Install Dependensi PHP (Composer)
> **PENTING:** Perintah ini wajib dijalankan pertama kali untuk membuat folder `vendor/` dan file `vendor/autoload.php`. Jika dilewati, perintah `php artisan` akan menghasilkan error `Failed opening required vendor/autoload.php`.

```bash
composer install
```

### 3. Salin & Siapkan File Konfigurasi Environment (`.env`)
Buat salinan file `.env` dari template `.env.example`:
```bash
cp .env.example .env
```

Buka file `.env` yang baru dibuat dengan teks editor Anda, lalu sesuaikan koneksi database Anda:

**Opsi A: Menggunakan PostgreSQL (Default)**
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1       # atau IP server database PostgreSQL Anda
DB_PORT=5432
DB_DATABASE=bank_jatim
DB_USERNAME=postgres    # sesuaikan username database Anda
DB_PASSWORD=secret      # sesuaikan password database Anda
```

**Opsi B: Menggunakan SQLite (Untuk development lokal tanpa server database)**
```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```
*(Jika menggunakan SQLite, pastikan file database sudah dibuat: `touch database/database.sqlite`)*

### 4. Generate Application Key
Jalankan perintah ini untuk membuat kunci enkripsi aplikasi yang unik di file `.env`:
```bash
php artisan key:generate
```

### 5. Install Dependensi Frontend (NPM)
Install pustaka pendukung antarmuka (Bootstrap, Tailwind utilities, dsb.):
```bash
npm install
```

### 6. Migrasi Database & Seeding Data Awal
Jalankan migrasi skema tabel dan isi data dummy lengkap (organisasi cabang, katalog barang, akun pengguna, pagu anggaran, transaksi):
```bash
php artisan migrate --seed
```
*(Atau gunakan `php artisan migrate:fresh --seed` jika ingin mereset ulang seluruh tabel dari awal)*

### 7. Kompilasi Aset Frontend (Vite)
Build aset CSS dan JavaScript:
```bash
npm run build
```

---

## 💻 Menjalankan Server Development

Anda dapat menjalankan server aplikasi secara otomatis menggunakan:

```bash
composer run dev
```

Atau jalankan secara terpisah pada 2 jendela terminal:
- **Terminal 1 (Backend Laravel Server)**:
  ```bash
  php artisan serve
  ```
- **Terminal 2 (Frontend Vite Hot-Reload)**:
  ```bash
  npm run dev
  ```

Akses aplikasi di browser pada: **`http://localhost:8000`**

---

## 👤 Akun Pengguna Default untuk Pengujian

Semua akun hasil seeding menggunakan kata sandi bawaan yang sama:
- **Default Password**: `password123`

| Peran (Role) | Email Login | Unit Kerja / Cabang |
|---|---|---|
| **Super Administrator** | `admin@bankjatim.co.id` | Kantor Pusat (Divisi Umum) |
| **User Administrator** | `useradmin@bankjatim.co.id` | Kantor Pusat (Divisi Umum) |
| **Requester Cabang Surabaya** | `requester.sby@bankjatim.co.id` | Kantor Cabang Utama Surabaya (`KC-SBY`) |
| **Order Approver Surabaya** | `approver.sby@bankjatim.co.id` | Kantor Cabang Utama Surabaya (`KC-SBY`) |
| **Requester Cabang Malang** | `requester.mlg@bankjatim.co.id` | Kantor Cabang Malang (`KC-MLG`) |
| **Receiving Officer Gubeng** | `receiving.gbg@bankjatim.co.id` | Kantor Cabang Pembantu Gubeng (`KCP-GBG`) |
| **Warehouse Officer** | `warehouse@bankjatim.co.id` | Gudang Logistik Rungkut (`GD-RKT`) |
| **Distribution Officer** | `distribusi@bankjatim.co.id` | Gudang Logistik Rungkut (`GD-RKT`) |
| **Procurement Officer** | `proc.officer@bankjatim.co.id` | Kantor Pusat |
| **Procurement Approver** | `proc.approver@bankjatim.co.id` | Kantor Pusat |
| **Finance Officer** | `finance.officer@bankjatim.co.id` | Kantor Pusat |
| **Finance Approver** | `finance.approver@bankjatim.co.id` | Kantor Pusat |
| **Auditor Internal** | `auditor@bankjatim.co.id` | Kantor Pusat |
| **Executive Management** | `management@bankjatim.co.id` | Kantor Pusat |

*(Anda juga dapat berpindah peran kapan saja dengan mengklik tombol **"Simulasi Peran"** di pojok kanan atas navbar atau profil sidebar)*.

---

## 🧪 Menjalankan Pengujian Otomatis (Tests)

Untuk memvalidasi integritas logika, alur persetujuan, dan hak akses multi-unit:
```bash
vendor/bin/phpunit
```
Atau:
```bash
php artisan test --compact
```

Format kode standar (Laravel Pint):
```bash
vendor/bin/pint --format agent
```

---

## ⚠️ Troubleshooting & Solusi Kendala Umum

### 1. `PHP Fatal error: Failed opening required '.../vendor/autoload.php'`
- **Penyebab**: Folder `vendor/` belum ada karena belum menginstal dependensi Composer.
- **Solusi**: Jalankan perintah `composer install`.

### 2. `RuntimeException: No application encryption key has been specified`
- **Penyebab**: Kunci aplikasi di file `.env` masih kosong.
- **Solusi**: Jalankan `php artisan key:generate`.

### 3. `Unable to locate file in Vite manifest`
- **Penyebab**: File aset CSS/JS belum di-bundle oleh Vite.
- **Solusi**: Jalankan `npm run build` atau biarkan `npm run dev` aktif di background.

### 4. `SQLSTATE[08006] Connection to server failed`
- **Penyebab**: Konfigurasi koneksi PostgreSQL di file `.env` belum sesuai atau server database belum berjalan.
- **Solusi**: Periksa `DB_HOST`, `DB_PORT`, `DB_USERNAME`, dan `DB_PASSWORD` pada file `.env`. Untuk mencoba tanpa PostgreSQL, Anda dapat beralih ke SQLite sementara.

---

## 🔒 Catatan Keamanan Git
- **Jangan pernah melakukan `git add -f .env`**: File `.env` berisi rahasia kredensial database dan kunci enkripsi aplikasi.
- File [`.gitignore`](.gitignore) telah dikonfigurasi untuk secara otomatis mengabaikan file `.env`, folder `vendor/`, `node_modules/`, dan file sementara lainnya.
