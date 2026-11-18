<?php

use yii\db\Migration;

/**
 * Dummy data for testing: 1 user per role, polyclinics, doctors + schedules,
 * medical procedures with tariffs, and medicines with stock.
 * Password for every seeded user is "password123".
 */
class m260101_000003_seed_dummy_data extends Migration
{
    public function safeUp()
    {
        $hash = Yii::$app->security->generatePasswordHash('password123');
        $authKey = Yii::$app->security->generateRandomString();

        $users = [
            ['admin', 'Administrator', 'admin'],
            ['pendaftaran1', 'Siti Petugas Pendaftaran', 'petugas_pendaftaran'],
            ['dokter1', 'dr. Andi Wijaya', 'dokter'],
            ['dokter2', 'dr. Maya Putri', 'dokter'],
            ['farmasi1', 'Budi Petugas Farmasi', 'petugas_farmasi'],
            ['lab1', 'Rina Petugas Laboratorium', 'petugas_laboratorium'],
            ['kasir1', 'Joko Kasir', 'kasir'],
        ];
        foreach ($users as [$username, $fullName, $role]) {
            $this->insert('users', [
                'username' => $username,
                'password_hash' => $hash,
                'auth_key' => $authKey,
                'full_name' => $fullName,
                'role' => $role,
            ]);
            $userId = $this->db->getLastInsertID('users_id_seq');
            Yii::$app->authManager->assign(
                Yii::$app->authManager->getRole($role),
                $userId
            );
        }

        $this->batchInsert('polyclinics', ['code', 'name'], [
            ['UMUM', 'Poli Umum'],
            ['ANAK', 'Poli Anak'],
            ['GIGI', 'Poli Gigi'],
            ['JANTUNG', 'Poli Jantung'],
        ]);

        $dokter1UserId = (new \yii\db\Query())->select('id')->from('users')->where(['username' => 'dokter1'])->scalar($this->db);
        $dokter2UserId = (new \yii\db\Query())->select('id')->from('users')->where(['username' => 'dokter2'])->scalar($this->db);

        $this->insert('doctors', [
            'user_id' => $dokter1UserId, 'name' => 'dr. Andi Wijaya', 'specialization' => 'Umum', 'sip_number' => 'SIP-0001',
        ]);
        $doctor1 = $this->db->getLastInsertID('doctors_id_seq');

        $this->insert('doctors', [
            'user_id' => $dokter2UserId, 'name' => 'dr. Maya Putri', 'specialization' => 'Anak', 'sip_number' => 'SIP-0002',
        ]);
        $doctor2 = $this->db->getLastInsertID('doctors_id_seq');

        $polyUmum = (new \yii\db\Query())->select('id')->from('polyclinics')->where(['code' => 'UMUM'])->scalar($this->db);
        $polyAnak = (new \yii\db\Query())->select('id')->from('polyclinics')->where(['code' => 'ANAK'])->scalar($this->db);

        for ($day = 1; $day <= 5; $day++) {
            $this->insert('doctor_schedules', [
                'doctor_id' => $doctor1, 'polyclinic_id' => $polyUmum, 'day_of_week' => $day,
                'start_time' => '08:00:00', 'end_time' => '14:00:00', 'quota' => 30,
            ]);
            $this->insert('doctor_schedules', [
                'doctor_id' => $doctor2, 'polyclinic_id' => $polyAnak, 'day_of_week' => $day,
                'start_time' => '09:00:00', 'end_time' => '15:00:00', 'quota' => 20,
            ]);
        }

        $this->batchInsert('medical_procedures', ['code', 'name', 'tariff'], [
            ['TIND-001', 'Konsultasi Dokter Umum', 75000],
            ['TIND-002', 'Konsultasi Dokter Spesialis', 150000],
            ['TIND-003', 'Nebulizer', 50000],
            ['TIND-004', 'Jahit Luka (per 5 jahitan)', 120000],
            ['TIND-005', 'Pemeriksaan EKG', 100000],
        ]);

        $this->batchInsert('medicines', ['code', 'name', 'unit', 'stock', 'price'], [
            ['OBT-001', 'Paracetamol 500mg', 'tablet', 500, 500],
            ['OBT-002', 'Amoxicillin 500mg', 'tablet', 300, 1500],
            ['OBT-003', 'Vitamin C 500mg', 'tablet', 1000, 300],
            ['OBT-004', 'Cetirizine 10mg', 'tablet', 200, 800],
            ['OBT-005', 'Obat Batuk Sirup', 'botol', 50, 15000],
        ]);
    }

    public function safeDown()
    {
        $this->execute('TRUNCATE TABLE medicines, medical_procedures, doctor_schedules, doctors, polyclinics, users RESTART IDENTITY CASCADE');
    }
}
