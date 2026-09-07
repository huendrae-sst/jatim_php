# BANK JATIM INVENTORY MANAGEMENT SYSTEM (JIMS)
## END-TO-END WORKFLOW, FEATURES & USERS
### Dokumen Konsep Operasional & Fungsional JIMS

**Tujuan Dokumen:** Memberikan gambaran menyeluruh mengenai pengguna, fitur, workflow, status transaksi, approval, integrasi, notifikasi, dan kontrol operasional JIMS dari awal hingga akhir.

**Versi:** 1.1 | **Tanggal:** Agustus 2026

---

## 1. Ringkasan Eksekutif

JIMS (*Bank Jatim Inventory Management System*) merupakan platform terintegrasi untuk mengelola siklus persediaan mulai dari master data, anggaran, *Purchase Request* (PR), konsolidasi PR menjadi *Purchase Order* (PO), penerimaan vendor, pengelolaan stok, permintaan barang, *approval*, distribusi, *switching stock*, pengiriman, penerimaan, *settlement*, notifikasi, *reporting*, hingga *forecasting*.

**Prinsip Utama:**
- Satu sumber data persediaan (*Single Source of Truth*)
- Transaksi berbasis *stock ledger*
- *Approval* yang dapat dikonfigurasi (*Configurable Workflow*)
- Keterlacakan *end-to-end* (*Traceability*)
- *Maker-checker*
- *Audit trail* menyeluruh

### 1.1 Tujuan Bisnis
1. Menyediakan visibilitas stok seluruh Kantor Pusat, Cabang, Capem, dan gudang secara terpusat.
2. Mengurangi risiko *stockout*, *overstock*, selisih stok, dan duplikasi permintaan.
3. Mengendalikan pemakaian anggaran melalui pagu, komitmen, realisasi, dan sisa anggaran.
4. Mempercepat proses *request*, *approval*, *fulfillment*, pengiriman, dan penerimaan barang.
5. Mengoptimalkan stok antarunit melalui *Switching Stock*.
6. Meningkatkan akuntabilitas melalui *maker-checker*, *audit trail*, dan histori transaksi.
7. Mendukung keputusan pengadaan melalui *reporting*, *analytical dashboard*, dan *forecasting*.

### 1.2 Gambaran End-to-End

```
[Master Data & Access] ➔ [Budget Planning] ➔ [Purchase Request (PR)] ➔ [PR Approval & Consolidation] ➔ [PO / Contract] ➔ [Vendor Receiving] ➔ [Inventory & Stock Ledger] ➔ [Order / Request] ➔ [Approval] ➔ [Allocation / Switching Stock] ➔ [Picking & Packing] ➔ [Manifest & Shipment] ➔ [Online Tracking] ➔ [Receiving & Discrepancy] ➔ [Settlement] ➔ [Dashboard, Report & Forecasting]
```
*Gambar 1. Alur end-to-end utama JIMS*

---

## 2. Pengguna dan Peran dalam JIMS

| Role / Pengguna | Tanggung Jawab Utama | Scope |
| :--- | :--- | :--- |
| **Super Admin** | Konfigurasi sistem, menu, permission, parameter global | Lintas organisasi sesuai kewenangan |
| **User Administrator** | User, role, role-menu, organisasi user, aktivasi/deaktivasi akses | Administrasi akses |
| **Master Data Maker** | Membuat/mengubah master barang, vendor, organisasi, referensi | Master Data |
| **Master Data Approver** | Approval perubahan master kritis | Master Data |
| **Budget Officer** | Pagu, realisasi, komitmen, monitoring anggaran | Budget |
| **Procurement Officer** | Purchase Request (PR), konsolidasi PR menjadi PO, vendor, kontrak, dan monitoring pengadaan | Procurement |
| **Procurement Approver** | Persetujuan PR dan/atau PO sesuai matriks kewenangan dan kebijakan pengadaan | Procurement |
| **Inventory Officer** | Monitoring stok, stock ledger, adjustment, transfer | Inventory |
| **Warehouse Officer** | Penerimaan, picking, packing, dispatch | Warehouse |
| **Requester Cabang/Capem** | Membuat order/request kebutuhan barang | Unit masing-masing |
| **Order Approver** | Approve/reject/return order sesuai workflow | Unit/nominal tertentu |
| **Switching Stock Approver** | Approval pemenuhan dari unit lain | Unit sumber/tujuan |
| **Distribution Officer** | Manifest, label, shipment, tracking | Distribution |
| **Receiving Officer** | Konfirmasi penerimaan dan discrepancy | Unit penerima |
| **Finance Officer** | Membuat dan memonitor settlement | Finance |
| **Finance Approver** | Approval settlement dan posting | Finance |
| **Auditor** | Read-only transaksi, approval, audit trail, laporan | Lintas unit sesuai scope |
| **Management** | Dashboard, KPI, analitik, monitoring exception | Management scope |
| **IT Operations** | Monitoring integrasi, job, log, health system | Teknis |

