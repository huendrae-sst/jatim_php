# PRODUCT REQUIREMENT DOCUMENT (PRD)
## JIMS (Jatim Inventory Management System)
### Sistem Informasi Manajemen Logistik, Pengadaan, Distribusi, dan Personalisasi Kartu Terpadu

---

| Informasi Dokumen | Detail |
| :--- | :--- |
| **Nama Produk** | Bank Jatim - JIMS (*Jatim Inventory Management System*) |
| **Organisasi / Entitas** | PT Bank Pembangunan Daerah Jawa Timur Tbk (Bank Jatim) |
| **Versi Dokumen** | 1.0 (Comprehensive Enterprise Edition) |
| **Tanggal Terbit** | 12 September 2026 |
| **Status Dokumen** | **Approved / Production Baseline** |
| **Target Pengguna** | Divisi Umum, Logistik, Pengadaan, Keuangan, Operasional Cabang/Capem, Auditor, Eksekutif |
| **Tech Stack Utama** | Laravel 11/12 (PHP 8.4/8.5), PostgreSQL, Blade / Tailwind CSS, Vite |

---

## 1. Executive Summary & Background

### 1.1 Latar Belakang
PT Bank Pembangunan Daerah Jawa Timur Tbk (Bank Jatim) memiliki jaringan kantor yang luas meliputi Kantor Pusat, Kantor Cabang Utama (KC), Kantor Cabang Pembantu (KCP), Kantor Kas, serta unit Gudang Logistik terpusat. Aktivitas operasional perbankan membutuhkan ketersediaan logistik yang konsisten dan akurat, mulai dari barang cetakan/warkat perbankan (buku tabungan, bilyet deposito, formulir), perlengkapan kantor (ATK), kebutuhan TI/elektronik, hingga bahan baku kartu ATM/debit yang memerlukan proses personalisasi (*embossing*).

Sebelum kehadiran JIMS, pengelolaan persediaan logistik menghadapi beberapa tantangan operasional:
1. **Pencatatan Stok Tersebar (*Silo Data*)**: Sulit memantau ketersediaan barang secara *real-time* di seluruh jaringan kantor Bank Jatim.
2. **Risiko Stockout & Overstock**: Ketidakseimbangan distribusi menyebabkan sebagian cabang kehabisan stok kritis warkat/kartu, sementara cabang lain mengalami penumpukan barang (*idle/excess stock*).
3. **Pengadaan yang Terfragmentasi**: Pengajuan kebutuhan dari unit kerja sering kali diproses secara parsial tanpa konsolidasi terpusat, sehingga kehilangan peluang efisiensi biaya (*economies of scale*).
4. **Kendali Anggaran Lemah**: Pemesanan barang belum tervalidasi secara otomatis terhadap pagu dan realisasi anggaran operasional unit.
5. **Ketiadaan Rekonsiliasi & Settlement Antarunit**: Perpindahan barang antarunit (kantor pusat ke cabang, atau antar-cabang) belum terhubung secara otomatis dengan pembebanan akuntansi debit/kredit (*General Ledger*).
6. **Kepatuhan Audit Perbankan**: Diperlukan tata kelola berbasis *maker-checker*, jejak audit (*audit trail*) yang tidak dapat diubah (*immutable*), dan buku besar persediaan (*stock ledger*) dengan prinsip mutasi ganda (*double-entry stock*).

### 1.2 Tujuan Produk
JIMS dikembangkan sebagai platform *Single Source of Truth* untuk siklus persediaan end-to-end dengan target:
1. Mengintegrasikan seluruh siklus hidup persediaan: perencanaan anggaran, *Purchase Request* (PR), konsolidasi PR menjadi *Purchase Order* (PO), penerimaan vendor, penyimpanan gudang, pemesanan unit kerja, *switching stock*, *picking & packing*, ekspedisi & pelacakan, konfirmasi penerimaan, penanganan diskrepansi, retur barang, pemusnahan barang, hingga penyelesaian finansial (*inter-unit financial settlement*).
2. Memfasilitasi integrasi berkas *emboss* personalisasi kartu ATM dari *core banking* untuk memicu penerbitan dan distribusi kartu warkat perbankan secara aman.
3. Memberikan visibilitas eksekutif dan operasional melalui *Executive Support System* (ESS), peringatan dini (*Early Warning System* - EWS), dan peramalan kebutuhan (*statistical forecasting*).

---

## 2. Visi Produk, Sasaran & KPI

### 2.1 Visi Produk
Menjadi sistem manajemen operasional logistik dan persediaan perbankan daerah terbaik di Indonesia yang aman, efisien, akuntabel, dan berbasis data analitik prediktif.

### 2.2 Key Performance Indicators (KPI)
- **Stockout Incidents**: Penurunan insiden kehabisan stok warkat dan barang operasional kritis di cabang hingga 0%.
- **Order Fulfillment Cycle Time**: Mempercepat durasi siklus pemenuhan pesanan dari rata-rata 7 hari kerja menjadi maksimal 2 hari kerja.
- **Cost Saving melalui Konsolidasi PR**: Peningkatan efisiensi biaya pengadaan minimal 10-15% melalui mekanisme *Approved PR Pool Consolidation*.
- **Inventory Discrepancy Rate**: Menjaga selisih fisik persediaan vs sistem di bawah 0.01% dengan rekonsiliasi berkala dan *Stock Opname*.
- **Audit Compliance**: 100% kepatuhan tata kelola *maker-checker* dan ketertelusuran dokumen (*traceability*) sesuai regulasi OJK dan Standar Audit Internal Bank Jatim.

---

## 3. Struktur Organisasi & Pengguna (User Personas)

Aplikasi JIMS menerapkan kontrol akses berbasis peran (*Role-Based Access Control* / RBAC) dengan pembatasan hierarki unit kerja (*Organization Scope*).

```
Kantor Pusat (Head Office)
 ├── Divisi Umum & Logistik (Super Admin, User Admin, Master Maker/Approver, Warehouse Officer, Distribution Officer)
 ├── Divisi Pengadaan / Procurement (Procurement Officer, Procurement Approver)
 ├── Divisi Keuangan / Finance (Budget Officer, Finance Officer, Finance Approver)
 ├── Divisi Audit Internal (Internal Auditor)
 └── Direksi / Manajemen (Executive Management)
 
Kantor Wilayah / Kantor Cabang Utama (KC)
 ├── Warehouse / Logistik Cabang
 ├── Requester Cabang
 └── Order Approver (Pimpinan Cabang / Penyelia Operasional)
 
Kantor Cabang Pembantu (KCP) & Kantor Kas
 ├── Requester Unit
 └── Receiving Officer (Petugas Penerima Barang)
```

### 3.1 Matriks Peran dan Tanggung Jawab

