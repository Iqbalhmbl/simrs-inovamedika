<?php

namespace app\modules\api\controllers;

use Yii;
use app\models\VisitProcedure;
use app\models\MedicalProcedure;
use app\services\AuditService;
use app\services\BillingService;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

/**
 * POST   /api/visit-procedures {"visit_id":..., "medical_procedure_id":..., "qty":1}
 * DELETE /api/visit-procedures/<id>  - voids (never hard-deletes) and re-bills
 */
class VisitProcedureController extends BaseApiController
{
    public $modelClass = VisitProcedure::class;

    public $rbacRoles = [
        '*' => ['admin', 'dokter', 'kasir'],
        'create' => ['admin', 'dokter'],
        'delete' => ['admin', 'dokter'],
    ];

    public function actions()
    {
        $actions = parent::actions();
        // Both 'create' and 'delete' are overridden below with custom logic
        // (tariff snapshot on create; void+re-bill instead of hard-delete on
        // delete). They MUST be unset here, otherwise Yii2's ActiveController
        // still owns those action ids and your own actionCreate()/actionDelete()
        // methods are never called - this was the actual bug: the raw
        // ActiveController CreateAction saved only the body's fields
        // (visit_id, medical_procedure_id, qty), skipping the tariff_at_time
        // snapshot entirely, which then violated the NOT NULL constraint.
        unset($actions['create'], $actions['delete']);
        return $actions;
    }

    public function actionCreate()
    {
        $body = Yii::$app->request->bodyParams;
        $procedure = MedicalProcedure::findOne($body['medical_procedure_id'] ?? null);
        if ($procedure === null) {
            throw new BadRequestHttpException('medical_procedure_id tidak valid.');
        }

        $vp = new VisitProcedure();
        $vp->visit_id = $body['visit_id'] ?? null;
        $vp->medical_procedure_id = $procedure->id;
        $vp->qty = $body['qty'] ?? 1;
        $vp->tariff_at_time = $procedure->tariff; // snapshot the tariff at time of billing
        $vp->created_by = Yii::$app->user->id;

        if (!$vp->save()) {
            Yii::$app->response->statusCode = 422;
            return ['message' => 'Validasi gagal', 'errors' => $vp->errors];
        }
        AuditService::record('visit_procedure', $vp->id, 'create', null, $vp->attributes);

        (new BillingService())->recalculate($vp->visit_id, Yii::$app->user->id);

        Yii::$app->response->statusCode = 201;
        return $vp;
    }

    /** "Delete" = void + re-bill, so billing history always explains the change (problem D4). */
    public function actionDelete($id)
    {
        $vp = VisitProcedure::findOne($id);
        if ($vp === null) {
            throw new NotFoundHttpException('Tindakan tidak ditemukan.');
        }
        $old = $vp->attributes;
        $vp->is_voided = true;
        $vp->save(false);
        AuditService::record('visit_procedure', $vp->id, 'update', $old, $vp->attributes);

        (new BillingService())->recalculate($vp->visit_id, Yii::$app->user->id);

        Yii::$app->response->statusCode = 204;
    }
}
