<?php

namespace app\models;

use yii\db\ActiveRecord;

class LabRequest extends ActiveRecord
{
    const STATUS_REQUESTED = 'requested';
    const STATUS_PROCESSED = 'processed';
    const STATUS_DONE = 'done';

    public static function tableName() { return '{{%lab_requests}}'; }

    public function rules()
    {
        return [
            [['visit_id', 'test_name'], 'required'],
            [['visit_id', 'requested_by'], 'integer'],
            [['test_name'], 'string', 'max' => 128],
            [['notes'], 'string'],
            [['status'], 'in', 'range' => [self::STATUS_REQUESTED, self::STATUS_PROCESSED, self::STATUS_DONE]],
            [['status'], 'default', 'value' => self::STATUS_REQUESTED],
        ];
    }

    public function getResult()
    {
        return $this->hasOne(LabResult::class, ['lab_request_id' => 'id']);
    }
}
