-- SIMRS PostgreSQL Schema
-- PT Inova Medika Solusindo - Real Case Test

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    auth_key VARCHAR(32) NOT NULL,
    access_token VARCHAR(64) UNIQUE,
    full_name VARCHAR(128) NOT NULL,
    role VARCHAR(32) NOT NULL, -- admin, petugas_pendaftaran, dokter, petugas_farmasi, petugas_laboratorium, kasir
    status SMALLINT NOT NULL DEFAULT 10, -- 10 active, 0 inactive
    created_at TIMESTAMP NOT NULL DEFAULT now(),
    updated_at TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE polyclinics (
    id SERIAL PRIMARY KEY,
    code VARCHAR(16) NOT NULL UNIQUE,
    name VARCHAR(128) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE doctors (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    name VARCHAR(128) NOT NULL,
    specialization VARCHAR(128),
    sip_number VARCHAR(64) UNIQUE,
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE doctor_schedules (
    id SERIAL PRIMARY KEY,
    doctor_id INTEGER NOT NULL REFERENCES doctors(id),
    polyclinic_id INTEGER NOT NULL REFERENCES polyclinics(id),
    day_of_week SMALLINT NOT NULL, -- 0=Sunday .. 6=Saturday
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    quota INTEGER NOT NULL DEFAULT 30,
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP NOT NULL DEFAULT now(),
    UNIQUE(doctor_id, polyclinic_id, day_of_week, start_time)
);

CREATE TABLE patients (
    id SERIAL PRIMARY KEY,
    mrn VARCHAR(20) NOT NULL UNIQUE, -- medical record number
    nik VARCHAR(20) UNIQUE, -- national id, may be null but unique when present
    name VARCHAR(128) NOT NULL,
    birth_date DATE NOT NULL,
    gender VARCHAR(1) NOT NULL, -- L/P
    address TEXT,
    phone VARCHAR(20),
    created_at TIMESTAMP NOT NULL DEFAULT now(),
    updated_at TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX idx_patients_name ON patients (lower(name));

CREATE TABLE medical_procedures (
    id SERIAL PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(128) NOT NULL,
    tariff NUMERIC(14,2) NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE medicines (
    id SERIAL PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(128) NOT NULL,
    unit VARCHAR(32) NOT NULL DEFAULT 'pcs',
    stock INTEGER NOT NULL DEFAULT 0 CHECK (stock >= 0),
    price NUMERIC(14,2) NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP NOT NULL DEFAULT now(),
    updated_at TIMESTAMP NOT NULL DEFAULT now()
);

-- Atomic per-polyclinic-per-day queue counter. Using INSERT ... ON CONFLICT DO
-- UPDATE ... RETURNING is race-free in Postgres without needing SELECT ... FOR
-- UPDATE (which cannot be combined with an aggregate like MAX()).
CREATE TABLE visit_queue_counters (
    polyclinic_id INTEGER NOT NULL REFERENCES polyclinics(id),
    visit_date DATE NOT NULL,
    next_number INTEGER NOT NULL DEFAULT 1,
    PRIMARY KEY (polyclinic_id, visit_date)
);

-- Visits / queue. Unique constraint prevents duplicate queue numbers per polyclinic/day.
CREATE TABLE visits (
    id SERIAL PRIMARY KEY,
    patient_id INTEGER NOT NULL REFERENCES patients(id),
    polyclinic_id INTEGER NOT NULL REFERENCES polyclinics(id),
    doctor_id INTEGER NOT NULL REFERENCES doctors(id),
    visit_date DATE NOT NULL,
    queue_number INTEGER NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'waiting', -- waiting, examined, completed, cancelled
    registered_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP NOT NULL DEFAULT now(),
    updated_at TIMESTAMP NOT NULL DEFAULT now(),
    UNIQUE (polyclinic_id, visit_date, queue_number)
);
-- Prevents the *same patient* from being double-registered to the same doctor/poly/day
-- (handles the "two officers register the same patient at the same time" race).
CREATE UNIQUE INDEX uq_visit_patient_per_day ON visits (patient_id, doctor_id, visit_date)
    WHERE status != 'cancelled';
CREATE INDEX idx_visits_date_status ON visits (visit_date, status);

CREATE TABLE medical_records (
    id SERIAL PRIMARY KEY,
    visit_id INTEGER NOT NULL UNIQUE REFERENCES visits(id),
    anamnesis TEXT,
    complaint TEXT,
    vital_signs JSONB,
    physical_exam TEXT,
    primary_diagnosis VARCHAR(255),
    secondary_diagnosis VARCHAR(255),
    examined_by INTEGER REFERENCES users(id),
    version INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT now(),
    updated_at TIMESTAMP NOT NULL DEFAULT now()
);

-- Audit trail: every change to diagnosis/procedures/billing after creation is traceable.
CREATE TABLE audit_histories (
    id SERIAL PRIMARY KEY,
    entity_type VARCHAR(40) NOT NULL, -- medical_record, visit_procedure, invoice
    entity_id INTEGER NOT NULL,
    action VARCHAR(20) NOT NULL, -- create, update, delete
    old_data JSONB,
    new_data JSONB,
    changed_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX idx_audit_entity ON audit_histories (entity_type, entity_id);

CREATE TABLE visit_procedures (
    id SERIAL PRIMARY KEY,
    visit_id INTEGER NOT NULL REFERENCES visits(id),
    medical_procedure_id INTEGER NOT NULL REFERENCES medical_procedures(id),
    tariff_at_time NUMERIC(14,2) NOT NULL,
    qty INTEGER NOT NULL DEFAULT 1,
    is_voided BOOLEAN NOT NULL DEFAULT false,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE prescriptions (
    id SERIAL PRIMARY KEY,
    visit_id INTEGER NOT NULL REFERENCES visits(id),
    doctor_id INTEGER NOT NULL REFERENCES doctors(id),
    status VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending, dispensed, cancelled
    created_at TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE prescription_items (
    id SERIAL PRIMARY KEY,
    prescription_id INTEGER NOT NULL REFERENCES prescriptions(id),
    medicine_id INTEGER NOT NULL REFERENCES medicines(id),
    dose VARCHAR(64) NOT NULL,
    frequency VARCHAR(64) NOT NULL,
    qty INTEGER NOT NULL CHECK (qty > 0),
    price_at_time NUMERIC(14,2) NOT NULL,
    dispensed BOOLEAN NOT NULL DEFAULT false,
    dispensed_at TIMESTAMP,
    dispensed_by INTEGER REFERENCES users(id)
);

-- Stock ledger: every stock change is an append-only row (used with SELECT ... FOR UPDATE on medicines).
CREATE TABLE medicine_stock_mutations (
    id SERIAL PRIMARY KEY,
    medicine_id INTEGER NOT NULL REFERENCES medicines(id),
    change_qty INTEGER NOT NULL, -- negative = dispensed, positive = restock
    reference_type VARCHAR(40) NOT NULL,
    reference_id INTEGER,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE lab_requests (
    id SERIAL PRIMARY KEY,
    visit_id INTEGER NOT NULL REFERENCES visits(id),
    requested_by INTEGER REFERENCES users(id),
    test_name VARCHAR(128) NOT NULL,
    notes TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'requested', -- requested, processed, done
    created_at TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE lab_results (
    id SERIAL PRIMARY KEY,
    lab_request_id INTEGER NOT NULL UNIQUE REFERENCES lab_requests(id),
    result_text TEXT,
    result_data JSONB,
    input_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE invoices (
    id SERIAL PRIMARY KEY,
    visit_id INTEGER NOT NULL UNIQUE REFERENCES visits(id),
    registration_fee NUMERIC(14,2) NOT NULL DEFAULT 0,
    procedure_fee NUMERIC(14,2) NOT NULL DEFAULT 0,
    lab_fee NUMERIC(14,2) NOT NULL DEFAULT 0,
    medicine_fee NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_amount NUMERIC(14,2) NOT NULL DEFAULT 0,
    paid_amount NUMERIC(14,2) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'unpaid', -- unpaid, partial, paid
    invoice_number VARCHAR(32) NOT NULL UNIQUE,
    version INTEGER NOT NULL DEFAULT 1, -- optimistic lock, bumped on recalculation
    created_at TIMESTAMP NOT NULL DEFAULT now(),
    updated_at TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE payments (
    id SERIAL PRIMARY KEY,
    invoice_id INTEGER NOT NULL REFERENCES invoices(id),
    amount NUMERIC(14,2) NOT NULL CHECK (amount > 0),
    method VARCHAR(20) NOT NULL, -- cash, non_cash
    idempotency_key VARCHAR(64) NOT NULL UNIQUE, -- prevents double-recording on retry
    receipt_number VARCHAR(32) NOT NULL UNIQUE,
    paid_at TIMESTAMP NOT NULL DEFAULT now(),
    created_by INTEGER REFERENCES users(id)
);

-- Problem D5 (Gangguan Integrasi): logs a request Node could not relay to
-- Yii (e.g. Yii was down) so it can be reconciled manually if the external
-- caller does not retry on its own.
CREATE TABLE integration_failures (
    id SERIAL PRIMARY KEY,
    source VARCHAR(64) NOT NULL,
    payload JSONB,
    error_message TEXT,
    resolved BOOLEAN NOT NULL DEFAULT false,
    created_at TIMESTAMP NOT NULL DEFAULT now()
);

CREATE INDEX idx_payments_invoice ON payments (invoice_id);
CREATE INDEX idx_prescription_items_prescription ON prescription_items (prescription_id);
CREATE INDEX idx_visit_procedures_visit ON visit_procedures (visit_id);
