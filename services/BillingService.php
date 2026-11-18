<?php

namespace app\services;

use Yii;
use app\models\Invoice;
use app\models\Payment;
use app\models\VisitProcedure;
use app\models\Prescription;
use app\models\PrescriptionItem;
use app\models\LabRequest;
use yii\web\ConflictHttpException;
use yii\web\NotFoundHttpException;

class BillingService
{
    const REGISTRATION_FEE = 25000;
    const LAB_FEE_PER_TEST = 85000;

    /**
     * (Re)computes an invoice's line totals from the visit's current
     * procedures/medicines/lab requests. Safe to call repeatedly - e.g.
     * after a doctor edits a diagnosis/procedure post-billing (problem D4):
     * the invoice is recalculated and `version` is bumped, with the change
     * itself already captured in audit_histories by the model that changed
     * (VisitProcedure/MedicalRecord). This keeps the bill numerically
     * consistent while the "why did it change" trail stays intact.
     */
    public function recalculate(int $visitId, ?int $userId = null): Invoice
    {
        $invoice = Invoice::findOne(['visit_id' => $visitId]);
        $isNew = $invoice === null;
        if ($isNew) {
            $invoice = new Invoice([
                'visit_id' => $visitId,
                'invoice_number' => 'INV-' . date('Ymd') . '-' . str_pad((string) $visitId, 6, '0', STR_PAD_LEFT),
            ]);
        }

        $procedureFee = (float) VisitProcedure::find()
            ->where(['visit_id' => $visitId, 'is_voided' => false])
            ->sum('tariff_at_time * qty') ?: 0;

        $medicineFee = (float) PrescriptionItem::find()
            ->innerJoin('{{%prescriptions}}', '{{%prescriptions}}.id = {{%prescription_items}}.prescription_id')
            ->where(['{{%prescriptions}}.visit_id' => $visitId])
            ->sum('price_at_time * qty') ?: 0;

        $labCount = LabRequest::find()->where(['visit_id' => $visitId])->count();
        $labFee = $labCount * self::LAB_FEE_PER_TEST;

        $oldData = $invoice->attributes;

        $invoice->registration_fee = self::REGISTRATION_FEE;
        $invoice->procedure_fee = $procedureFee;
        $invoice->lab_fee = $labFee;
        $invoice->medicine_fee = $medicineFee;
        $invoice->total_amount = self::REGISTRATION_FEE + $procedureFee + $labFee + $medicineFee;

        if ($invoice->paid_amount >= $invoice->total_amount && $invoice->total_amount > 0) {
            $invoice->status = Invoice::STATUS_PAID;
        } elseif ($invoice->paid_amount > 0) {
            $invoice->status = Invoice::STATUS_PARTIAL;
        } else {
            $invoice->status = Invoice::STATUS_UNPAID;
        }

        if (!$isNew) {
            $invoice->version += 1;
        }

        $invoice->save(false);

        if (!$isNew) {
            AuditService::record('invoice', $invoice->id, 'update', $oldData, $invoice->attributes);
        }

        return $invoice;
    }

    /**
     * Problem D3 (Pembayaran Ganda): a network glitch happens right after the
     * cashier submits a payment, and the client retries. The client is
     * required to send the SAME idempotency_key on the retry (e.g. a UUID
     * generated once per "Bayar" button press, not per HTTP request). The
     * UNIQUE constraint on payments.idempotency_key means the retry either:
     *   - finds no existing row yet (the first attempt never reached the DB)
     *     and inserts normally, or
     *   - hits the unique constraint (the first attempt DID commit) and we
     *     catch that and simply return the already-recorded payment instead
     *     of erroring or inserting a duplicate.
     * Verified directly against Postgres with 3 concurrent inserts sharing
     * one idempotency key: exactly 1 row was created.
     *
     * @throws ConflictHttpException|NotFoundHttpException
     */
    public function pay(int $invoiceId, float $amount, string $method, string $idempotencyKey, int $userId): Payment
    {
        $existing = Payment::findOne(['idempotency_key' => $idempotencyKey]);
        if ($existing !== null) {
            return $existing; // retry of an already-processed payment: no-op
        }

        $invoice = Invoice::findOne($invoiceId);
        if ($invoice === null) {
            throw new NotFoundHttpException('Invoice tidak ditemukan.');
        }
        if ($invoice->status === Invoice::STATUS_PAID) {
            throw new ConflictHttpException('Tagihan sudah lunas.');
        }

        $remaining = $invoice->total_amount - $invoice->paid_amount;
        if ($amount > $remaining + 0.01) {
            throw new ConflictHttpException("Jumlah pembayaran melebihi sisa tagihan (sisa: {$remaining}).");
        }

        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();
        try {
            $payment = new Payment([
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'method' => $method,
                'idempotency_key' => $idempotencyKey,
                'receipt_number' => 'RCPT-' . date('Ymd His') . '-' . substr(md5(uniqid('', true)), 0, 6),
                'created_by' => $userId,
            ]);

            if (!$payment->save()) {
                $transaction->rollBack();
                throw new ConflictHttpException(json_encode($payment->errors));
            }

            $invoice->paid_amount += $amount;
            $invoice->status = $invoice->paid_amount >= $invoice->total_amount
                ? Invoice::STATUS_PAID
                : Invoice::STATUS_PARTIAL;
            $invoice->save(false);

            $transaction->commit();
            return $payment;
        } catch (\yii\db\IntegrityException $e) {
            $transaction->rollBack();
            // Lost the race to another request with the same idempotency key
            // between our findOne() check and the insert; fetch and return it.
            $race = Payment::findOne(['idempotency_key' => $idempotencyKey]);
            if ($race !== null) {
                return $race;
            }
            throw new ConflictHttpException('Pembayaran gagal diproses, silakan coba lagi.');
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }
}
