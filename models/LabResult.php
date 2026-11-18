<?php

namespace app\models;

use yii\db\ActiveRecord;

class LabResult extends ActiveRecord
{
    public static function tableName() { return '{{%lab_results}}'; }

    public function rules()
    {
        return [
            [['lab_request_id'], 'required'],
            [['lab_request_id'], 'unique'],
            [['lab_request_id', 'input_by'], 'integer'],
            [['result_text'], 'string'],
            [['result_data'], 'safe'],
        ];
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        if ($insert) {
            $request = LabRequest::findOne($this->lab_request_id);
            if ($request !== null) {
                $request->status = LabRequest::STATUS_DONE;
                $request->save(false);
            }
        }
    }
}
