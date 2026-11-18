<?php

namespace app\models;

use app\services\AuditService;
use yii\db\ActiveRecord;

/**
 * @property int $visit_id
 * @property string|null $anamnesis
 * @property string|null $complaint
 * @property string|null $vital_signs JSON: {"td":"120/80","nadi":80,"suhu":36.5,"rr":20}
 * @property string|null $physical_exam
 * @property string|null $primary_diagnosis
 * @property string|null $secondary_diagnosis
 * @property int $version bumped on every update after the record already existed;
 *           used together with audit_histories to make changes after billing traceable.
 */
class MedicalRecord extends ActiveRecord
{
    public static function tableName() { return '{{%medical_records}}'; }

    public function rules()
    {
        return [
            [['visit_id'], 'required'],
            [['visit_id'], 'unique'],
            [['visit_id', 'examined_by'], 'integer'],
            [['anamnesis', 'complaint', 'physical_exam'], 'string'],
            [['vital_signs'], 'safe'],
            [['primary_diagnosis', 'secondary_diagnosis'], 'string', 'max' => 255],
        ];
    }

    /**
     * Diagnosis/exam data changed after the record already existed is
     * audited automatically (problem D4), so that if billing has already
     * been generated from an earlier diagnosis, the discrepancy is always
     * traceable rather than silently overwritten.
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        if ($insert) {
            AuditService::record('medical_record', $this->id, 'create', null, $this->attributes);
        } elseif (!empty($changedAttributes)) {
            $this->updateAttributes(['version' => $this->version + 1]);
            AuditService::record('medical_record', $this->id, 'update', $changedAttributes, $this->attributes);
        }
    }

    public function getVisit()
    {
        return $this->hasOne(Visit::class, ['id' => 'visit_id']);
    }
}
