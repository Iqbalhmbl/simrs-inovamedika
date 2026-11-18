<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property string $idempotency_key client-generated UUID, one per payment
 *           attempt. The same key resubmitted after a network error is safe:
 *           see BillingService::pay().
 */
class Payment extends ActiveRecord
{
    const METHOD_CASH = 'cash';
    const METHOD_NON_CASH = 'non_cash';

    public static function tableName() { return '{{%payments}}'; }

    public function rules()
    {
        return [
            [['invoice_id', 'amount', 'method', 'idempotency_key'], 'required'],
            [['invoice_id', 'created_by'], 'integer'],
            [['amount'], 'number', 'min' => 0.01],
            [['method'], 'in', 'range' => [self::METHOD_CASH, self::METHOD_NON_CASH]],
            [['idempotency_key'], 'string', 'max' => 64],
            [['idempotency_key'], 'unique'],
            [['receipt_number'], 'unique'],
        ];
    }
}
