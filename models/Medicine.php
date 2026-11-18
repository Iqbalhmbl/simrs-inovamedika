<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $unit
 * @property int $stock
 * @property float $price
 */
class Medicine extends ActiveRecord
{
    public static function tableName() { return '{{%medicines}}'; }

    public function rules()
    {
        return [
            [['code', 'name', 'price'], 'required'],
            [['code'], 'string', 'max' => 20],
            [['code'], 'unique'],
            [['name'], 'string', 'max' => 128],
            [['unit'], 'string', 'max' => 32],
            [['unit'], 'default', 'value' => 'pcs'],
            [['stock'], 'integer', 'min' => 0],
            [['stock'], 'default', 'value' => 0],
            [['price'], 'number', 'min' => 0],
            [['is_active'], 'boolean'],
            [['is_active'], 'default', 'value' => true],
        ];
    }
}
