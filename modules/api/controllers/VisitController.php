<?php

namespace app\modules\api\controllers;

use Yii;
use app\models\Visit;
use app\services\RegistrationService;
use yii\data\ActiveDataProvider;
use yii\web\BadRequestHttpException;

/**
 * GET  /api/visits?date=2026-11-18&polyclinic_id=1&status=waiting  - queue/visit list
 * POST /api/visits/register  - register a patient's visit (see RegistrationService)
 */
class VisitController extends BaseApiController
{
    public $modelClass = Visit::class;

    public $rbacRoles = [
        '*' => ['admin', 'petugas_pendaftaran', 'dokter', 'kasir', 'petugas_farmasi', 'petugas_laboratorium'],
        'register' => ['admin', 'petugas_pendaftaran'],
        'complete' => ['admin', 'dokter'],
    ];

    public function verbs()
    {
        return array_merge(parent::verbs(), [
            'register' => ['POST'],
            'complete' => ['POST'],
        ]);
    }

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['create'], $actions['delete']); // creation only via actionRegister()
        $actions['index']['prepareDataProvider'] = function () {
            $query = Visit::find()->with(['patient', 'doctor', 'polyclinic']);
            $request = Yii::$app->request;
            if ($date = $request->get('date')) {
                $query->andWhere(['visit_date' => $date]);
            }
            if ($polyclinicId = $request->get('polyclinic_id')) {
                $query->andWhere(['polyclinic_id' => $polyclinicId]);
            }
            if ($doctorId = $request->get('doctor_id')) {
                $query->andWhere(['doctor_id' => $doctorId]);
            }
            if ($status = $request->get('status')) {
                $query->andWhere(['status' => $status]);
            }
            $query->orderBy(['queue_number' => SORT_ASC]);
            return new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 50]]);
        };
        return $actions;
    }

    /**
     * POST /api/visits/register
     * {"patient_id":1,"doctor_id":1,"polyclinic_id":1,"visit_date":"2026-11-18"}
     */
    public function actionRegister()
    {
        $body = Yii::$app->request->bodyParams;
        foreach (['patient_id', 'doctor_id', 'polyclinic_id', 'visit_date'] as $field) {
            if (empty($body[$field])) {
                throw new BadRequestHttpException("Field '{$field}' wajib diisi.");
            }
        }

        $service = new RegistrationService();
        $visit = $service->register(
            (int) $body['patient_id'],
            (int) $body['doctor_id'],
            (int) $body['polyclinic_id'],
            (string) $body['visit_date'],
            (int) Yii::$app->user->id
        );

        Yii::$app->response->statusCode = 201;
        return $visit;
    }

    /** POST /api/visits/<id>/complete - marks a visit done once billing is settled. */
    public function actionComplete($id)
    {
        $visit = Visit::findOne($id);
        if ($visit === null) {
            throw new \yii\web\NotFoundHttpException('Kunjungan tidak ditemukan.');
        }
        $visit->status = Visit::STATUS_COMPLETED;
        $visit->save(false);
        return $visit;
    }
}