### 2.1 Model Hak Akses
- **User** dapat memiliki satu atau beberapa role.
- **Role** menentukan menu dan permission yang dapat diakses.
- **Role-Menu Mapping** menentukan menu apa yang terlihat dan dapat dibuka.
- **Permission** menentukan aksi di dalam menu, seperti *view*, *create*, *edit*, *submit*, *approve*, *reject*, *post*, *cancel*, *export*.
- **Scope organisasi** membatasi data berdasarkan Kantor Pusat, Cabang, Capem, gudang, atau lokasi.
- **Approval limit** dapat membatasi hak persetujuan berdasarkan nilai transaksi.
- **Segregation of Duties** mencegah *maker* menyetujui transaksi yang dibuat sendiri pada proses kritis.

---

## 3. Peta Modul dan Fitur JIMS

| Modul | Fitur Utama |
| :--- | :--- |
| **Dashboard** | Executive dashboard, operational dashboard, KPI, exception monitoring |
| **Master Data** | Organisasi, lokasi, barang, kategori, UOM, vendor, ekspedisi, akun, cost center, SLA, parameter stok |
| **User & Access** | User, role, menu, permission, role-menu mapping, scope organisasi, delegasi |
| **Budget** | Pagu, komitmen, realisasi, sisa anggaran, threshold, realokasi |
| **Procurement** | Purchase Request (PR), budget validation, approval PR, approved PR pool, konsolidasi PR, PO/kontrak, mapping PR-PO, histori harga, vendor performance |
| **Inventory** | Stock balance, stock ledger, reservation, transfer, adjustment, stock opname, damaged/hold stock |
| **Order** | Request barang, approval, status tracking, partial fulfillment, cancellation |
| **Switching Stock** | Rekomendasi sumber stok, approval, reservasi sumber alternatif, transfer |
| **Warehouse** | Goods receipt, picking, packing, verification, dispatch |
| **Distribution** | Manifest, label, shipment, tracking, courier integration |
| **Receiving** | Scan/confirm receipt, discrepancy, POD, claim/return |
| **Settlement** | Debit/kredit antarunit, approval, posting, reconciliation |
| **Notification** | Action Required, Alert, Information, reminder, escalation |
| **Reporting** | Laporan operasional, PDF/Excel, JasperReports |
| **Forecasting** | Stockout prediction, reorder recommendation, seasonal forecast |
| **Audit & Monitoring** | Audit trail, auth log, integration log, job monitoring |

---

## 4. Workflow 0 - Persiapan Sistem dan Master Data

1. **Administrator** membuat struktur menu, permission, role, dan Role-Menu Mapping.
2. **Administrator** membuat atau mengaktifkan user dan menetapkan role serta scope organisasi.
3. **Master Data Maker** menyiapkan organisasi, lokasi/gudang, barang, kategori, UOM, vendor, ekspedisi, cost center, akun, parameter minimum/maximum stock, reorder point, lead time, dan SLA.
4. **Master Data kritis** melalui approval maker-checker sebelum aktif digunakan.
5. **Budget Officer** memasukkan pagu anggaran per periode, unit, dan kategori apabila diperlukan.
6. Setelah master dan akses valid, proses dapat dilanjutkan ke *Purchase Request* (PR) untuk pengadaan maupun *order/request* barang untuk pemenuhan stok internal.

### Kelompok Master Data

