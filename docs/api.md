# Dokumentasi Endpoint API

Semua response mengikuti format envelope yang sama pada kedua backend:

```json
// sukses
{ "success": true, "data": { ... } }
// gagal
{ "success": false, "error": { "code": 404, "message": "...", "details": null } }
```

Semua endpoint (kecuali login) butuh header `Authorization: Bearer <JWT>`.

## Backend Yii2 - `http://localhost:8080/api`

### Auth
| Method | Path | Role | Keterangan |
|---|---|---|---|
| POST | `/auth/login` | public | `{username, password}` → `{token, user}` |
| GET | `/auth/me` | any | Identitas user yang sedang login |

### Master Data
| Method | Path | Role (create/update/delete) |
|---|---|---|
| GET/POST/PUT/DELETE | `/patients` | admin, petugas_pendaftaran |
| GET/POST/PUT/DELETE | `/doctors` | admin |
| GET/POST/PUT/DELETE | `/polyclinics` | admin |
| GET/POST/PUT/DELETE | `/doctor-schedules` | admin |
| GET/POST/PUT/DELETE | `/medical-procedures` | admin |
| GET/POST/PUT/DELETE | `/medicines` | admin, petugas_farmasi |
| GET/POST/PUT | `/users` | admin only |

Query params umum: `?search=` (patients: nama/No.RM/NIK), `?page=`,
`?doctor_id=`, `?polyclinic_id=`, `?day_of_week=` (doctor-schedules).

### Pendaftaran & Antrean
| Method | Path | Role | Body |
|---|---|---|---|
| GET | `/visits?date=&polyclinic_id=&doctor_id=&status=` | semua role | - |
| POST | `/visits/register` | admin, petugas_pendaftaran | `{patient_id, doctor_id, polyclinic_id, visit_date}` |
| POST | `/visits/<id>/complete` | admin, dokter | - |

### Pemeriksaan & Rekam Medis
| Method | Path | Role | Body |
|---|---|---|---|
| GET | `/medical-records/by-visit/<visitId>` | admin, dokter, lab, kasir | - |
| POST | `/medical-records` | admin, dokter | `{visit_id, anamnesis, complaint, vital_signs, physical_exam, primary_diagnosis, secondary_diagnosis}` |
| PUT | `/medical-records/<id>` | admin, dokter | sama seperti create (audited otomatis) |

### Tindakan Medis
| Method | Path | Role | Body |
|---|---|---|---|
| POST | `/visit-procedures` | admin, dokter | `{visit_id, medical_procedure_id, qty}` |
| DELETE | `/visit-procedures/<id>` | admin, dokter | void + re-bill |

### Farmasi & Resep
| Method | Path | Role | Body |
|---|---|---|---|
| GET | `/prescriptions?status=pending` | admin, dokter, farmasi, kasir | - |
| POST | `/prescriptions` | admin, dokter | `{visit_id, items:[{medicine_id,dose,frequency,qty}]}` |
| POST | `/prescriptions/<id>/dispense` | admin, petugas_farmasi | - (lihat PharmacyService untuk penanganan stok konkuren) |

### Pemeriksaan Penunjang
| Method | Path | Role | Body |
|---|---|---|---|
| GET | `/lab-requests?status=requested&visit_id=` | admin, dokter, lab | - |
| POST | `/lab-requests` | admin, dokter | `{visit_id, test_name, notes}` |
| POST | `/lab-results` | admin, petugas_laboratorium | `{lab_request_id, result_text, result_data}` |

### Billing & Pembayaran
| Method | Path | Role | Body |
|---|---|---|---|
| GET | `/invoices/by-visit/<visitId>` | admin, kasir, dokter, pendaftaran | - |
| POST | `/invoices/<id>/pay` | admin, kasir | `{amount, method:"cash"\|"non_cash", idempotency_key}` |

## Backend Node.js - `http://localhost:4000/api`

| Method | Path | Role | Keterangan |
|---|---|---|---|
| GET | `/health` | public | Health check |
| GET | `/dashboard/visits-summary?from=&to=` | any authenticated | Total/waiting/completed/cancelled |
| GET | `/dashboard/visits-by-polyclinic?from=&to=` | any authenticated | Kunjungan per poli |
| GET | `/dashboard/procedures-summary?from=&to=` | any authenticated | Jumlah & pendapatan per tindakan |
| GET | `/dashboard/revenue-summary?from=&to=` | any authenticated | Total terkumpul vs outstanding |
| GET | `/dashboard/medicine-usage?from=&to=` | any authenticated | Rekap penggunaan obat |
| GET | `/dashboard/payment-status?from=&to=` | any authenticated | Jumlah invoice per status |
| POST | `/integrations/lab-results` | admin, petugas_laboratorium | `{lab_request_id, result_text, result_data}` - relay ke Yii2, lihat docs/technical-decisions.md D5 |

## Error Codes

| Code | Arti |
|---|---|
| 400 | Request tidak valid (field wajib kosong, dll) |
| 401 | Token tidak ada/invalid/kedaluwarsa |
| 403 | Role tidak memiliki akses (RBAC) |
| 404 | Resource tidak ditemukan |
| 409 | Konflik (duplikasi pendaftaran, stok tidak cukup, dll) |
| 422 | Validasi model gagal (lihat `error.details` untuk detail per-field) |
| 429 | Rate limit terlampaui (Node.js, 120 req/menit) |
| 502 | Layanan backend lain tidak terjangkau (lihat D5) |
