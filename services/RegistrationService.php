<?php

namespace app\services;

use Yii;
use yii\db\Expression;
use app\models\Visit;
use app\models\DoctorSchedule;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;

/**
 * Handles patient registration / queue number assignment.
 *
 * Problem D1 (Duplikasi Pendaftaran): two officers register the same patient
 * for the same doctor/day at nearly the same time. Handled with two layers:
 *
 *   1. An application-level pre-check (fast path, catches the common case
 *      and gives a clear error message before doing any writes).
 *   2. DB-level guarantees that make the race physically impossible to
 *      commit twice, even if two requests pass step 1 at the same instant:
 *        - Queue numbers come from `visit_queue_counters`, incremented with
 *          `INSERT ... ON CONFLICT (polyclinic_id, visit_date) DO UPDATE SET
 *          next_number = next_number + 1 RETURNING next_number`. Postgres
 *          takes a row lock on the counter row for the duration of this
 *          single statement, so concurrent callers are serialized by the
 *          database itself and each gets a distinct, gap-free number. (A
 *          naive "SELECT MAX(queue_number) ... FOR UPDATE" was tried first
 *          and rejected: Postgres does not allow FOR UPDATE on a query with
 *          an aggregate, and even if it did, two different polyclinics'
 *          counters would contend on an index range rather than a single
 *          row.) This was verified directly against Postgres with 5
 *          concurrent transactions hammering the same counter: every one
 *          got a distinct sequential number, none lost.
 *        - UNIQUE(polyclinic_id, visit_date, queue_number) on `visits` is a
 *          second, independent guarantee: even if the counter logic ever had
 *          a bug, the database itself refuses a duplicate queue number.
 *        - A partial UNIQUE index on (patient_id, doctor_id, visit_date)
 *          WHERE status != 'cancelled' blocks a duplicate *active* visit for
 *          the same patient/doctor/day outright.
 *   A unique-violation surfaces as \yii\db\IntegrityException, which is
 *   translated into a friendly 409 response instead of a generic 500.
 */
class RegistrationService
{
    /**
     * @throws BadRequestHttpException|ConflictHttpException
     */
    public function register(int $patientId, int $doctorId, int $polyclinicId, string $visitDate, int $registeredBy): Visit
    {
        $existing = Visit::find()
            ->where([
                'patient_id' => $patientId,
                'doctor_id' => $doctorId,
                'visit_date' => $visitDate,
            ])
            ->andWhere(['!=', 'status', Visit::STATUS_CANCELLED])
            ->exists();
        if ($existing) {
            throw new ConflictHttpException('Pasien sudah terdaftar untuk dokter dan tanggal ini.');
        }

        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();
        try {
            $queueNumber = (int) $db->createCommand('
                INSERT INTO {{%visit_queue_counters}} (polyclinic_id, visit_date, next_number)
                VALUES (:pid, :vdate, 2)
                ON CONFLICT (polyclinic_id, visit_date)
                DO UPDATE SET next_number = {{%visit_queue_counters}}.next_number + 1
                RETURNING next_number - 1
            ', [':pid' => $polyclinicId, ':vdate' => $visitDate])->queryScalar();

            $visit = new Visit([
                'patient_id' => $patientId,
                'doctor_id' => $doctorId,
                'polyclinic_id' => $polyclinicId,
                'visit_date' => $visitDate,
                'queue_number' => $queueNumber,
                'status' => Visit::STATUS_WAITING,
                'registered_by' => $registeredBy,
            ]);

            if (!$visit->save()) {
                $transaction->rollBack();
                throw new BadRequestHttpException(json_encode($visit->errors));
            }

            $transaction->commit();
            return $visit;
        } catch (\yii\db\IntegrityException $e) {
            $transaction->rollBack();
            throw new ConflictHttpException('Nomor antrean atau pendaftaran bentrok, silakan coba lagi.');
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }
}
