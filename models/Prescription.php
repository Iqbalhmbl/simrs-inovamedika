<?php

namespace app\models;

use yii\db\ActiveRecord;

class Prescription extends ActiveRecord
{
    const STATUS_PENDING = 'pending';
    const STATUS_DISPENSED = 'dispensed';
    const STATUS_CANCELLED = 'cancelled';

    public static function tableName() { return '{{%prescriptions}}'; }

    public function rules()
    {
        return [
            [['visit_id', 'doctor_id'], 'required'],
            [['visit_id', 'doctor_id'], 'integer'],
            [['status'], 'in', 'range' => [self::STATUS_PENDING, self::STATUS_DISPENSED, self::STATUS_CANCELLED]],
            [['status'], 'default', 'value' => self::STATUS_PENDING],
        ];
    }

    public function getItems()
    {
        return $this->hasMany(PrescriptionItem::class, ['prescription_id' => 'id']);
    }
}
