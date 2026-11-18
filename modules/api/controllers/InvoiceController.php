<?php

namespace app\modules\api\controllers;

use Yii;
use app\models\Invoice;
use app\services\BillingService;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

/**
 * GET  /api/invoices/by-visit/<visitId>
 * POST /api/invoices/<id>/pay  {"amount":..., "method":"cash|non_cash", "idempotency_key":"<client-generated uuid>"}
 */
class InvoiceController extends BaseApiController
{
    public $modelClass = Invoice::class;

    public $rbacRoles = [
        '*' => ['admin', 'kasir', 'dokter', 'petugas_pendaftaran'],
        'pay' => ['admin', 'kasir'],
    ];

    public function verbs()
    {
        return array_merge(parent::verbs(), ['pay' => ['POST'], 'by-visit' => ['GET']]);
    }

    public function actionByVisit($visitId)
    {
        $invoice = Invoice::findOne(['visit_id' => $visitId]);
        if ($invoice === null) {
            $invoice = (new BillingService())->recalculate((int) $visitId);
        }
        return $invoice->toArray([], ['payments']);
    }

    /**
     * Problem D3 (Pembayaran Ganda): the client MUST send the same
     * idempotency_key when retrying a payment after a network failure (e.g.
     * generated once per "Bayar" click, stored client-side, reused on retry
     * until a definitive success/failure response is received). See
     * BillingService::pay() for how the server guarantees a single charge.
     */
    public function actionPay($id)
    {
        $body = Yii::$app->request->bodyParams;
        foreach (['amount', 'method', 'idempotency_key'] as $field) {
            if (empty($body[$field])) {
                throw new BadRequestHttpException("Field '{$field}' wajib diisi.");
            }
        }

        $payment = (new BillingService())->pay(
            (int) $id,
            (float) $body['amount'],
            (string) $body['method'],
            (string) $body['idempotency_key'],
            (int) Yii::$app->user->id
        );

        return $payment;
    }
}