| Peran (Role) | Ruang Lingkup (Scope) | Tanggung Jawab Utama |
| :--- | :--- | :--- |
| **Super Administrator** | Seluruh Sistem | Konfigurasi parameter global, manajemen menu, hak akses, pembersihan data simulasi, dan kesehatan sistem. |
| **User Administrator** | Lintas Unit | Pendaftaran user, pengaturan peran (*role*), pemetaan hak akses (*permission*), dan penugasan unit kerja/organisasi. |
| **Master Data Maker** | Lintas Unit / Pusat | Pembuatan dan pembaruan data referensi: Barang, Kategori, UOM, Konversi Satuan, Gudang, Vendor, Ekspedisi, dan Akuntansi (CoA & Cost Center). |
| **Master Data Approver** | Kantor Pusat | Verifikasi dan persetujuan perubahan master data kritis (*maker-checker*). |
| **Budget Officer** | Pusat & Cabang | Penginputan dan pemeliharaan pagu anggaran per unit/periode, pemantauan utilisasi pagu, komitmen, dan realisasi. |
| **Procurement Officer** | Kantor Pusat | Pembuatan *Purchase Request* (PR), pengelolaan *Approved PR Pool*, konsolidasi banyak PR menjadi satu *Purchase Order* (PO), interaksi vendor, dan penerbitan PO. |
| **Procurement Approver** | Kantor Pusat | Persetujuan PR dan PO sesuai matriks batasan kewenangan (*approval limits*). |
| **Inventory Officer** | Pusat & Gudang | Pemantauan saldo stok (*Stock Balance*), buku besar persediaan (*Stock Ledger*), penyesuaian stok (*Stock Adjustment*), *Stock Opname*, dan inisialisasi saldo awal (*Initial Stock*). |
| **Warehouse Officer** | Gudang (Pusat/Cabang) | Penerimaan barang dari vendor (*Goods Receipt*), pemrosesan antrean *Picking* (pengambilan barang), dan antrean *Packing* (pengemasan koli). |
| **Requester (Cabang/Capem)**| Unit Kerja Masing-masing | Pengajuan permohonan kebutuhan barang operasional (*Branch Order*), pengecekan sisa anggaran unit, dan pelacakan pesanan. |
| **Order Approver** | Unit Kerja Masing-masing | Persetujuan pesanan unit kerja berjenjang berdasarkan batas nominal pesanan. |
| **Switching Stock Approver**| Unit Sumber / Pusat | Persetujuan pengalihan stok antar-cabang/gudang untuk mengatasi defisit stok tanpa pengadaan baru. |
| **Distribution Officer** | Gudang & Logistik | Pembuatan manifest pengiriman, cetak label koli ber-QR/barcode, penyerahan ke ekspedisi, dan pembaruan resi pelacakan (*Shipment Tracking*). |
| **Receiving Officer** | Unit Penerima (Cabang/Capem)| Konfirmasi penerimaan fisik kiriman, verifikasi kuantitas/kondisi, pencatatan diskrepansi, dan unduh Berita Acara Penerimaan. |
| **Finance Officer** | Divisi Keuangan | Verifikasi transaksi mutasi antarunit, pembuatan *Inter-Unit Settlement*, dan draft jurnal penyesuaian (*General Ledger*). |
| **Finance Approver** | Divisi Keuangan | Otorisasi *Settlement* dan *posting* otomatis jurnal buku besar umum. |
| **Auditor Internal** | Seluruh Sistem (Read-Only) | Pemeriksaan kepatuhan transaksi, *audit trail*, buku besar stok, perbandingan fisik vs sistem, dan laporan kepatuhan. |
| **Executive Management** | Seluruh Sistem | Akses dasbor eksekutif, analisis *Executive Support System* (ESS), evaluasi *Cost Saving*, dan analisis risiko persediaan. |

---

## 4. Arsitektur Sistem & Konsep Inti

### 4.1 Prinsip Fondasi Desain
1. **Single Source of Truth**: Seluruh transaksi persediaan, pengadaan, dan logistik bermuara pada satu basis data terintegrasi.
2. **Immutable Stock Ledger**: Saldo persediaan tidak dapat diubah secara langsung (*no direct overwrite*). Setiap penambahan, pengurangan, atau reservasi stok dicatat melalui jurnal mutasi stok (*double-entry stock transaction*).
3. **Maker-Checker & Segregation of Duties (SoD)**: Pembuat dokumen tidak diizinkan menyetujui transaksi miliknya sendiri pada dokumen kritis (PR, PO, Order, Adjustment, Settlement, Pemusnahan).
4. **End-to-End Traceability**: Keterlacakan penuh dari pengajuan kebutuhan cabang atau warkat emboss $\rightarrow$ alokasi stok $\rightarrow$ pengiriman $\rightarrow$ tanda terima $\rightarrow$ jurnal settlement akuntansi. Pada sisi pengadaan: PR $\rightarrow$ PO $\rightarrow$ Penerimaan Vendor $\rightarrow$ Stok Gudang.
5. **Multi-Horizon Early Warning System (EWS)**: Deteksi proaktif terhadap potensi kehabisan stok, penumpukan stok (*excess*), dan penipisan kuota anggaran operasional.

### 4.2 Formula & Mekanisme Saldo Stok (Stock Balance Engine)
Setiap pencatatan persediaan pada suatu Gudang/Lokasi dikelompokkan ke dalam kategori saldo:
- **On Hand**: Total stok fisik aktual yang ada di lokasi penyimpanan.
- **Reserved**: Kuantitas stok yang telah dicadangkan untuk order yang disetujui, mencegah alokasi ganda (*prevent double allocation*).
- **Allocated**: Kuantitas stok yang telah dialokasikan ke proses *picking* dan *packing*.
- **In Transit**: Kuantitas barang yang telah dikirim oleh sumber namun belum dikonfirmasi terima oleh unit tujuan.
- **Hold / Damaged**: Kuantitas barang yang rusak, kedaluwarsa, atau ditahan menunggu Berita Acara Pemusnahan/Retur.
- **Available Stock**: Saldo stok yang siap untuk dipesan atau dipindahkan.

$$\text{Available Stock} = \text{On Hand} - (\text{Reserved} + \text{Allocated} + \text{Hold} + \text{Damaged})$$

### 4.3 Formula & Mekanisme Anggaran Unit (Budget Engine)
Setiap unit kerja memiliki alokasi pagu anggaran per mata anggaran (CoA/Cost Center) per periode:

$$\text{Available Budget} = \text{Pagu Anggaran} - (\text{Komitmen} + \text{Realisasi})$$

- **Komitmen**: Terbentuk saat pengajuan pesanan/PR disetujui namun belum selesai diserahterimakan/dibayar.
- **Realisasi**: Terbentuk saat barang telah diterima dan penyelesaian finansial (*settlement*) diposting.

