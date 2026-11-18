<?php

namespace app\models;

use yii\db\ActiveRecord;

class MedicalProcedure extends ActiveRecord
{
    public static function tableName() { return '{{%medical_procedures}}'; }

    public function rules()
    {
        return [
            [['code', 'name', 'tariff'], 'required'],
            [['code'], 'string', 'max' => 20],
            [['code'], 'unique'],
            [['name'], 'string', 'max' => 128],
            [['tariff'], 'number', 'min' => 0],
            [['is_active'], 'boolean'],
            [['is_active'], 'default', 'value' => true],
        ];
    }
}
