<?php

namespace tests\unit\services;

use Codeception\Test\Unit;
use app\services\BillingService;
use app\models\Visit;
use app\models\Invoice;

/**
 * Covers problem D3 (Pembayaran Ganda). Run with:
 *   vendor/bin/codecept run Unit services/BillingServiceTest
 */
class BillingServiceTest extends Unit
{
    public function testRetriedPaymentWithSameIdempotencyKeyIsNotDoubleCharged()
    {
        $visit = Visit::find()->one();
        $service = new BillingService();
        $invoice = $service->recalculate($visit->id);

        $key = 'test-idem-' . uniqid();
        $payment1 = $service->pay($invoice->id, 10000, 'cash', $key, 1);
        $payment2 = $service->pay($invoice->id, 10000, 'cash', $key, 1); // simulated retry

        $this->assertEquals($payment1->id, $payment2->id, 'Retry with the same key must return the same payment, not create a new one.');

        $invoice->refresh();
        $this->assertEquals(10000, $invoice->paid_amount, 'paid_amount must reflect exactly one payment, not two.');
    }

    public function testCannotOverpayInvoice()
    {
        $visit = Visit::find()->one();
        $service = new BillingService();
        $invoice = $service->recalculate($visit->id);

        $this->expectException(\yii\web\ConflictHttpException::class);
        $service->pay($invoice->id, $invoice->total_amount + 1000000, 'cash', 'test-idem-overpay-' . uniqid(), 1);
    }
}