---

## 5. Alur Kerja Utama (End-to-End Workflows)

### 5.1 Workflow 1: Siklus Pengadaan Terpadu (Procurement Lifecycle)
```
[EWS / Rekomendasi Pengadaan]
        │
        ▼
[Purchase Request (PR) Draft] ──► [Validasi Anggaran Otomatis] ──► [PR Approval (Maker-Checker)]
                                                                           │
                                                                           ▼
                                                             [Approved PR Pool]
                                                                           │
                                                                           ▼
                                                              [Konsolidasi Banyak PR]
                                                                           │
                                                                           ▼
                                                            [Terbitkan Purchase Order (PO)]
                                                                           │
                                                                           ▼
                                                               [PO Approval & Kirim Vendor]
                                                                           │
                                                                           ▼
                                                              [Penerimaan Barang (Goods Receipt)]
                                                                           │
                                                                           ▼
                                                         [Stock Posting ke Stock Ledger (Tersedia)]
```

**Aturan Bisnis Konsolidasi:**
- Satu PO dapat menggabungkan 1 atau lebih PR yang telah berstatus `APPROVED`.
- Sistem menyimpan relasi pemetaan granular: setiap baris item PO mencatat ID PR sumber beserta kuantitas yang dikonsolidasikan.
- Kuantitas PO tidak boleh melampaui sisa kuantitas PR yang belum diproses.
- Status PR bertransisi dari `APPROVED` $\rightarrow$ `PARTIALLY_ORDERED` $\rightarrow$ `FULLY_ORDERED` $\rightarrow$ `CLOSED`.

### 5.2 Workflow 2: Pemenuhan Pesanan Cabang (Branch Order Fulfillment)
```
[Cabang Membuat Order] ──► [Cek Sisa Pagu Unit] ──► [Order Approval Berjenjang]
                                                                │
                                                                ▼
                                                    [Reservasi Stok Otomatis]
                                                                │
                                                ┌───────────────┴───────────────┐
                                      [Stok Tersedia Cukup]          [Stok Defisit]
                                                │                               │
                                                │                               ▼
                                                │                     [Switching Stock Engine]
                                                │                     (Rekomendasi Cabang Lain)
                                                │                               │
                                                ▼                               ▼
                                      [Antrean Picking Gudang] ◄────────────────┘
                                                │
                                                ▼
                                      [Antrean Packing Gudang] (Timbang, Koli, Dimensi)
                                                │
                                                ▼
                                      [Manifest & Label Ekspedisi (QR Code)]
                                                │
                                                ▼
                                      [Dispatched & Tracking Status (In Transit)]
                                                │
                                                ▼
                                      [Konfirmasi Penerimaan Cabang]
                                                │
                                 ┌──────────────┴──────────────┐
                           [Sesuai (Match)]            [Diskrepansi / Rusak]
                                 │                             │
                                 │                             ▼
                                 │                 [Catat BAP Diskrepansi]
                                 │                             │
                                 └──────────────┬──────────────┘
                                                ▼
                                  [Inter-Unit Financial Settlement]
                                                │
                                                ▼
                                  [Posting Jurnal General Ledger (Debit/Kredit)]
```

### 5.3 Workflow 3: Switching Stock (Pengalihan Stok Antar-Cabang)
1. **Pemicu**: Pesanan cabang tidak dapat dipenuhi sepenuhnya oleh gudang utama karena keterbatasan stok.
2. **Mesin Rekomendasi**: Sistem mengevaluasi stok unit-unit sekitar berdasarkan:
   - Saldo stok berlebih (*excess above safety stock*).
   - Jarak geografis dan estimasi biaya logistik terendah.
   - SLA pengiriman tercepat.
3. **Persetujuan**: Pengajuan *Switching Stock* dikirimkan ke pihak berwenang di unit sumber dan koordinator logistik pusat.
4. **Eksekusi**: Stok unit sumber direservasi $\rightarrow$ barang dikirim via ekspedisi $\rightarrow$ penerimaan di cabang pemohon $\rightarrow$ jurnal akuntansi antarunit.

### 5.4 Workflow 4: Integrasi Personalisasi Kartu & Emboss (POC-20 ~ POC-28)
1. **Unggah Berkas**: Admin mengunggah berkas data permintaan kartu ATM/debit (*batch file* TXT/CSV/DAT hingga 50 MB) yang dihasilkan oleh *Core Banking System*.
2. **Validasi & Parsing**: Sistem memvalidasi struktur berkas, format nomor kartu, tipe kartu, dan mendeteksi data duplikasi (*duplicate records*).
3. **Penanganan Reject**: Baris data yang cacat masuk ke *Reject Queue* untuk dapat diperbaiki secara manual dan diproses ulang (*reprocess*).
4. **Otomasi Order**: Dari berkas emboss yang valid, sistem secara otomatis menghasilkan pesanan pemenuhan kartu warkat dan perlengkapan (*welcome pack*, amplop PIN) ke Gudang Personalisasi.
5. **Produksi & Personalisasi (POC-30)**: Gudang menerbitkan *Production Order*, memotong stok kartu polos (*blank card*), mencetak warkat, dan menerbitkan manifest pengiriman kartu jadi ke cabang pemesan.

### 5.5 Workflow 5: Reverse Inventory (Retur & Pemusnahan Barang - POC-41, POC-43)
- **Retur Barang**: Cabang dapat mengajukan pengembalian barang berlebih atau salah kirim ke Gudang Pusat dengan alur *Approval $\rightarrow$ Shipment $\rightarrow$ Goods Receipt Gudang*.
- **Pemusnahan Barang (Disposal)**: Warkat rusak, kartu cacat/kedaluwarsa, atau material kedaluwarsa dipindahkan ke status *Hold/Damaged*, diverifikasi oleh Auditor dan Petugas Kepatuhan, dieksekusi pemusnahan secara fisik, dan diterbitkan dokumen digital **Berita Acara Pemusnahan Barang**.

---

## 6. Rincian Kebutuhan Fungsional (Functional Requirements)

### Modul 1: Manajemen Pengguna & Kontrol Akses (User & RBAC)
- **FR-01-01**: Sistem harus menyediakan manajemen akun pengguna lengkap dengan NIK, nama lengkap, email resmi `@bankjatim.co.id`, jabatan, nomor kontak, status aktif/non-aktif, dan unit kerja (*organization mapping*).
- **FR-01-02**: Sistem harus mendukung multi-peran (*multiple roles*) per pengguna dengan hak akses granular pada level menu dan aksi (*view, create, edit, delete, approve, reject, export, print*).
- **FR-01-03**: Sistem harus menyediakan fitur **Simulasi Peran (*Quick Role Switcher*)** pada lingkungan pengujian/staging untuk mempermudah verifikasi skenario *maker-checker* lintas unit.
- **FR-01-04**: Sistem harus menerapkan pemfilteran data berbasis unit kerja (*Data Scoping*): pengguna cabang hanya dapat melihat data unit kerjanya sendiri, sedangkan kantor pusat/auditor memiliki cakupan nasional (*all branches*).

