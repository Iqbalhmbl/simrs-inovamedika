<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Append-only ledger of every stock change. Not strictly required to
 * compute the current stock (that's `medicines.stock`), but gives a full,
 * auditable history of why stock moved - useful for reconciliation and for
 * diagnosing the "stock went negative" class of bug if it ever recurs.
 */
class MedicineStockMutation extends ActiveRecord
{
    public static function tableName() { return '{{%medicine_stock_mutations}}'; }

    public function rules()
    {
        return [
            [['medicine_id', 'change_qty', 'reference_type'], 'required'],
            [['medicine_id', 'change_qty', 'reference_id', 'created_by'], 'integer'],
            [['reference_type'], 'string', 'max' => 40],
        ];
    }
}
