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

    /**
     * See Visit::fields() - Yii2 drops a relation from JSON unless it's
     * named here, even one already eager-loaded with with('items.medicine').
     * This is why PharmacyPage's item listing and the exam page's
     * prescription history were always empty.
     */
    public function fields()
    {
        $fields = parent::fields();
        $fields['items'] = 'items';
        return $fields;
    }
}