### Modul 2: Master Data Manajemen Terpadu
- **FR-02-01 (Katalog Barang)**: Mengelola master item persediaan meliputi kode item unik, nama, kategori, subkategori, tipe barang (Warkat, Kartu ATM, ATK, Cetakan, Elektronik, Seragam), UOM primer, harga estimasi, stok minimum (*safety stock*), stok maksimum, *reorder point* (ROP), dan *lead time* (hari).
- **FR-02-02 (Konversi Satuan)**: Mengelola tabel konversi UOM (misal: 1 Box = 10 Pack; 1 Pack = 50 Lembar).
- **FR-02-03 (Hierarki Organisasi)**: Mengelola struktur unit Bank Jatim (Kantor Pusat, Kantor Cabang, Kantor Cabang Pembantu, Kantor Kas, Titik Gudang) lengkap dengan kode kantor, cost center, dan hierarki induk-anak.
- **FR-02-04 (Gudang & Lokasi)**: Mengelola lokasi gudang penyimpanan fisik dan gudang virtual (Gudang Pusat Logistik, Gudang Personalisasi Kartu, Gudang Transit, Gudang Rusak).
- **FR-02-05 (Vendor & Rekanan)**: Mengelola profil rekanan penyedia barang/jasa, NPWP, kontak, bank rekening, dan rekam jejak performa vendor.
- **FR-02-06 (Ekspedisi & Logistik)**: Mengelola master kurir/ekspedisi (internal Bank Jatim, JNE, Pos Indonesia, vendor rekanan logistik khusus warkat) dan pemetaan ekspedisi per rute wilayah (*Expedition Mapping*).
- **FR-02-07 (Bagan Akun Standar / CoA & Cost Center)**: Mengelola kode rekening akuntansi debit/kredit dan pusat biaya operasional per unit kerja.

### Modul 3: Manajemen Anggaran (Budgeting & Control)
- **FR-03-01**: Mengelola pagu anggaran operasional logistik per unit kerja per mata anggaran per tahun fiskal.
- **FR-03-02**: Validasi otomatis sisa anggaran saat pengajuan Order atau PR: sistem wajib menolak atau memberi peringatan jika nilai permohonan melebihi *available budget*.
- **FR-03-03**: Pencatatan otomatis *Budget Commitment* saat transaksi disetujui, dan pengonversian menjadi *Realisasi* saat transaksi diserahterimakan/selesai.
- **FR-03-04**: Peringatan Dini Anggaran (*Budget Early Warning System*): Notifikasi otomatis ketika penyerapan anggaran unit mencapai ambang batas 70%, 85%, dan 95%.

### Modul 4: Pengadaan Barang (Procurement, PR & PO)
- **FR-04-01 (Purchase Request)**: Pembuatan PR internal dilengkapi estimasi harga, justifikasi pengadaan, mata anggaran, dan tanggal kebutuhan.
- **FR-04-02 (Rekomendasi PR dari EWS)**: Fitur rekomendasi otomatis item yang perlu diajukan PR berdasarkan status stok yang berada di bawah *reorder point*.
- **FR-04-03 (Approved PR Pool)**: PR yang disetujui secara otomatis berkumpul di *Approved PR Pool* untuk dianalisis oleh Procurement Officer.
- **FR-04-04 (Konsolidasi Multi-PR ke PO)**: Memilih beberapa item dari PR berbeda (misal: pesanan ATK dari berbagai cabang) untuk digabungkan ke dalam 1 nomor PO pengadaan massal ke vendor.
- **FR-04-05 (Purchase Order Management)**: Penerbitan dokumen formal PO kepada vendor, pelacakan acknowledgement, termin pengiriman, dan cetak PDF format resmi Bank Jatim.
- **FR-04-06 (Penerimaan PO / Vendor Goods Receipt)**: Pencatatan barang kiriman vendor di gudang utama, mendukung penerimaan bertahap (*partial delivery*), inspeksi kondisi barang, dan pembaruan sisa *outstanding PO*.

### Modul 5: Pemesanan Cabang & Persetujuan (Branch Orders)
- **FR-05-01**: Requester unit kerja dapat memilih barang dari katalog yang aktif, menentukan jumlah permohonan, dan melampirkan keterangan peruntukan.
- **FR-05-02**: Sistem memvalidasi ketersediaan anggaran cabang dan ketersediaan stok fisik secara langsung (*real-time stock availability check*).
- **FR-05-03**: Alur persetujuan berjenjang:
  - Nominal $\le$ Rp 5.000.000: Cukup persetujuan Penyelia/Seksi Unit.
  - Nominal $>$ Rp 5.000.000 s/d Rp 25.000.000: Persetujuan Wakil Pemimpin Cabang.
  - Nominal $>$ Rp 25.000.000: Persetujuan Pemimpin Cabang / Otoritas Pusat.
- **FR-05-04**: Saat order disetujui, kuantitas barang otomatis berubah menjadi status *Reserved*.
- **FR-05-05**: Fasilitas cetak formulir Surat Permintaan Barang (SPB) format resmi berbarcode.

### Modul 6: Switching Stock Engine
- **FR-06-01**: Algoritma deteksi otomatis cabang donor potensial yang memiliki kelebihan stok (*excess inventory*) dari item yang sedang defisit.
- **FR-06-02**: Form pengajuan pengalihan stok antarunit (*Switching Stock Request*) yang memerlukan persetujuan dari unit asal dan otoritas pengawas.
- **FR-06-03**: Pemotongan saldo unit asal ke status *In Transit* dan penambahan otomatis ke unit penerima setelah konfirmasi serah terima dilakukan.

### Modul 7: Integrasi Emboss Kartu ATM & Personalisasi (POC-20 s/d POC-28)
- **FR-07-01**: Modul pengunggahan berkas batch emboss (ekstensi .txt, .csv, .dat) dengan proteksi ukuran berkas hingga 50 MB.
- **FR-07-02**: Mesin parser berkas yang membaca *field*: Nomor Rekening, Nomor Kartu, Nama Nasabah Teremboss, Jenis Kartu (GPN, Mastercard, Kartu Pegawai, Kartu Prioritas), Cabang Penerbit, dan Tanggal Kadaluwarsa.
- **FR-07-03**: Validasi format dan keunikan: Baris yang duplikat atau cacat otomatis dipisahkan ke antrean *Reject Queue* tanpa menggugurkan data baris lain yang valid.
- **FR-07-04**: *Reprocess Queue*: Kemampuan memperbaiki kesalahan format data pada baris reject dan memprosesnya ulang.
- **FR-07-05**: *Generate Orders*: Tombol satu-klik untuk mengonversi data batch emboss yang telah divalidasi menjadi pesanan pemenuhan kartu fisik ke gudang personalisasi.