| Kelompok Master | Contoh Data |
| :--- | :--- |
| **Organisasi & Lokasi** | Kantor Pusat, Cabang, Capem, Gudang, Cost Center, hierarki unit |
| **Barang** | Item, kategori, subkategori, UOM, spesifikasi, minimum/maximum stock, reorder point, lead time |
| **Vendor & Procurement** | Vendor, kontrak, SLA vendor, metode/tipe pengadaan, tipe PO, dan parameter konsolidasi PR |
| **Ekspedisi** | Courier, service type, SLA, tracking mapping |
| **Budget & Finance** | Budget account, cost center, debit/credit account, periode |
| **Workflow** | Approval matrix, step, nominal limit, escalation |
| **Dokumen** | Document type, numbering format, label/manifest template |
| **User & Access** | User, role, menu, permission, role-menu, scope organisasi |

---

## 5. Workflow 1 - Procurement sampai Stok Tersedia

```
[PR Draft] ➔ [Budget Validation] ➔ [PR Approval] ➔ [Approved PR Pool (PR1, PR2, ...)] ➔ [Consolidate PRs] ➔ [PO / Contract] ➔ [Vendor Delivery] ➔ [Goods Receipt] ➔ [Match PO & PR Trace] ➔ [Stock Posting] ➔ [History & Fulfillment] ➔ [Completed]
```
*Gambar 2. Workflow Purchase Request (PR), konsolidasi PR menjadi PO, dan penerimaan vendor*
*(1 PO = kumpulan 1 atau lebih PR yang sudah Approved; Setiap PO item menyimpan referensi PR item + ordered qty)*

| No | Tahap | Pengguna | Fungsi |
| :---: | :--- | :--- | :--- |
| **1** | **Purchase Request (PR)** | Procurement Officer | Membuat PR berisi kebutuhan pengadaan, item, qty, estimasi harga, sumber anggaran, periode, dan parameter pengadaan. PR belum menjadi komitmen ke vendor. |
| **2** | **Budget Validation** | System / Budget Officer | Memeriksa pagu, komitmen, realisasi, dan sisa anggaran untuk setiap PR sebelum masuk approval. |
| **3** | **PR Approval** | Procurement Approver | Approve, reject, atau return for revision. Hanya PR berstatus Approved yang dapat masuk proses konsolidasi PO. |
| **4** | **Approved PR Pool & Consolidation** | Procurement Officer | Mengelompokkan satu atau lebih PR yang telah disetujui berdasarkan kompatibilitas vendor/metode pengadaan, item, periode, currency, delivery terms, lokasi, dan aturan Bank Jatim. |
| **5** | **PO / Contract** | Procurement Officer | Membentuk satu PO dari satu atau lebih PR yang dipilih. Setiap PO item wajib menyimpan referensi PR item dan quantity yang dikonsolidasikan. |
| **6** | **Vendor Delivery** | Vendor / Procurement | Monitoring acknowledgement, ETA, SLA, dan status pengiriman berdasarkan PO yang telah diterbitkan. |
| **7** | **Goods Receipt** | Warehouse Officer | Mencatat barang diterima, qty, kondisi, batch/serial bila ada, dengan referensi PO. |
| **8** | **Verification & PR Traceability** | Warehouse / Procurement | Membandingkan barang diterima dengan PO serta menelusuri sumber PR. Catat partial receipt, reject, discrepancy, dan outstanding quantity. |
| **9** | **Stock Posting** | System | Menambah stock balance dan membuat stock ledger setelah penerimaan tervalidasi. |
| **10** | **History & Fulfillment** | System | Menyimpan histori harga, vendor, lead time, performa, coverage PR terhadap PO, ordered qty, received qty, dan outstanding qty. |

### 5.1 Aturan Relasi Purchase Request (PR) dan Purchase Order (PO)
- PR merupakan dokumen kebutuhan pengadaan internal dan harus melalui *budget validation* serta *approval* sebelum dapat digunakan sebagai sumber PO.
- PR yang sudah *Approved* masuk ke *Approved PR Pool*. Procurement Officer dapat memilih satu atau lebih PR atau PR item yang kompatibel untuk dikonsolidasikan.
- Satu PO merupakan hasil konsolidasi satu atau lebih PR. PO tidak dibuat sebagai transaksi berdiri sendiri tanpa sumber PR, kecuali terdapat jenis pengadaan khusus yang secara eksplisit dikonfigurasi dan disetujui.
- Setiap PO item wajib dapat ditelusuri ke PR item sumber beserta *allocated/ordered quantity* sehingga tersedia *audit trail* **PR ➔ PO ➔ Receipt ➔ Stock**.
- Quantity PO tidak boleh melebihi sisa quantity PR yang telah disetujui. Perubahan di atas *approved quantity* harus melalui *amendment* atau *approval* baru.
- Status PR diperbarui berdasarkan progres konsolidasi: `APPROVED`, `PARTIALLY_ORDERED`, `FULLY_ORDERED`, dan `CLOSED`. *Partial receipt* pada PO tidak menghilangkan *outstanding quantity* yang belum diterima.

