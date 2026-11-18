# Entity Relationship Diagram (ERD)

Sumber kebenaran skema ada di `yii2-project/migrations/sql/schema.sql`
(dijalankan lewat migration Yii2 `m260101_000001_create_simrs_schema.php`).
Diagram berikut merangkum entitas dan relasinya.

```
users ───────────────────────┐
  │ (1-1, opsional)           │ role: admin, petugas_pendaftaran, dokter,
  ▼                           │       petugas_farmasi, petugas_laboratorium, kasir
doctors                       │
  │ 1                         │
  │                           │
  ▼ N                         │
doctor_schedules               │
  │ N                          │
  ▼ 1                          │
polyclinics                    │
                                │
patients                       │
  │ 1                          │
  ▼ N                          │
visits ◄──────────────────────┘ (registered_by)
  │ N          │ N            │ N
  │ 1          │ 1            │ 1
  ▼            ▼              ▼
medical_records   visit_procedures   prescriptions
  (1-1 per visit)  │ N              │ 1
                    ▼ 1              ▼ N
              medical_procedures   prescription_items
                                     │ N
                                     ▼ 1
                                  medicines ──► medicine_stock_mutations

visits
  │ 1
  ▼ N
lab_requests
  │ 1
  ▼ 1
lab_results

visits
  │ 1
  ▼ 1
invoices
  │ 1
  ▼ N
payments

audit_histories  (polymorphic: entity_type + entity_id → medical_records / visit_procedures / invoices)
visit_queue_counters (polyclinic_id, visit_date) → atomic queue number source
integration_failures (log only, no FK - deliberately decoupled so logging never fails on a bad reference)
```

## Ringkasan Tabel

| Tabel | Deskripsi |
|---|---|
| `users` | Akun login + role (RBAC) |
| `patients` | Data pasien, No. RM auto-generate (`RM-YYYY-000001`) |
| `doctors` / `doctor_schedules` | Dokter dan jadwal praktik per poli/hari |
| `polyclinics` | Master poli/unit pelayanan |
| `medical_procedures` | Master tindakan medis + tarif |
| `medicines` | Master obat, stok, harga |
| `visit_queue_counters` | Counter atomic untuk nomor antrean per poli/tanggal |
| `visits` | Kunjungan pasien = pendaftaran + antrean |
| `medical_records` | Rekam medis elektronik (1 per kunjungan) |
| `visit_procedures` | Tindakan yang diberikan pada satu kunjungan (snapshot tarif) |
| `prescriptions` / `prescription_items` | Resep obat per kunjungan (snapshot harga) |
| `medicine_stock_mutations` | Ledger perubahan stok obat (audit) |
| `lab_requests` / `lab_results` | Permintaan & hasil pemeriksaan penunjang |
| `invoices` / `payments` | Tagihan dan riwayat pembayaran (idempotent) |
| `audit_histories` | Jejak audit perubahan data setelah dibuat |
| `integration_failures` | Log kegagalan relay Node→Yii untuk rekonsiliasi |

Untuk membuat gambar ERD visual (PNG/SVG), import `schema.sql` ke tool
seperti dbdiagram.io, pgAdmin "ERD Tool", atau DBeaver.