### Modul 8: Operasional Produksi Kartu & Personalisasi (POC-30, POC-32)
- **FR-08-01**: Pelacakan status *Production Order* (Menunggu Bahan, Proses Cetak/Emboss, QC Kartu, Siap Kirim).
- **FR-08-02**: Pengurangan stok bahan baku (*blank card* dan warkat terkait) dari gudang bahan baku secara otomatis saat perintah produksi dieksekusi.
- **FR-08-03**: Penerbitan *Production Manifest* untuk pengiriman batch kartu ke cabang tujuan.

### Modul 9: Operasional Gudang (Warehouse Picking & Packing)
- **FR-09-01 (Picking Queue)**: Daftar antrean kerja petugas gudang untuk mengambil barang di rak, verifikasi kuantitas fisik, dan input catatan pengambilan.
- **FR-09-02 (Packing Queue)**: Pemrosesan barang yang telah diambil menjadi paket koli pengiriman, pencatatan dimensi (panjang, lebar, tinggi), berat aktual (kg), jumlah koli, dan label keamanan (*security seal*).
- **FR-09-03**: Otomasi status: Pesanan yang selesai dikemas bertransisi menjadi `READY_TO_SHIP`.

### Modul 10: Distribusi, Manifest & Pelacakan Ekspedisi
- **FR-10-01 (Manifest Pengiriman)**: Pengelompokan beberapa koli/pesanan ke dalam satu Berkas Manifest Pengiriman per kurir/rute.
- **FR-10-02 (Cetak Label Koli)**: Cetak label stiker pengiriman berukuran standar lengkap dengan Nomor Resi, Alamat Cabang Penerima, Nomor Koli (misal: Koli 1 dari 3), dan QR Code pelacakan.
- **FR-10-03 (Shipment & Tracking)**: Perekaman nama ekspedisi, nomor resi pengiriman (*waybill*), tanggal dispatch, estimasi kedatangan (ETA), serta log status pengiriman (*In Transit, Out for Delivery, Delivered, Failed*).

### Modul 11: Penerimaan Cabang, Diskrepansi & Berita Acara
- **FR-11-01**: Antarmuka konfirmasi penerimaan untuk Receiving Officer di cabang/capem dengan pemindaian QR Code atau pencarian nomor resi/order.
- **FR-11-02**: Verifikasi pencocokan kuantitas: Jumlah Dikirim vs Jumlah Diterima dalam kondisi Baik.
- **FR-11-03 (Pencatatan Diskrepansi)**: Apabila terjadi selisih (kurang, lebih, rusak, cacat kemasan, salah item), petugas wajib mencatat kuantitas selisih, kategori insiden, foto bukti, dan kronologi.
- **FR-11-04 (Berita Acara Digital)**: Sistem secara otomatis mengompilasi dan mengunduh dokumen resmi **Berita Acara Penerimaan Barang (BAP)** atau **Berita Acara Diskrepansi** dalam format siap cetak/PDF lengkap dengan tanda tangan para pihak.
- **FR-11-05**: Penutupan status pesanan menjadi `RECEIVED` atau `COMPLETED` dan saldo fisik cabang langsung diperbarui di *Stock Ledger*.

### Modul 12: Reverse Inventory (Retur & Pemusnahan Barang)
- **FR-12-01 (Retur Barang)**: Alur formal pengembalian barang dari cabang ke gudang utama (Pengajuan $\rightarrow$ Otorisasi $\rightarrow$ Pengiriman $\rightarrow$ Penerimaan Gudang).
- **FR-12-02 (Pemusnahan Warkat/Kartu Rusak - POC-43)**: Fasilitas pencatatan barang rusak/usang yang akan dimusnahkan.
- **FR-12-03 (Persetujuan & Eksekusi Pemusnahan)**: Verifikasi oleh tim gabungan (Divisi Umum, Kepatuhan, dan Auditor Internal). Saat dieksekusi, sistem menghapus stok dari *Hold/Damaged Stock* dan mencatatnya ke *Stock Ledger* dengan tipe transaksi *Destruction/Write-off*.
- **FR-12-04 (Berita Acara Pemusnahan)**: Penerbitan berkas resmi Berita Acara Pemusnahan Barang Inventaris/Warkat Bank Jatim.

### Modul 13: Manajemen Persediaan & Stock Ledger (Inventory Engine)
- **FR-13-01 (Kartu Stok / Stock Card)**: Riwayat terperinci keluar-masuk setiap item per lokasi gudang, menampilkan saldo awal, mutasi masuk, mutasi keluar, saldo akhir, nomor referensi transaksi, dan user pelaksana.
- **FR-13-02 (Stock Adjustment)**: Fasilitas koreksi saldo stok karena selisih penyesuaian yang wajib melalui persetujuan *maker-checker*.
- **FR-13-03 (Stock Opname)**: Jadwal dan kertas kerja pemeriksaan fisik stok berkala (bulanan/tahunan), perbandingan otomatis stok buku vs fisik, serta kalkulasi deviasi.
- **FR-13-04 (Initial Stock Import)**: Fitur impor massal saldo awal persediaan saat *go-live* atau pembukaan cabang baru melalui file template Excel/CSV.
- **FR-13-05 (Reconciliation & Movement Inquiry)**: Pelacakan jejak mutasi historis untuk kebutuhan audit forensik persediaan.

### Modul 14: Penyelesaian Keuangan Antarunit & Jurnal Akuntansi (Settlement & General Ledger)
- **FR-14-01**: Pembuatan otomatis draft *Inter-Unit Financial Settlement* begitu pesanan dinyatakan selesai diterima di cabang.
- **FR-14-02**: Perhitungan nilai perolehan barang (*cost of goods sold*) dan biaya pengiriman yang dibebankan kepada unit penerima.
- **FR-14-03**: Persetujuan *Settlement* oleh Finance Approver.
- **FR-14-04**: Pencatatan otomatis pasangan jurnal debit dan kredit (*General Ledger Entries*) berdasarkan pemetaan akun CoA dan Cost Center:
  - *Debit: Beban Perlengkapan / Warkat Cabang Pemesan*
  - *Kredit: Persediaan Logistik Kantor Pusat / Gudang Pengirim*