---

## 6. Workflow 2 - Order/Request sampai Settlement

```
[Create Request] ➔ [Budget Check] ➔ [Stock Check] ➔ [Approval] ➔ (Approved?)
    ├── [No] ➔ [Reject / Revision]
    └── [Yes] ➔ [Reserve & Allocate] ➔ (Stock Enough?)
                  ├── [No] ➔ [Switching Stock] ─┐
                  └── [Yes] ────────────────────┴➔ [Picking] ➔ [Packing] ➔ [Manifest & Label] ➔ [Shipment] ➔ [Receiving] ➔ (Match?)
                                                                                                                          ├── [No] ➔ [Discrepancy] ─┐
                                                                                                                          └── [Yes] ────────────────┴➔ [Settlement] ➔ [Completed]
```
*Gambar 3. Workflow fulfillment order end-to-end*

| No | Tahap | Pengguna | Fungsi |
| :---: | :--- | :--- | :--- |
| **1** | **Create Request** | Requester Cabang/Capem | Memilih barang, jumlah, kebutuhan, cost center, dan sumber anggaran. |
| **2** | **Budget Check** | System | Validasi sisa anggaran dan aturan transaksi. |
| **3** | **Stock Check** | System | Mengecek available stock pada lokasi pemenuh utama. |
| **4** | **Submit & Approval** | Requester + Approver | Order masuk approval; dapat approve/reject/return. |
| **5** | **Reservation** | Inventory System | Stok direservasi untuk mencegah double allocation. |
| **6** | **Allocation** | Inventory Officer / System | Menentukan sumber pemenuhan; dapat split fulfillment. |
| **7** | **Switching Stock** | Inventory + Approver | Jika stok utama kurang, sistem merekomendasikan sumber alternatif dan meminta approval. |
| **8** | **Picking** | Warehouse Officer | Mengambil barang sesuai pick list dan memverifikasi jumlah. |
| **9** | **Packing** | Warehouse Officer | Packing, jumlah koli, berat, dan verifikasi akhir. |
| **10** | **Manifest & Label** | Distribution Officer | Sistem menghasilkan manifest, label, QR/barcode. |
| **11** | **Shipment** | Distribution Officer | Serah terima ke ekspedisi, nomor resi, status in-transit. |
| **12** | **Online Tracking** | System / Courier | Sinkronisasi shipment status dari ekspedisi. |
| **13** | **Receiving** | Receiving Officer | Konfirmasi barang diterima dan kondisi fisik. |
| **14** | **Discrepancy** | Receiving / Warehouse | Menangani kurang, lebih, rusak, salah barang, atau partial receive. |
| **15** | **Stock Posting** | System | Menambah stok penerima, mengurangi in-transit, dan menutup ledger pengiriman. |
| **16** | **Settlement** | Finance | Membuat debit/kredit antarunit, approval, dan posting. |
| **17** | **Close Order** | System | Order selesai setelah fulfillment, receiving, dan settlement memenuhi syarat. |

---

## 7. Status Transaksi Utama

