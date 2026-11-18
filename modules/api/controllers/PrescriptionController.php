<?php

namespace app\modules\api\controllers;

use Yii;
use app\models\Prescription;
use app\models\PrescriptionItem;
use app\models\Medicine;
use app\services\PharmacyService;
use app\services\BillingService;
use yii\data\ActiveDataProvider;
use yii\web\BadRequestHttpException;

/**
 * POST /api/prescriptions {"visit_id":1, "items":[{"medicine_id":1,"dose":"3x1","frequency":"3x sehari","qty":10}]}
 * POST /api/prescriptions/<id>/dispense  - petugas farmasi processes & deducts stock (PharmacyService)
 * GET  /api/prescriptions?status=pending  - farmasi queue
 */
class PrescriptionController extends BaseApiController
{
    public $modelClass = Prescription::class;

    public $rbacRoles = [
        '*' => ['admin', 'dokter', 'petugas_farmasi', 'kasir'],
        'create' => ['admin', 'dokter'],
        'dispense' => ['admin', 'petugas_farmasi'],
    ];

    public function verbs()
    {
        return array_merge(parent::verbs(), ['dispense' => ['POST']]);
    }

    public function actions()
    {
        $actions = parent::actions();
        // 'create' is overridden below (creates the prescription + its items
        // transactionally, snapshotting price_at_time) - must unset, or the
        // default CreateAction runs instead, saves only the bare Prescription
        // row, and every item (and the price snapshot) is silently dropped.
        unset($actions['create']);
        $actions['index']['prepareDataProvider'] = function () {
            $query = Prescription::find()->with(['items.medicine'])->orderBy(['created_at' => SORT_DESC]);
            if ($status = Yii::$app->request->get('status')) {
                $query->andWhere(['status' => $status]);
            }
            return new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 30]]);
        };
        return $actions;
    }

    public function actionCreate()
    {
        $body = Yii::$app->request->bodyParams;
        if (empty($body['visit_id']) || empty($body['items']) || !is_array($body['items'])) {
            throw new BadRequestHttpException('visit_id dan items wajib diisi.');
        }

        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();
        try {
            $prescription = new Prescription([
                'visit_id' => $body['visit_id'],
                'doctor_id' => $body['doctor_id'] ?? null,
            ]);
            if (!$prescription->save()) {
                $transaction->rollBack();
                Yii::$app->response->statusCode = 422;
                return ['message' => 'Validasi gagal', 'errors' => $prescription->errors];
            }

            foreach ($body['items'] as $itemData) {
                $medicine = Medicine::findOne($itemData['medicine_id'] ?? null);
                if ($medicine === null) {
                    throw new BadRequestHttpException("medicine_id tidak valid: " . ($itemData['medicine_id'] ?? '?'));
                }
                $item = new PrescriptionItem([
                    'prescription_id' => $prescription->id,
                    'medicine_id' => $medicine->id,
                    'dose' => $itemData['dose'] ?? '',
                    'frequency' => $itemData['frequency'] ?? '',
                    'qty' => $itemData['qty'] ?? 1,
                    'price_at_time' => $medicine->price, // snapshot price at prescribing time
                ]);
                if (!$item->save()) {
                    throw new BadRequestHttpException(json_encode($item->errors));
                }
            }

            $transaction->commit();
            Yii::$app->response->statusCode = 201;
            return $prescription;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /** Validates stock and deducts it atomically; see PharmacyService for the concurrency story. */
    public function actionDispense($id)
    {
        $prescription = (new PharmacyService())->dispense((int) $id, (int) Yii::$app->user->id);
        (new BillingService())->recalculate($prescription->visit_id, Yii::$app->user->id);
        return $prescription;
    }
}