### Modul 15: Peringatan Dini (EWS) & Peramalan Kebutuhan (Forecasting)
- **FR-15-01 (EWS Stock Out & Overstock)**: Pemindaian otomatis seluruh stok cabang/gudang untuk mengidentifikasi item yang berada di bawah *Safety Stock* atau berada jauh di atas *Maximum Stock*.
- **FR-15-02 (Notifikasi Proaktif)**: Mengirimkan peringatan ke dasbor pengguna terkait untuk segera melakukan pengadaan (PR) atau pengalihan (*Switching Stock*).
- **FR-15-03 (Statistikal Forecasting)**: Estimasi kebutuhan pemakaian barang untuk periode 30, 60, dan 90 hari ke depan berbasis data historis konsumsi dengan metode *Moving Average / Trend Analysis*.

### Modul 16: Executive Support System (ESS) & Pelaporan
- **FR-16-01**: Dasbor Eksekutif dengan grafik interaktif performa persediaan, total valuasi aset gudang, rasio pemenuhan pesanan (SLA), dan utilisasi anggaran.
- **FR-16-02 (7 Laporan Khusus Eksekutif ESS)**:
  1. *Valuation & Budget Report*: Nilai nominal persediaan aktif vs sisa pagu anggaran per unit.
  2. *Cost Saving Report*: Analisis efisiensi biaya yang dicapai dari konsolidasi PR dan program *Switching Stock*.
  3. *Inventory Turnover (ITO) Report*: Rasio perputaran stok per kategori barang (identifikasi barang *fast-moving* vs *slow-moving/dead-stock*).
  4. *Risk Heatmap Report*: Peta risiko persediaan berdasarkan probabilitas kehabisan warkat/kartu pada cabang-cabang strategis.
  5. *Service Level Agreement (SLA) Report*: Evaluasi kecepatan pemenuhan pesanan dari pengajuan sampai penerimaan fisik.
  6. *Audit Compliance Report*: Kepatuhan alur kerja, transaksi tanpa approval yang dibatalkan, dan catatan diskrepansi penerimaan.
  7. *Predictive Budget Report*: Estimasi proyeksi kebutuhan anggaran pengadaan periode mendatang berbasis tren konsumsi.
- **FR-16-03 (Ekspor Laporan)**: Seluruh laporan dapat diekspor ke dalam format CSV dan PDF siap saji.

### Modul 17: Pusat Notifikasi & Jejak Audit (Audit Trail)
- **FR-17-01**: Pusat Notifikasi terpadu dengan 3 kategori prioritas:
  - `ACTION_REQUIRED` (Persetujuan transaksi pending, verifikasi diskrepansi).
  - `ALERT` (Peringatan stok kritis, pagu anggaran menipis).
  - `INFORMATION` (Pemberitahuan status pesanan dikirim, barang telah diterima).
- **FR-17-02**: Deep-linking URL pada notifikasi yang mengarahkan pengguna langsung ke halaman detail transaksi terkait.
- **FR-17-03**: Pencatatan jejak audit (*Audit Log*) menyeluruh tanpa opsi penghapusan (*append-only*): mencatat *User ID, Event Type, Entity Model, Record ID, Old Values, New Values, IP Address, User-Agent,* dan *Timestamp*.

---

## 7. Kebutuhan Non-Fungsional (Non-Functional Requirements)

### 7.1 Keamanan & Kepatuhan Perbankan (Security & Regulatory)
- **NFR-SEC-01**: Mengikuti regulasi keamanan OJK (Otoritas Jasa Keuangan) dan Bank Indonesia terkait penyelenggaraan sistem teknologi informasi perbankan.
- **NFR-SEC-02**: Enkripsi berkas dan data rahasia nasabah pada modul emboss personalisasi kartu (data nomor kartu dan identitas diproteksi sesuai kaidah keamanan informasi perbankan).
- **NFR-SEC-03**: Penerapan proteksi sesi, proteksi CSRF (*Cross-Site Request Forgery*), sanitasi input terhadap ancaman SQL Injection dan XSS (*Cross-Site Scripting*).
- **NFR-SEC-04**: Kata sandi dienkripsi dengan algoritma *Bcrypt / Argon2id*.

### 7.2 Kinerja & Skalabilitas (Performance & Scalability)
- **NFR-PRF-01**: Waktu muat halaman utama dan dasbor operasional $\le$ 2.0 detik pada kondisi jaringan intranet perbankan normal.
- **NFR-PRF-02**: Pemrosesan berkas batch emboss 50 MB (hingga 50.000 data kartu) harus selesai dalam waktu $\le$ 60 detik melalui mekanisme *asynchronous processing / queue workers*.
- **NFR-PRF-03**: Menjamin transaksi mutasi stok konkuren (*concurrent stock updates*) menggunakan isolasi transaksi database (*Database Transactions & Pessimistic/Optimistic Locking*) guna menghindari kondisi *race-condition* atau saldo negatif.

### 7.3 Keandalan & Integritas Data (Reliability & Integrity)
- **NFR-REL-01**: Prinsip integritas data mutlak: saldo persediaan pada *Stock Balance* harus selalu identik dengan kalkulasi kumulatif dari *Stock Ledger*.
- **NFR-REL-02**: Kegagalan pada langkah transaksi kritis (misal: saat *posting* jurnal gagal) wajib melakukan *automatic rollback* untuk mempertahankan konsistensi data.

### 7.4 Kompatibilitas & Antarmuka (UI/UX)
- **NFR-UI-01**: Desain antarmuka responsif dan konsisten berbasis standar desain Bank Jatim (palet warna korporat: Merah Bank Jatim, Emas, Abu-abu, dan Putih).
- **NFR-UI-02**: Kompatibel dengan peramban standar perbankan modern: Google Chrome, Microsoft Edge, dan Mozilla Firefox.

---



## 8. Spesifikasi Teknis & Skema Database

