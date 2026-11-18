# Test Case - Fitur Utama

## Otomatis

| Suite | Lokasi | Cakupan | Status |
|---|---|---|---|
| Node.js integration tests | `services/node/test/server.test.js` | Health check, auth 401, RBAC 403, dashboard shape, 404 envelope | ✅ **Dijalankan**, 5/5 pass (lihat output di bawah) |
| Yii2 unit tests (RegistrationService) | `yii2-project/tests/Unit/services/RegistrationServiceTest.php` | D1: duplikasi pendaftaran ditolak, nomor antrean sequential & gap-free | Ditulis, siap dijalankan dengan `codecept run` di lingkungan dengan vendor/ lengkap |
| Yii2 unit tests (PharmacyService) | `yii2-project/tests/Unit/services/PharmacyServiceTest.php` | D2: dispense gagal jika stok kurang & stok tidak berubah; dispense sukses mengurangi stok tepat sekali | sda |
| Yii2 unit tests (BillingService) | `yii2-project/tests/Unit/services/BillingServiceTest.php` | D3: retry dengan idempotency key sama tidak dobel-charge; overpay ditolak | sda |

Output aktual menjalankan `npm test` pada `services/node`:
```
# tests 5
# pass 5
# fail 0
```

Selain itu, pendekatan konkurensi inti (counter antrean, lock stok, idempotent
payment) sudah diverifikasi langsung dengan transaksi SQL konkuren terhadap
PostgreSQL asli selama pengembangan - lihat `docs/technical-decisions.md`
untuk hasil tiap verifikasi.

## Manual - Skenario End-to-End (Pendaftaran s.d. Pembayaran)

| # | Langkah | Hasil yang Diharapkan |
|---|---|---|
| 1 | Login sebagai `pendaftaran1` | Masuk, menu Pendaftaran & Antrean tampil |
| 2 | Cari pasien "budi" - tidak ketemu → daftarkan pasien baru | Pasien baru dibuat dengan No. RM otomatis `RM-2026-xxxxxx` |
| 3 | Pilih poli + dokter sesuai jadwal hari ini, daftarkan kunjungan | Muncul nomor antrean; mencoba daftar ulang pasien+dokter+tanggal yang sama → ditolak (409) |
| 4 | Logout, login sebagai `dokter1` | Menu Antrean & Kunjungan tampil, kunjungan di atas berstatus `waiting` |
| 5 | Buka Pemeriksaan untuk kunjungan tsb, isi anamnesis/vital/diagnosis, simpan | Status kunjungan berubah jadi `examined` |
| 6 | Tambahkan tindakan medis (mis. "Konsultasi Dokter Umum") | Invoice otomatis dibuat/diperbarui dengan procedure_fee bertambah |
| 7 | Buat resep (obat dengan stok cukup) | Resep tersimpan status `pending` |
| 8 | Minta pemeriksaan lab "Darah Lengkap" | Permintaan muncul di antrean Laboratorium |
| 9 | Logout, login sebagai `farmasi1`, proses resep dari langkah 7 | Stok obat berkurang sesuai qty; status resep jadi `dispensed`; invoice medicine_fee bertambah |
| 10 | Logout, login sebagai `lab1`, input hasil lab dari langkah 8 | Hasil tersimpan; invoice lab_fee bertambah |
| 11 | Logout, login sebagai `kasir1`, buka Billing untuk kunjungan tsb | Rincian biaya (registrasi+tindakan+lab+obat) dan total sesuai akumulasi langkah 6/9/10 |
| 12 | Bayar sebagian (kurang dari total) | Status invoice jadi `partial`; sisa tagihan berkurang |
| 13 | Bayar sisanya | Status invoice jadi `paid`; muncul di riwayat pembayaran dengan no. struk unik |
| 14 | Cetak invoice | Dialog cetak browser muncul dengan rincian lengkap |
| 15 | Login sebagai `dokter1` atau `admin`, buka Dashboard | Angka kunjungan, pendapatan, dan status pembayaran mencerminkan transaksi di atas |

## Manual - Skenario Konkurensi (D1-D3)

| # | Skenario | Cara Uji | Hasil yang Diharapkan |
|---|---|---|---|
| 1 | D1 | Kirim 2 request `POST /visits/register` dengan `patient_id`+`doctor_id`+`visit_date` identik hampir bersamaan (mis. 2 tab Postman "Send" cepat berurutan) | Satu berhasil (201), satu lagi 409 "Pasien sudah terdaftar..." |
| 2 | D2 | Set stok obat = 1 lewat `/medicines`, buat 2 resep berbeda dengan obat yang sama qty=1, dispense keduanya nyaris bersamaan | Satu berhasil, satu lagi 409 "Stok obat tidak mencukupi..." |
| 3 | D3 | `POST /invoices/<id>/pay` dengan `idempotency_key` yang sama 2x berturut-turut | Response kedua identik dengan yang pertama (payment yang sama), `paid_amount` di invoice hanya bertambah sekali |

## Keamanan / RBAC

| # | Skenario | Hasil yang Diharapkan |
|---|---|---|
| 1 | Login sebagai `kasir1`, coba `POST /patients` | 403 Forbidden |
| 2 | Login sebagai `petugas_farmasi`, coba `POST /visits/register` | 403 Forbidden |
| 3 | Request tanpa header Authorization ke endpoint mana pun (selain login/health) | 401 Unauthorized |
| 4 | Token kedaluwarsa/dipalsukan | 401 Unauthorized |
