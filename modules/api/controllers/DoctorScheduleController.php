<?php

namespace app\modules\api\controllers;

use app\models\DoctorSchedule;
use yii\data\ActiveDataProvider;

/** Supports ?doctor_id= and ?polyclinic_id= and ?day_of_week= filters for the registration screen's "pick a doctor on this day" picker. */
class DoctorScheduleController extends BaseApiController
{
    public $modelClass = DoctorSchedule::class;

    public $rbacRoles = [
        '*' => ['admin', 'petugas_pendaftaran', 'dokter'],
        'create' => ['admin'],
        'update' => ['admin'],
        'delete' => ['admin'],
    ];

    public function actions()
    {
        $actions = parent::actions();
        $actions['index']['prepareDataProvider'] = function ($action) {
            $query = DoctorSchedule::find()->with(['doctor', 'polyclinic'])->where(['is_active' => true]);
            foreach (['doctor_id', 'polyclinic_id', 'day_of_week'] as $param) {
                $value = \Yii::$app->request->get($param);
                if ($value !== null) {
                    $query->andWhere([$param => $value]);
                }
            }
            return new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 50]]);
        };
        return $actions;
    }
}