### 8.1 Lingkungan Pengembangan & Produksi
| Layer | Teknologi | Alasan Pemilihan |
|---|---|---|
| **Frontend** | **Vue.js 3** (Composition API) + **Vite** | *Ditetapkan.* Vite mempercepat dev/build; Composition API memudahkan pemisahan logic per modul. |
| **Styling** | **Tailwind CSS dan Shadcn UI** |
| **State Management** | **Pinia** | Standar resmi state management Vue 3, ringan dan TypeScript-friendly. |
| **UI Component Library** | **PrimeVue** (alt: Element Plus) | Komponen data table, form, dashboard chart siap pakai untuk aplikasi data-heavy. |
| **HTTP Client** | **Axios** | Mendukung interceptor untuk auth token & error handling terpusat. |
| **Chart/Dashboard** | **ECharts** (`vue-echarts`) | Performa baik untuk dashboard anggaran & analitik stok dengan drill-down. |
| **Backend Framework** | **Spring Boot 3.x** (Java 17/21 LTS) | *Ditetapkan.* Matang untuk aplikasi enterprise perbankan. |
| **API Style** | **REST + OpenAPI** (springdoc-openapi) | Kontrak API terdokumentasi otomatis. |
| **Security** | **Spring Security + JWT**, opsional **Keycloak** | Role/unit-based authorization; SSO korporat jika dibutuhkan. |
| **Database** | **PostgreSQL 15+** | *Ditetapkan, sesuai syarat wajib KAK 3.a.7.* |
| **Migrasi Skema DB** | **Flyway** | Versioning skema sinkron dengan rilis aplikasi, auditable. |
| **ORM** | **Spring Data JPA (Hibernate)** | Terintegrasi native dengan Spring Boot. |
| **Caching** | **Redis** | Caching data referensi & session token. |
| **Asynchronous Processing** | **RabbitMQ** (alt: Kafka bila volume sangat tinggi) | Pemrosesan file emboss volume besar secara asinkron. |
| **File Storage** | **MinIO** (S3-compatible, on-premise) | Manifest, label, berita acara (PDF) tetap on-premise. |
| **Export Laporan** | **Apache POI** (XLSX), **iText/OpenPDF** (PDF), CSV native | Memenuhi kebutuhan ekspor multi-format. |
| **Monitoring & Logging** | **Prometheus + Grafana**, **ELK/OpenSearch** | Observability & correlation ID, mendukung health check (KAK 3.c.4.e). |
| **Containerization** | **Docker** + **Kubernetes** (atau Docker Compose skala kecil) | Deployment terisolasi per service, mendukung HA. |
| **API Gateway** | **Spring Cloud Gateway** | Satu ekosistem dengan backend, routing & audit trail terpusat. |
| **CI/CD** | **GitLab CI** / **Jenkins** (on-premise) | Menghindari dependency ke layanan cloud publik. |
| **Testing** | **JUnit 5 + Mockito**, **Vitest + Vue Test Utils**, **Cypress/Playwright** | Cakupan unit test hingga system integration test, UAT, security acceptance test, performance/stress test (KAK 3.c.3). |
| **Version Management** | **Git** (repository di-share ke Bank Jatim sesuai kesepakatan) | Memenuhi KAK 3.d.1.c (join development, repository code). |
| **Source Code Escrow** | **Escrow agreement** pihak ketiga yang disepakati | Memenuhi KAK 3.d.1.b. |

### 8.2 Daftar Entitas Model Database Utama

| Nama Tabel / Model | Deskripsi Fungsional |
| :--- | :--- |
| `users` | Data akun pengguna, kredensial, relasi unit organisasi, dan peran. |
| `organizations` | Master unit kerja (Kantor Pusat, Cabang Utama, Cabang Pembantu, Gudang). |
| `items` | Master data katalog barang, kode barang, kategori, UOM, spesifikasi stok minimum/maksimum. |
| `categories` & `uoms` | Klasifikasi kelompok barang dan satuan ukuran barang. |
| `item_conversions` | Faktor konversi antar satuan (UOM). |
| `warehouses` | Lokasi fisik dan virtual penyimpanan persediaan. |
| `stock_balances` | Saldo stok agregat per item per gudang (On-hand, Reserved, Allocated, In-transit, Available). |
| `stock_ledgers` | Jurnal transaksi historis mutasi stok persediaan (*immutable ledger*). |
| `budgets` | Data pagu anggaran unit, alokasi per mata anggaran, komitmen, dan realisasi. |
| `purchase_requests` | Dokumen pengajuan pembelian internal (PR) beserta rincian item permohonan. |
| `purchase_orders` | Dokumen pesanan pembelian formal ke rekanan vendor (PO) hasil konsolidasi PR. |
| `goods_receipts` | Bukti transaksi fisik penerimaan barang kiriman vendor di gudang utama. |
| `orders` & `order_items`| Transaksi permohonan barang dari unit cabang dan daftar rincian barang yang diminta. |
| `order_allocations` | Alokasi pemenuhan pesanan dari titik gudang atau cabang donor (*split allocation*). |
| `warehouse_pickings` | Lembar kerja pengambilan barang fisik di gudang penyimpanan. |
| `warehouse_packings` | Lembar kerja pengemasan barang ke dalam koli pengiriman. |
| `shipments` | Data pengiriman ekspedisi, nomor resi kurir, status pelacakan logistik. |
| `receivings` | Data pencatatan penerimaan fisik kiriman oleh unit cabang pemesan. |
| `discrepancies` | Catatan rekonsiliasi selisih barang (kurang/lebih/rusak) dan lampiran bukti. |
| `switching_stocks` | Transaksi pengalihan stok antar-cabang/gudang untuk pencegahan *stockout*. |
| `emboss_files` | Berkas batch integrasi personalisasi kartu ATM dari *core banking*. |
| `emboss_records` | Baris data individual kartu pada berkas emboss (nomor kartu, nama nasabah, status parsing). |
| `production_orders` | Perintah kerja personalisasi fisik kartu polos dan amplop PIN. |
| `inventory_returns` | Pengajuan dan transaksi retur barang dari unit cabang ke gudang pusat. |
| `stock_destructions` | Transaksi pemusnahan resmi barang/warkat rusak beserta berita acara pemusnahan. |
| `settlements` | Transaksi penyelesaian finansial pembebanan biaya antarunit kerja. |
| `chart_of_accounts` | Bagan akun buku besar perbankan (CoA). |
| `cost_centers` | Kode pusat pertanggungjawaban biaya unit kerja. |
| `general_ledger_entries` | Jurnal otomatis debit/kredit akuntansi biaya logistik. |
| `notifications` | Pesan notifikasi sistem, prioritas tindakan, dan pelacakan status baca. |
| `audit_logs` | Jejak audit kepatuhan atas segala aktivitas penambahan, pengubahan, dan persetujuan data. |

---

## 9. Status Transaksi & Diagram Mesin Keadaan (State Machine)

### 9.1 Siklus Status Pesanan Cabang (Branch Order)
```
[DRAFT] 
   │ (Submit)
   ▼
[SUBMITTED] 
   │ (Verifikasi Sistem)
   ▼
[WAITING_APPROVAL] ──► [REJECTED] / [RETURNED_FOR_REVISION]
   │ (Approved by Approver)
   ▼
[APPROVED] 
   │ (Reservasi Stok Sukses)
   ▼
[ALLOCATED] 
   │ (Gudang Mulai Ambil)
   ▼
[PICKING] 
   │ (Selesai Ambil & Mulai Kemas)
   ▼
[PACKING] 
   │ (Selesai Kemas & Koli Siap)
   ▼
[READY_TO_SHIP] 
   │ (Serah Terima Ekspedisi)
   ▼
[IN_TRANSIT] 
   │ (Cabang Konfirmasi Terima)
   ▼
[RECEIVED] 
   │ (Settlement Finansial Selesai)
   ▼
[COMPLETED]
```

