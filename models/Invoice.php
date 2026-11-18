<?php

namespace app\models;

use yii\db\ActiveRecord;

class Invoice extends ActiveRecord
{
    const STATUS_UNPAID = 'unpaid';
    const STATUS_PARTIAL = 'partial';
    const STATUS_PAID = 'paid';

    public static function tableName() { return '{{%invoices}}'; }

    public function rules()
    {
        return [
            [['visit_id'], 'required'],
            [['visit_id'], 'unique'],
            [['visit_id'], 'integer'],
            [['registration_fee', 'procedure_fee', 'lab_fee', 'medicine_fee', 'total_amount', 'paid_amount'], 'number'],
            [['status'], 'in', 'range' => [self::STATUS_UNPAID, self::STATUS_PARTIAL, self::STATUS_PAID]],
            [['status'], 'default', 'value' => self::STATUS_UNPAID],
            [['invoice_number'], 'required'],
            [['invoice_number'], 'unique'],
        ];
    }

    public function getPayments()
    {
        return $this->hasMany(Payment::class, ['invoice_id' => 'id']);
    }

    public function getVisit()
    {
        return $this->hasOne(Visit::class, ['id' => 'visit_id']);
    }
}
