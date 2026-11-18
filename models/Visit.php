<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $patient_id
 * @property int $polyclinic_id
 * @property int $doctor_id
 * @property string $visit_date
 * @property int $queue_number
 * @property string $status waiting|examined|completed|cancelled
 */
class Visit extends ActiveRecord
{
    const STATUS_WAITING = 'waiting';
    const STATUS_EXAMINED = 'examined';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    public static function tableName() { return '{{%visits}}'; }

    public function rules()
    {
        return [
            [['patient_id', 'polyclinic_id', 'doctor_id', 'visit_date'], 'required'],
            [['patient_id', 'polyclinic_id', 'doctor_id', 'queue_number', 'registered_by'], 'integer'],
            [['visit_date'], 'date', 'format' => 'php:Y-m-d'],
            [['status'], 'in', 'range' => [
                self::STATUS_WAITING, self::STATUS_EXAMINED, self::STATUS_COMPLETED, self::STATUS_CANCELLED,
            ]],
            [['status'], 'default', 'value' => self::STATUS_WAITING],
        ];
    }

    public function getPatient() { return $this->hasOne(Patient::class, ['id' => 'patient_id']); }
    public function getPolyclinic() { return $this->hasOne(Polyclinic::class, ['id' => 'polyclinic_id']); }
    public function getDoctor() { return $this->hasOne(Doctor::class, ['id' => 'doctor_id']); }
    public function getMedicalRecord() { return $this->hasOne(MedicalRecord::class, ['visit_id' => 'id']); }
    public function getVisitProcedures() { return $this->hasMany(VisitProcedure::class, ['visit_id' => 'id']); }
    public function getPrescriptions() { return $this->hasMany(Prescription::class, ['visit_id' => 'id']); }
    public function getLabRequests() { return $this->hasMany(LabRequest::class, ['visit_id' => 'id']); }
    public function getInvoice() { return $this->hasOne(Invoice::class, ['visit_id' => 'id']); }
}