### 9.2 Siklus Status Purchase Request (PR)
```
[DRAFT] ──► [SUBMITTED] ──► [BUDGET_VALIDATED] ──► [WAITING_APPROVAL] ──► [APPROVED] 
                                                                               │
                                                   ┌───────────────────────────┴───────────────────────────┐
                                                   ▼                                                       ▼
                                         [PARTIALLY_ORDERED]                                       [FULLY_ORDERED]
                                                   │                                                       │
                                                   └───────────────────────────┬───────────────────────────┘
                                                                               ▼
                                                                            [CLOSED]
```

### 9.3 Siklus Status Purchase Order (PO)
```
[DRAFT] ──► [GENERATED_FROM_PR] ──► [ISSUED] ──► [VENDOR_PROCESS] ──► [IN_DELIVERY] 
                                                                           │
                                                   ┌───────────────────────┴───────────────────────┐
                                                   ▼                                               ▼
                                         [PARTIAL_RECEIVED]                                   [RECEIVED]
                                                   │                                               │
                                                   └───────────────────────┬───────────────────────┘
                                                                           ▼
                                                                      [COMPLETED]
```

---

## 10. Kriteria Penerimaan & Verifikasi (Acceptance Criteria)

Sistem dinyatakan lulus uji dan siap operasional apabila memenuhi kriteria berikut:
1. **Verifikasi Saldo & Ledger**: Setiap pergerakan barang menghasilkan baris baru pada tabel `stock_ledgers` yang menyeimbangkan saldo fisik pada `stock_balances`.
2. **Integritas Anggaran**: Pengajuan order yang melampaui sisa pagu anggaran unit diblokir oleh sistem dengan pesan validasi yang jelas.
3. **Pemisahan Tugas (Maker-Checker)**: Tidak ada pengguna yang dapat menyetujui transaksi (PR, PO, Order, Settlement, Adjustment, Pemusnahan) yang dibuat oleh akunnya sendiri.
4. **Konsolidasi PR ke PO**: Satu PO dapat dibuat dari gabungan beberapa PR yang disetujui, dan seluruh item PO memiliki referensi yang valid ke item PR sumber.
5. **Penanganan Batch Emboss**: Berkas berukuran 50 MB dengan 50.000 baris dapat diunggah, diparsing tanpa kegagalan memori, dan data duplikat berhasil dipisahkan ke *Reject Queue*.
6. **Berita Acara Digital**: Fitur Berita Acara Penerimaan, Berita Acara Diskrepansi, dan Berita Acara Pemusnahan menghasilkan dokumen cetak berformat standar Bank Jatim dengan metadata lengkap.
7. **Jurnal Otomatis**: Transaksi settlement yang disetujui secara otomatis membukukan entri debit dan kredit yang seimbang (*balanced*) pada buku besar umum (`general_ledger_entries`).
8. **Jejak Audit**: Seluruh aktivitas persetujuan (*approve*), penolakan (*reject*), dan perubahan data terekam lengkap pada tabel `audit_logs`.

---

## 11. Roadmap Pengembangan & Rilis

```
Fase 1: Fondasi Inti (Selesai / Baseline)
 ├── Master Data Terpadu & Multi-Organisasi Hierarkis
 ├── RBAC, Scope Organisasi & Akses Pengguna
 ├── Stock Ledger Persediaan & Stock Balance Engine
 ├── Branch Order & Approval Workflow Maker-Checker
 ├── Operasional Gudang (Picking, Packing, Dispatch)
 ├── Ekspedisi, Pelacakan Resi & Penerimaan Cabang
 └── Penanganan Diskrepansi & Cetak Berita Acara Digital

Fase 2: Pengadaan, Personalisasi & Reverse Logistics (Selesai / Baseline)
 ├── Modul Purchase Request (PR) & Validasi Anggaran Unit
 ├── Approved PR Pool & Mesin Konsolidasi PR ke PO
 ├── Penerimaan Barang Vendor (PO Goods Receipt)
 ├── Switching Stock Engine (Rekomendasi Distribusi Antar-Cabang)
 ├── Integrasi Emboss Kartu ATM & Parsing Batch File (POC-20 ~ POC-28)
 ├── Operasional Produksi Kartu & Personalisasi (POC-30, POC-32)
 ├── Reverse Inventory: Retur Barang Cabang ke Gudang (POC-41)
 └── Pemusnahan Warkat/Kartu Rusak & Berita Acara Pemusnahan (POC-43)

Fase 3: Keuangan, Otomasi & Analitik Eksekutif (Selesai / Baseline)
 ├── Inter-Unit Financial Settlement & Otomasi Jurnal General Ledger (CoA & Cost Center)
 ├── Early Warning System (EWS) untuk Minimum Stock, Overstock & Budget Threshold
 ├── Peramalan Kebutuhan Stok (Statistical Forecasting 30/60/90 Hari)
 ├── Executive Support System (ESS) - 7 Laporan Analitik Strategis
 ├── Pusat Notifikasi Real-time & Deep-linking
 └── Pengujian Otomatis Menyeluruh (44+ Feature Test Suites)

Fase 4: Integrasi Lanjutan Masa Depan (Rencana Pengembangan)
 ├── Integrasi REST API langsung ke Core Banking System Bank Jatim
 ├── Integrasi API Kurir Eksternal Real-time (JNE, Pos Indonesia)
 ├── Otomasi Pemindaian Barcode via Aplikasi Mobile Gudang
 └── Penerapan Model Machine Learning Terdistribusi untuk Dynamic Demand Forecasting
```

---

## 12. Persetujuan Dokumen (Sign-off)

| Peran Pemangku Kepentingan | Nama / Jabatan | Tanggal | Tanda Tangan |
| :--- | :--- | :--- | :--- |
| **Product Owner** | Tim Transformasi Digital Logistik Bank Jatim | 12 September 2026 | *Disetujui* |
| **Lead Solution Architect** | Arsitek Sistem Informasi & TI Bank Jatim | 12 September 2026 | *Disetujui* |
| **Head of General & Logistics Division** | Pemimpin Divisi Umum Bank Jatim | 12 September 2026 | *Disetujui* |
| **Head of Procurement Division** | Pemimpin Divisi Pengadaan Bank Jatim | 12 September 2026 | *Disetujui* |
| **Head of Financial Accounting** | Pemimpin Divisi Akuntansi & Keuangan Bank Jatim | 12 September 2026 | *Disetujui* |
| **Head of Internal Audit Division** | Pemimpin Divisi Audit Internal Bank Jatim | 12 September 2026 | *Disetujui* |