| Transaksi | Happy Path | Exception Status |
| :--- | :--- | :--- |
| **Order** | `DRAFT` ➔ `SUBMITTED` ➔ `WAITING_APPROVAL` ➔ `APPROVED` ➔ `ALLOCATED` ➔ `PICKING` ➔ `PACKING` ➔ `READY_TO_SHIP` ➔ `IN_TRANSIT` ➔ `RECEIVED` ➔ `COMPLETED` | `REJECTED`, `RETURNED_FOR_REVISION`, `PARTIAL`, `CANCELLED` |
| **Purchase Request (PR)** | `DRAFT` ➔ `SUBMITTED` ➔ `BUDGET_VALIDATED` ➔ `WAITING_APPROVAL` ➔ `APPROVED` ➔ `PARTIALLY_ORDERED` ➔ `FULLY_ORDERED` ➔ `CLOSED` | `REJECTED`, `RETURNED_FOR_REVISION`, `CANCELLED`, `AMENDED` |
| **Purchase Order (PO)** | `DRAFT` ➔ `GENERATED_FROM_PR` ➔ `ISSUED` ➔ `VENDOR_PROCESS` ➔ `IN_DELIVERY` ➔ `PARTIAL_RECEIVED` ➔ `RECEIVED` ➔ `COMPLETED` | `CANCELLED`, `REVISED`, `DELIVERY_EXCEPTION` |
| **Shipment** | `CREATED` ➔ `READY_TO_SHIP` ➔ `DISPATCHED` ➔ `IN_TRANSIT` ➔ `OUT_FOR_DELIVERY` ➔ `DELIVERED` ➔ `CLOSED` | `DELIVERY_FAILED`, `RETURNED`, `PARTIAL_RECEIVED` |
| **Switching Stock** | `PROPOSED` ➔ `WAITING_APPROVAL` ➔ `APPROVED` ➔ `RESERVED` ➔ `TRANSFERRED` ➔ `RECEIVED` ➔ `COMPLETED` | `REJECTED`, `CANCELLED` |
| **Settlement** | `DRAFT` ➔ `VALIDATED` ➔ `WAITING_APPROVAL` ➔ `APPROVED` ➔ `POSTING` ➔ `POSTED` ➔ `COMPLETED` | `REJECTED`, `POSTING_FAILED`, `REVERSED` |

---

## 8. Mekanisme Inventory dan Stock Ledger

> **Sumber Kebenaran Stok:** Setiap perubahan stok harus berasal dari transaksi yang sah. Saldo stok tidak boleh diedit langsung tanpa adjustment yang tercatat dan, untuk kondisi tertentu, membutuhkan approval.

| Komponen | Definisi |
| :--- | :--- |
| **On Hand** | Total stok fisik yang tercatat di lokasi. |
| **Reserved** | Stok yang sudah dicadangkan untuk order tertentu. |
| **Allocated** | Stok yang telah dialokasikan ke proses fulfillment. |
| **In Transit** | Barang yang sudah keluar dari sumber tetapi belum diterima tujuan. |
| **Hold** | Barang ditahan dan tidak dapat digunakan. |
| **Damaged** | Barang rusak yang tidak dapat digunakan. |
| **Available** | `On Hand - Reserved - Hold - Damaged` |

### 8.1 Jenis Stock Transaction
1. Vendor Receipt / Procurement Receipt
2. Order Issue / Goods Issue
3. Transfer Out / Transfer In
4. Switching Stock
5. Goods Receipt Unit
6. Stock Adjustment
7. Stock Opname Adjustment
8. Damaged / Hold / Release
9. Return
10. Reversal / Cancellation

---

## 9. Workflow Anggaran

1. **Budget Officer** menetapkan pagu berdasarkan periode dan unit/cost center.
2. Saat procurement atau order disubmit, sistem memeriksa **Available Budget**.
3. Transaksi yang telah disetujui dapat membentuk **Budget Commitment**.
4. Setelah transaksi direalisasikan/settlement, commitment dikonversi menjadi realisasi sesuai aturan keuangan.
5. Sistem mengirim alert ketika penggunaan anggaran mencapai threshold yang ditetapkan.
6. Dashboard menampilkan pagu, commitment, realisasi, available budget, dan utilization rate.

$$	ext{Available Budget} = 	ext{Pagu} - 	ext{Komitmen} - 	ext{Realisasi}$$

---

## 10. Workflow Switching Stock

1. Sistem mendeteksi bahwa stok sumber utama tidak cukup.
2. Sistem mencari lokasi alternatif dengan stok tersedia di atas minimum/safety stock.
3. Rekomendasi mempertimbangkan kuantitas, jarak, lead time, biaya distribusi, prioritas wilayah, dan overstock.
4. Inventory Officer memilih sumber yang sesuai dan mengajukan Switching Stock.
5. Approver unit sumber/otoritas terkait menyetujui atau menolak.
6. Jika disetujui, stok sumber direservasi dan dibuat transfer/shipment.
7. Setelah diterima, stok unit tujuan bertambah dan proses order berlanjut.
8. Jika memengaruhi pembebanan unit, settlement diproses sesuai aturan.

---

## 11. Workflow Manifest, Shipment, Tracking, dan Receiving

