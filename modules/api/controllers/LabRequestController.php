<?php

namespace app\modules\api\controllers;

use Yii;
use app\models\LabRequest;
use yii\data\ActiveDataProvider;

/**
 * GET  /api/lab-requests?status=requested  - petugas laboratorium's worklist
 * POST /api/lab-requests  {"visit_id":..., "test_name":"Darah Lengkap", "notes":"..."}
 * Results are submitted through LabResultController (also exposed via the
 * Node.js service's REST API for external/lab-system integration, see
 * services/node/src/routes/labResults.js).
 */
class LabRequestController extends BaseApiController
{
    public $modelClass = LabRequest::class;

    public $rbacRoles = [
        '*' => ['admin', 'dokter', 'petugas_laboratorium'],
        'create' => ['admin', 'dokter'],
    ];

    public function actions()
    {
        $actions = parent::actions();
        $actions['index']['prepareDataProvider'] = function () {
            $query = LabRequest::find()->with(['result'])->orderBy(['created_at' => SORT_ASC]);
            if ($status = Yii::$app->request->get('status')) {
                $query->andWhere(['status' => $status]);
            }
            if ($visitId = Yii::$app->request->get('visit_id')) {
                $query->andWhere(['visit_id' => $visitId]);
            }
            return new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 30]]);
        };
        return $actions;
    }

    public function actionCreate()
    {
        $model = new LabRequest();
        $model->load(Yii::$app->request->bodyParams, '');
        $model->requested_by = Yii::$app->user->id;
        if (!$model->save()) {
            Yii::$app->response->statusCode = 422;
            return ['message' => 'Validasi gagal', 'errors' => $model->errors];
        }
        Yii::$app->response->statusCode = 201;
        return $model;
    }
}
