<?php

namespace app\modules\api\controllers;

use Yii;
use app\models\MedicalRecord;
use app\models\Visit;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

/**
 * GET  /api/medical-records/by-visit/<visitId>
 * POST /api/medical-records  {"visit_id":..., "anamnesis":..., "vital_signs": {...}, ...}
 * PUT  /api/medical-records/<id>  - update after creation (audited automatically, see MedicalRecord::afterSave)
 */
class MedicalRecordController extends BaseApiController
{
    public $modelClass = MedicalRecord::class;

    public $rbacRoles = [
        '*' => ['admin', 'dokter'],
        'by-visit' => ['admin', 'dokter', 'petugas_laboratorium', 'kasir'],
    ];

    public function verbs()
    {
        return array_merge(parent::verbs(), ['by-visit' => ['GET']]);
    }

    public function actionByVisit($visitId)
    {
        $record = MedicalRecord::findOne(['visit_id' => $visitId]);
        if ($record === null) {
            throw new NotFoundHttpException('Rekam medis untuk kunjungan ini belum dibuat.');
        }
        return $record;
    }

    public function actionCreate()
    {
        $body = Yii::$app->request->bodyParams;
        $visit = Visit::findOne($body['visit_id'] ?? null);
        if ($visit === null) {
            throw new BadRequestHttpException('visit_id tidak valid.');
        }

        $record = new MedicalRecord();
        $record->load($body, '');
        $record->examined_by = Yii::$app->user->id;
        if (!$record->save()) {
            Yii::$app->response->statusCode = 422;
            return ['message' => 'Validasi gagal', 'errors' => $record->errors];
        }

        $visit->status = Visit::STATUS_EXAMINED;
        $visit->save(false);

        Yii::$app->response->statusCode = 201;
        return $record;
    }
}
