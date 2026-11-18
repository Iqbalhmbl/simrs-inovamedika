<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * A medical procedure billed to a visit. Procedures are never hard-deleted
 * once billing may reference them - `is_voided` marks a correction instead,
 * keeping the audit trail (see AuditHistory) intact for problem D4.
 */
class VisitProcedure extends ActiveRecord
{
    public static function tableName() { return '{{%visit_procedures}}'; }

    public function rules()
    {
        return [
            [['visit_id', 'medical_procedure_id'], 'required'],
            [['visit_id', 'medical_procedure_id', 'qty', 'created_by'], 'integer'],
            [['qty'], 'default', 'value' => 1],
            [['tariff_at_time'], 'number'],
            [['is_voided'], 'boolean'],
        ];
    }

    public function getMedicalProcedure()
    {
        return $this->hasOne(MedicalProcedure::class, ['id' => 'medical_procedure_id']);
    }
}
