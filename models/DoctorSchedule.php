<?php

namespace app\models;

use yii\db\ActiveRecord;

class DoctorSchedule extends ActiveRecord
{
    public static function tableName() { return '{{%doctor_schedules}}'; }

    public function rules()
    {
        return [
            [['doctor_id', 'polyclinic_id', 'day_of_week', 'start_time', 'end_time'], 'required'],
            [['doctor_id', 'polyclinic_id', 'quota'], 'integer'],
            [['day_of_week'], 'integer', 'min' => 0, 'max' => 6],
            [['start_time', 'end_time'], 'string'],
            [['quota'], 'default', 'value' => 30],
            [['is_active'], 'boolean'],
            [['is_active'], 'default', 'value' => true],
            [['doctor_id', 'polyclinic_id', 'day_of_week', 'start_time'], 'unique',
                'targetAttribute' => ['doctor_id', 'polyclinic_id', 'day_of_week', 'start_time'],
                'message' => 'Jadwal dokter pada hari dan jam ini sudah ada.'],
        ];
    }

    public function getDoctor()
    {
        return $this->hasOne(Doctor::class, ['id' => 'doctor_id']);
    }

    public function getPolyclinic()
    {
        return $this->hasOne(Polyclinic::class, ['id' => 'polyclinic_id']);
    }

    /**
     * See Visit::fields() for why this is needed: Yii2's REST serializer
     * drops a relation unless it's named here, even one already eager-loaded
     * with with() - DoctorScheduleController's index action loads 'doctor'
     * and 'polyclinic' specifically so the schedule screen can show them.
     */
    public function fields()
    {
        $fields = parent::fields();
        $fields['doctor'] = function (self $model) {
            return $model->doctor ? [
                'id' => $model->doctor->id,
                'name' => $model->doctor->name,
                'specialization' => $model->doctor->specialization,
            ] : null;
        };
        $fields['polyclinic'] = function (self $model) {
            return $model->polyclinic ? [
                'id' => $model->polyclinic->id,
                'name' => $model->polyclinic->name,
            ] : null;
        };
        return $fields;
    }
}
