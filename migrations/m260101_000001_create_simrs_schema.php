<?php

use yii\db\Migration;

/**
 * Creates the core SIMRS (Sistem Informasi Manajemen Rumah Sakit) schema.
 *
 * Covers: master data (patients, doctors, polyclinics, schedules, procedures,
 * medicines), the outpatient visit/queue flow, medical records with an audit
 * trail, procedures billed per visit, pharmacy prescriptions with a stock
 * ledger, lab requests/results, and invoices/payments.
 *
 * Concurrency-safety decisions encoded directly in the schema (see also
 * docs/technical-decisions.md):
 *  - visits: UNIQUE(polyclinic_id, visit_date, queue_number) guarantees the
 *    queue number can never be issued twice, even under a race; a partial
 *    unique index also blocks the same patient being registered twice to the
 *    same doctor on the same day.
 *  - medicines.stock has a CHECK (stock >= 0) as a last-resort guard; the
 *    application additionally uses SELECT ... FOR UPDATE on the medicine row
 *    inside a transaction before decrementing stock (see PharmacyService).
 *  - payments.idempotency_key is UNIQUE so a retried payment request (e.g.
 *    after a network blip) can never be recorded twice.
 *  - audit_histories is an append-only log used whenever a medical record,
 *    billed procedure, or invoice is changed after the fact.
 */
class m260101_000001_create_simrs_schema extends Migration
{
    public function safeUp()
    {
        $sql = file_get_contents(__DIR__ . '/sql/schema.sql');
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            $this->db->createCommand($stmt)->execute();
        }
    }

    public function safeDown()
    {
        $tables = [
            'integration_failures', 'payments', 'invoices', 'lab_results', 'lab_requests',
            'medicine_stock_mutations', 'prescription_items', 'prescriptions',
            'visit_procedures', 'audit_histories', 'medical_records', 'visits',
            'medicines', 'medical_procedures', 'patients', 'doctor_schedules',
            'doctors', 'polyclinics', 'users',
        ];
        foreach ($tables as $table) {
            $this->execute("DROP TABLE IF EXISTS {$table} CASCADE");
        }
    }
}
