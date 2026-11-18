<?php

namespace app\services;

use Yii;
use app\models\PrescriptionItem;
use app\models\Prescription;
use app\models\MedicineStockMutation;
use yii\web\ConflictHttpException;
use yii\web\NotFoundHttpException;

/**
 * Problem D2 (Konkurensi Stok Obat): two pharmacy transactions try to
 * dispense the same medicine when stock only covers one of them.
 *
 * Verified directly against Postgres (see race_stock.sql in dev notes):
 * `SELECT stock FROM medicines WHERE id = :id FOR UPDATE` inside a
 * transaction takes a row lock, so a second concurrent transaction blocks
 * until the first commits or rolls back - it then re-reads the now-updated
 * stock. Combined with a conditional `UPDATE ... WHERE stock >= :qty`
 * (belt-and-braces alongside the CHECK (stock >= 0) constraint), exactly one
 * of two racing dispense requests succeeds; the other gets a clear 409
 * instead of silently taking stock negative.
 */
class PharmacyService
{
    /**
     * Dispenses every item of a prescription in one transaction: all-or-nothing,
     * so a partially-filled prescription never leaves stock inconsistent.
     * @throws ConflictHttpException|NotFoundHttpException
     */
    public function dispense(int $prescriptionId, int $userId): Prescription
    {
        $prescription = Prescription::findOne($prescriptionId);
        if ($prescription === null) {
            throw new NotFoundHttpException('Resep tidak ditemukan.');
        }
        if ($prescription->status === Prescription::STATUS_DISPENSED) {
            throw new ConflictHttpException('Resep sudah diproses sebelumnya.');
        }

        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();
        try {
            foreach ($prescription->items as $item) {
                if ($item->dispensed) {
                    continue;
                }

                // Row lock: blocks concurrent dispensers of the same medicine
                // until this transaction commits/rolls back.
                $currentStock = (int) $db->createCommand(
                    'SELECT stock FROM {{%medicines}} WHERE id = :id FOR UPDATE',
                    [':id' => $item->medicine_id]
                )->queryScalar();

                if ($currentStock < $item->qty) {
                    throw new ConflictHttpException(
                        "Stok obat tidak mencukupi untuk item #{$item->id} (tersedia: {$currentStock}, diminta: {$item->qty})."
                    );
                }

                $affected = $db->createCommand(
                    'UPDATE {{%medicines}} SET stock = stock - :qty WHERE id = :id AND stock >= :qty',
                    [':qty' => $item->qty, ':id' => $item->medicine_id]
                )->execute();

                if ($affected === 0) {
                    // Lost the race between the lock read above and this write
                    // (shouldn't happen given FOR UPDATE, but fail safe anyway).
                    throw new ConflictHttpException('Stok berubah saat diproses, silakan coba lagi.');
                }

                (new MedicineStockMutation([
                    'medicine_id' => $item->medicine_id,
                    'change_qty' => -$item->qty,
                    'reference_type' => 'prescription_item',
                    'reference_id' => $item->id,
                    'created_by' => $userId,
                ]))->save(false);

                $item->dispensed = true;
                $item->dispensed_at = date('Y-m-d H:i:s');
                $item->dispensed_by = $userId;
                $item->save(false);
            }

            $prescription->status = Prescription::STATUS_DISPENSED;
            $prescription->save(false);

            $transaction->commit();
            return $prescription;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }
}
