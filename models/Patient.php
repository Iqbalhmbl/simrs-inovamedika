<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $mrn Medical Record Number, auto-generated, unique
 * @property string|null $nik
 * @property string $name
 * @property string $birth_date
 * @property string $gender L|P
 * @property string|null $address
 * @property string|null $phone
 */
class Patient extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%patients}}';
    }

    public function rules()
    {
        return [
            [['name', 'birth_date', 'gender'], 'required'],
            [['name'], 'string', 'max' => 128],
            [['address'], 'string'],
            [['phone'], 'string', 'max' => 20],
            [['gender'], 'in', 'range' => ['L', 'P']],
            [['birth_date'], 'date', 'format' => 'php:Y-m-d'],
            [['nik'], 'string', 'max' => 20],
            [['nik'], 'unique', 'filter' => ['!=', 'nik', null]],
            [['nik'], 'default', 'value' => null],
            [['mrn'], 'unique'],
        ];
    }

    public function beforeSave($insert)
    {
        if ($insert && empty($this->mrn)) {
            $this->mrn = $this->generateMrn();
        }
        return parent::beforeSave($insert);
    }

    /**
     * MRN format: RM-YYYY-000001 (sequential per year). Generated inside the
     * same DB transaction as the insert; the UNIQUE constraint on `mrn` is the
     * final guard against a race between two concurrent registrations.
     */
    private function generateMrn(): string
    {
        $year = date('Y');
        $count = (int) static::find()->where(['like', 'mrn', "RM-{$year}-%", false])->count();
        do {
            $count++;
            $candidate = sprintf('RM-%s-%06d', $year, $count);
        } while (static::find()->where(['mrn' => $candidate])->exists());
        return $candidate;
    }

    public function getVisits()
    {
        return $this->hasMany(Visit::class, ['patient_id' => 'id']);
    }
}