| Objek | Informasi Utama |
| :--- | :--- |
| **Manifest** | Nomor manifest, order, asal, tujuan, ekspedisi, item, qty, koli, berat, tanggal kirim. |
| **Label** | Nomor manifest, unit tujuan, nomor koli, QR/barcode, instruksi khusus. |
| **Shipment** | Nomor resi, courier service, ETA, status internal dan eksternal. |
| **Tracking** | Shipment Created, Picked Up, In Transit, Hub, Out for Delivery, Delivered, Failed, Returned. |
| **Receiving** | Qty dikirim vs diterima, kondisi, penerima, waktu, POD/foto, catatan. |
| **Discrepancy** | Kurang, lebih, rusak, salah barang, partial receive, ditolak. |

---

## 12. Workflow Settlement

1. Settlement dibuat berdasarkan transaksi yang telah memenuhi kriteria, misalnya barang diterima atau switching stock selesai.
2. Sistem menentukan unit debit, unit kredit, akun, cost center, nilai barang, biaya distribusi, dan pajak bila berlaku.
3. Finance Officer melakukan validasi data dan dokumen sumber.
4. Settlement melalui approval sesuai batas kewenangan.
5. Settlement yang disetujui dikirim ke sistem finansial atau diposting melalui mekanisme integrasi yang disepakati.
6. Kegagalan posting masuk retry/reconciliation dan menghasilkan notifikasi kritis.
7. Transaksi yang sudah diposting tidak dihapus; koreksi menggunakan reversal.

---

## 13. Notifikasi dan Operational Task Center

```
[Business Event (NestJS Module)] ──┬➔ [PostgreSQL]
                                    └➔ [Domain Event] ➔ [RabbitMQ] ➔ [Notification Worker] ──┬➔ [Resolve Recipient (Role + Unit + Workflow)] ➔ [notifications DB] ➔ [React Notification Center]
                                                                                             └➔ [Email Gateway]
```
*Gambar 4. Alur teknis notifikasi berbasis event*

| Tipe | Prioritas | Contoh | Tujuan |
| :--- | :--- | :--- | :--- |
| **ACTION REQUIRED** | HIGH / CRITICAL | Approval, revisi, receiving, discrepancy, master change | User harus melakukan tindakan. |
| **ALERT** | WARNING / HIGH / CRITICAL | Minimum stock, stockout, overstock, SLA, budget threshold, integration failed | Kondisi yang perlu perhatian. |
| **INFORMATION** | INFO | Approved, shipped, delivered, completed | Informasi perubahan status. |

- Notifikasi memiliki *reference transaction* dan *action URL* agar user langsung menuju transaksi terkait.
- Anti-spam diterapkan untuk threshold seperti minimum stock: notifikasi dikirim saat *state* berubah, bukan setiap transaksi.
- Reminder dan escalation dapat berjalan berdasarkan SLA.
- Notifikasi kritis tertentu tidak dapat dinonaktifkan oleh user.
- Channel awal: In-App dan Email; channel lain dapat dikembangkan sesuai kebijakan.

---

## 14. Reporting, Dashboard, dan Forecasting

| Area | Output |
| :--- | :--- |
| **Dashboard Manajemen** | Nilai stok, penggunaan anggaran, stockout, overstock, order SLA, vendor/courier performance. |
| **Dashboard Operasional** | Order pending, picking, packing, ready to ship, receiving pending, exception. |
| **Operational Report** | Stock card, stock position, goods movement, procurement, shipment, settlement, audit trail. |
| **Forecasting** | Prediksi kebutuhan, stockout prediction, reorder recommendation, seasonal trend. |
| **Export** | Excel dan PDF sesuai format/otorisasi. |

> **Aturan Forecasting:** Forecasting menghasilkan rekomendasi. Pembuatan Purchase Request (PR) atau transfer tetap membutuhkan tindakan dan approval manusia.

---

## 15. Integrasi dan Tech Stack

| Layer | Teknologi |
| :--- | :--- |
| **Frontend** | Laravel + Livewire 4 |
| **UI Components** | shadcn/ui for Laravel |
| **Backend** | Laravel 13 |
| **Database** | PostgreSQL |
| **Language** | PHP 8.4/8.5 |
| **Authentication** | Laravel Fortify + session auth |
| **Authorization** | Laravel Policies / Gates |
| **Cache** | Redis |
| **Async Processing** | Laravel Queues |
| **File Storage** | MinIO |
| **Reporting** | JasperReports |
| **Dashboard** | Livewire + Chart.js / ApexCharts |
| **Forecasting** | Python + FastAPI + Statsmodels |

