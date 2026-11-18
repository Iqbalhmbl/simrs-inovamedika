<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Append-only audit log. Used whenever a medical record, a billed procedure,
 * or an invoice is changed after creation (problem D4: dokter mengubah
 * diagnosis/tindakan setelah billing dibuat). Every write to those entities
 * goes through AuditService::record() rather than touching this table
 * directly, so the trail can never be bypassed accidentally.
 */
class AuditHistory extends ActiveRecord
{
    public static function tableName() { return '{{%audit_histories}}'; }

    public function rules()
    {
        return [
            [['entity_type', 'entity_id', 'action'], 'required'],
            [['entity_id', 'changed_by'], 'integer'],
            [['entity_type'], 'string', 'max' => 40],
            [['action'], 'in', 'range' => ['create', 'update', 'delete']],
            [['old_data', 'new_data'], 'safe'],
        ];
    }
}
