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

    /**
     * By default, Yii2's REST serializer drops every relation unless it's
     * named here (or requested via ?expand=) - even one the query already
     * eager-loaded with with(). VisitController's index action loads
     * patient/doctor/polyclinic specifically so the queue screen can show
     * them, so they must be listed here too, or they're fetched from the DB
     * and then silently stripped before the JSON response is built - which
     * is exactly why the queue table showed blank Poli/Dokter columns.
     */
    public function fields()
    {
        $fields = parent::fields();
        $fields['patient'] = function (self $model) {
            return $model->patient ? [
                'id' => $model->patient->id,
                'mrn' => $model->patient->mrn,
                'name' => $model->patient->name,
            ] : null;
        };
        $fields['polyclinic'] = function (self $model) {
            return $model->polyclinic ? [
                'id' => $model->polyclinic->id,
                'name' => $model->polyclinic->name,
            ] : null;
        };
        $fields['doctor'] = function (self $model) {
            return $model->doctor ? [
                'id' => $model->doctor->id,
                'name' => $model->doctor->name,
            ] : null;
        };
        return $fields;
    }
}
