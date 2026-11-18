<?php

namespace tests\unit\services;

use Codeception\Test\Unit;
use app\services\PharmacyService;
use app\models\Medicine;
use app\models\Prescription;
use app\models\PrescriptionItem;
use app\models\Visit;
use yii\web\ConflictHttpException;

/**
 * Covers problem D2 (Konkurensi Stok Obat). Run with:
 *   vendor/bin/codecept run Unit services/PharmacyServiceTest
 */
class PharmacyServiceTest extends Unit
{
    public function testDispenseFailsWhenStockInsufficient()
    {
        $medicine = new Medicine(['code' => 'TST-' . uniqid(), 'name' => 'Test Obat', 'price' => 1000, 'stock' => 2]);
        $medicine->save();

        $visit = Visit::find()->one();
        $prescription = new Prescription(['visit_id' => $visit->id, 'doctor_id' => 1]);
        $prescription->save();
        $item = new PrescriptionItem([
            'prescription_id' => $prescription->id,
            'medicine_id' => $medicine->id,
            'dose' => '1x1',
            'frequency' => '1x sehari',
            'qty' => 5, // more than available stock (2)
            'price_at_time' => $medicine->price,
        ]);
        $item->save();

        $this->expectException(ConflictHttpException::class);
        (new PharmacyService())->dispense($prescription->id, 1);

        $medicine->refresh();
        $this->assertEquals(2, $medicine->stock, 'Stock must be unchanged after a failed dispense.');
    }

    public function testDispenseSucceedsAndDeductsStockExactlyOnce()
    {
        $medicine = new Medicine(['code' => 'TST-' . uniqid(), 'name' => 'Test Obat 2', 'price' => 1000, 'stock' => 10]);
        $medicine->save();

        $visit = Visit::find()->one();
        $prescription = new Prescription(['visit_id' => $visit->id, 'doctor_id' => 1]);
        $prescription->save();
        $item = new PrescriptionItem([
            'prescription_id' => $prescription->id, 'medicine_id' => $medicine->id,
            'dose' => '1x1', 'frequency' => '1x sehari', 'qty' => 3, 'price_at_time' => $medicine->price,
        ]);
        $item->save();

        (new PharmacyService())->dispense($prescription->id, 1);

        $medicine->refresh();
        $this->assertEquals(7, $medicine->stock);
        $this->assertEquals(Prescription::STATUS_DISPENSED, $prescription->refresh()->status);
    }
}
