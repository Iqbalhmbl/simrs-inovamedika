<?php

namespace app\models;

use yii\db\ActiveRecord;

class PrescriptionItem extends ActiveRecord
{
    public static function tableName() { return '{{%prescription_items}}'; }

    public function rules()
    {
        return [
            [['prescription_id', 'medicine_id', 'dose', 'frequency', 'qty'], 'required'],
            [['prescription_id', 'medicine_id', 'qty', 'dispensed_by'], 'integer'],
            [['qty'], 'integer', 'min' => 1],
            [['dose', 'frequency'], 'string', 'max' => 64],
            [['price_at_time'], 'number'],
            [['dispensed'], 'boolean'],
        ];
    }

    public function getMedicine()
    {
        return $this->hasOne(Medicine::class, ['id' => 'medicine_id']);
    }
}
