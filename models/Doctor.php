<?php

namespace app\models;

use yii\db\ActiveRecord;

class Doctor extends ActiveRecord
{
    public static function tableName() { return '{{%doctors}}'; }

    public function rules()
    {
        return [
            [['name'], 'required'],
            [['name', 'specialization'], 'string', 'max' => 128],
            [['sip_number'], 'string', 'max' => 64],
            [['sip_number'], 'unique'],
            [['user_id'], 'integer'],
            [['is_active'], 'boolean'],
            [['is_active'], 'default', 'value' => true],
        ];
    }

    public function getSchedules()
    {
        return $this->hasMany(DoctorSchedule::class, ['doctor_id' => 'id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
