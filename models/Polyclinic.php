<?php

namespace app\models;

use yii\db\ActiveRecord;

class Polyclinic extends ActiveRecord
{
    public static function tableName() { return '{{%polyclinics}}'; }

    public function rules()
    {
        return [
            [['code', 'name'], 'required'],
            [['code'], 'string', 'max' => 16],
            [['code'], 'unique'],
            [['name'], 'string', 'max' => 128],
            [['is_active'], 'boolean'],
            [['is_active'], 'default', 'value' => true],
        ];
    }
}
