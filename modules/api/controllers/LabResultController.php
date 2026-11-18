<?php

namespace app\modules\api\controllers;

use Yii;
use app\models\LabResult;
use app\models\LabRequest;
use app\services\BillingService;
use yii\web\BadRequestHttpException;

class LabResultController extends BaseApiController
{
    public $modelClass = LabResult::class;

    public $rbacRoles = [
        '*' => ['admin', 'dokter', 'petugas_laboratorium'],
        'create' => ['admin', 'petugas_laboratorium'],
    ];

    public function actions()
    {
        $actions = parent::actions();
        // 'create' is overridden below (snapshots lab_request_id lookup and
        // re-bills the visit) - must unset, or the default CreateAction runs
        // instead and this logic never executes.
        unset($actions['create']);
        return $actions;
    }

    public function actionCreate()
    {
        $body = Yii::$app->request->bodyParams;
        $request = LabRequest::findOne($body['lab_request_id'] ?? null);
        if ($request === null) {
            throw new BadRequestHttpException('lab_request_id tidak valid.');
        }

        $result = new LabResult();
        $result->load($body, '');
        $result->input_by = Yii::$app->user->id;
        if (!$result->save()) {
            Yii::$app->response->statusCode = 422;
            return ['message' => 'Validasi gagal', 'errors' => $result->errors];
        }

        (new BillingService())->recalculate($request->visit_id, Yii::$app->user->id);

        Yii::$app->response->statusCode = 201;
        return $result;
    }
}
