<?php

namespace app\modules\api\controllers;

use app\models\Patient;
use yii\data\ActiveDataProvider;

/**
 * GET    /api/patients?search=budi        - list + search by name/mrn/nik
 * GET    /api/patients/<id>
 * POST   /api/patients
 * PUT    /api/patients/<id>
 * DELETE /api/patients/<id>
 */
class PatientController extends BaseApiController
{
    public $modelClass = Patient::class;

    public $rbacRoles = [
        '*' => ['admin', 'petugas_pendaftaran', 'dokter', 'kasir'],
        'create' => ['admin', 'petugas_pendaftaran'],
        'update' => ['admin', 'petugas_pendaftaran'],
        'delete' => ['admin'],
    ];

    public function actions()
    {
        $actions = parent::actions();
        $actions['index']['prepareDataProvider'] = function ($action) {
            $query = Patient::find();
            $search = \Yii::$app->request->get('search');
            if ($search) {
                $query->andWhere(['or',
                    ['ilike', 'name', $search],
                    ['ilike', 'mrn', $search],
                    ['ilike', 'nik', $search],
                ]);
            }
            return new ActiveDataProvider([
                'query' => $query,
                'pagination' => ['pageSize' => 20],
                'sort' => ['defaultOrder' => ['created_at' => SORT_DESC]],
            ]);
        };
        return $actions;
    }
}