### 15.1 Integrasi Eksternal/Internal
- SSO / Directory Service Bank Jatim.
- Sistem keuangan untuk settlement / journal posting.
- Ekspedisi untuk shipment creation dan online tracking.
- Email / notification gateway.
- Document management atau storage enterprise bila diperlukan.
- Monitoring / SIEM sesuai arsitektur TI Bank Jatim.

---

## 16. Keamanan, Approval, dan Audit Trail

| Kontrol | Implementasi |
| :--- | :--- |
| **Authentication** | SSO/credential policy, session management, lockout, optional MFA. |
| **Authorization** | Role, menu, permission, organization scope, transaction scope, approval limit. |
| **Maker-Checker** | Pemisahan pembuat dan penyetuju untuk transaksi kritis. |
| **Audit Trail** | Who, what, when, before, after, transaction, IP/device metadata sesuai kebijakan. |
| **Data Integrity** | Transaction boundary, concurrency control, idempotency, reversal. |
| **File Security** | MIME validation, checksum, authorization, malware scanning bila tersedia. |
| **Integration Security** | Authentication, encryption, retry, logging, reconciliation. |

---

## 17. Contoh Skenario End-to-End

### 17.1 Skenario A - Konsolidasi Beberapa PR menjadi Satu PO
1. `PR-001` mengajukan kebutuhan 100 unit Toner A dan `PR-002` mengajukan kebutuhan 150 unit Toner A.
2. Masing-masing PR melalui *budget validation* dan *approval*. Setelah disetujui, keduanya masuk *Approved PR Pool*.
3. Procurement Officer memilih `PR-001` dan `PR-002` karena kompatibel untuk vendor, metode pengadaan, periode, dan *delivery terms* yang sama.
4. Sistem mengkonsolidasikan total kebutuhan 250 unit dan membentuk `PO-001` dengan referensi ke kedua PR.
5. PO item menyimpan mapping: `PR-001 = 100 unit` dan `PR-002 = 150 unit` sehingga quantity tetap dapat ditelusuri per PR.
6. Setelah PO diterbitkan, `PR-001` dan `PR-002` berubah menjadi `FULLY_ORDERED`; jika hanya sebagian quantity dimasukkan, status menjadi `PARTIALLY_ORDERED`.
7. Vendor mengirim barang berdasarkan `PO-001`. *Goods receipt* dapat dilakukan penuh atau sebagian.
8. Sistem mencatat *received quantity* pada PO dan tetap mempertahankan *outstanding quantity* sampai seluruh barang diterima atau ditutup sesuai kebijakan.
9. Setelah verifikasi, stok diposting ke *stock ledger* dan histori `PR -> PO -> Receipt -> Stock` tersedia untuk audit.

### 17.2 Skenario B - Order Dipenuhi dari Gudang Utama
1. Capem membuat order 50 rim kertas.
2. Sistem memvalidasi anggaran dan stok.
3. Approver menyetujui order.
4. Sistem mereservasi 50 rim dari gudang utama.
5. Gudang melakukan picking dan packing.
6. Manifest dan label dibuat otomatis.
7. Barang diserahkan ke ekspedisi dan nomor resi dicatat.
8. Capem memonitor tracking.
9. Capem menerima 50 rim dalam kondisi sesuai.
10. Stok Capem bertambah, in-transit ditutup, settlement dibuat dan diposting.
11. Order berubah menjadi `Completed`.

### 17.3 Skenario C - Switching Stock
1. Cabang A meminta 100 unit barang.
2. Gudang utama hanya memiliki 40 unit available.
3. Sistem menemukan Cabang B memiliki 80 unit excess stock.
4. Sistem merekomendasikan pemenuhan 40 unit dari gudang utama dan 60 unit dari Cabang B.
5. Switching Stock diajukan dan disetujui oleh pihak berwenang.
6. Dua allocation/shipment dapat dibuat untuk order yang sama.
7. Cabang A menerima barang dan sistem melakukan reconciliation quantity.
8. Settlement dilakukan sesuai pembebanan antarunit.
9. Order selesai setelah seluruh quantity terpenuhi atau user menerima partial fulfillment sesuai kebijakan.

### 17.4 Skenario D - Penerimaan Tidak Sesuai
1. Shipment mengirim 100 unit.
2. Unit tujuan menerima 98 unit baik dan 2 unit rusak/kurang.
3. Receiving Officer mencatat discrepancy dan bukti pendukung.
4. Sistem menambah stok hanya sebesar quantity yang diterima sesuai aturan.
5. Warehouse/Distribution menerima notifikasi discrepancy.
6. Proses claim, return, atau pengiriman susulan dilakukan.
7. Settlement menyesuaikan quantity/value yang telah tervalidasi.

---

## 18. Matriks Tanggung Jawab per Tahap

| Tahap | Pelaksana Utama | Approver / Kontrol | Informasi ke |
| :--- | :--- | :--- | :--- |
| **Master & Access** | User Admin / Master Maker | Master Approver / IT | Management / Auditor |
| **Budget** | Budget Officer | Budget Approver / Management | Requester / Procurement |
| **PR & PO Procurement** | Procurement Officer | Procurement Approver | Budget / Warehouse / Vendor terkait |
| **Order** | Requester | Order Approver | Inventory / Warehouse |
| **Allocation** | Inventory Officer | Switching Approver jika perlu | Requester |
| **Picking / Packing** | Warehouse Officer | Warehouse Supervisor | Distribution |
| **Shipment** | Distribution Officer | Warehouse / Distribution Supervisor | Requester / Receiver |
| **Receiving** | Receiving Officer | Unit Supervisor jika discrepancy | Warehouse / Requester |
| **Settlement** | Finance Officer | Finance Approver | Unit terkait / Auditor |
| **Reporting** | System / Data Owner | - | Management / Auditor |

---

## 19. Acceptance Criteria Tingkat Sistem

- [x] Setiap perubahan stok memiliki *stock transaction / ledger* yang dapat ditelusuri.
- [x] User hanya melihat menu, aksi, dan data sesuai *role*, *permission*, serta *scope* organisasi.
- [x] Transaksi kritis mengikuti *maker-checker* sesuai konfigurasi.
- [x] Order dapat dilacak dari *request* sampai *receiving* dan *settlement*.
- [x] Sistem mendukung *partial fulfillment*, *split allocation*, dan *switching stock*.
- [x] Receiving dapat membandingkan *request*, *allocated*, *shipped*, dan *received quantity*.
- [x] Notifikasi memiliki *recipient*, *priority*, *reference transaction*, *read status*, dan *action URL*.
- [x] Audit trail menyimpan aktivitas penting tanpa dapat dihapus oleh user biasa.
- [x] Integrasi memiliki *retry*, *error log*, dan *reconciliation* untuk transaksi penting.
- [x] Dashboard dan laporan menggunakan data yang konsisten dengan transaksi sumber.
- [x] Forecasting tidak mengeksekusi pengadaan/transfer tanpa persetujuan user berwenang.
- [x] PO hanya dapat dibentuk dari PR yang telah disetujui sesuai workflow.
- [x] Satu PO dapat mengkonsolidasikan satu atau lebih PR dan setiap PO item dapat ditelusuri ke PR item sumber.
- [x] Sistem mencegah *ordered quantity* PO melebihi *remaining approved quantity* pada PR tanpa amendment/approval yang sah.
- [x] Status PR menunjukkan progres konsolidasi ke PO, termasuk `PARTIALLY_ORDERED` dan `FULLY_ORDERED`.

---

## 20. Kesimpulan

JIMS dirancang sebagai platform operasional persediaan *end-to-end*, bukan hanya aplikasi pencatatan stok [cite: 1]. Seluruh proses dari *Purchase Request* (PR), konsolidasi PR menjadi *Purchase Order* (PO), penerimaan vendor, stok, request, approval, switching stock, warehouse, distribusi, receiving, settlement, notifikasi, reporting, dan forecasting terhubung melalui transaksi yang dapat ditelusuri dan dikendalikan berdasarkan role serta organisasi [cite: 1].

**Rekomendasi Implementasi:**  
Fokus awal pada fondasi master data, akses, stock ledger, order, approval, warehouse, dan distribusi [cite: 1]. Procurement, settlement, integrasi ekspedisi, analytical reporting, dan forecasting dapat dikembangkan secara bertahap tanpa mengubah prinsip data dan workflow utama [cite: 1].