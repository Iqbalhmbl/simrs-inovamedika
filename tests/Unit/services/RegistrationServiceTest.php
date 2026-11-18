<?php

namespace tests\unit\services;

use Codeception\Test\Unit;
use app\services\RegistrationService;
use app\models\Patient;
use app\models\Doctor;
use app\models\Polyclinic;
use app\models\Visit;
use yii\web\ConflictHttpException;

/**
 * Covers problem D1 (Duplikasi Pendaftaran). Run with:
 *   cd yii2-project && vendor/bin/codecept run Unit services/RegistrationServiceTest
 * Requires tests/Unit.suite.yml to point at a Postgres test database
 * (config/test_db.php) with the schema migrated.
 */
class RegistrationServiceTest extends Unit
{
    private function makePatient(string $name): Patient
    {
        $patient = new Patient(['name' => $name, 'birth_date' => '1990-01-01', 'gender' => 'L']);
        $patient->save();
        return $patient;
    }

    public function testCannotRegisterSamePatientTwiceForSameDoctorAndDay()
    {
        $patient = $this->makePatient('Test Pasien Duplikat');
        $doctor = Doctor::find()->one() ?? (new Doctor(['name' => 'Dr. Test']));
        if ($doctor->isNewRecord) $doctor->save();
        $poly = Polyclinic::find()->one() ?? (new Polyclinic(['code' => 'TST', 'name' => 'Poli Test']));
        if ($poly->isNewRecord) $poly->save();

        $service = new RegistrationService();
        $visit1 = $service->register($patient->id, $doctor->id, $poly->id, '2026-12-01', 1);
        $this->assertNotNull($visit1->id);

        $this->expectException(ConflictHttpException::class);
        $service->register($patient->id, $doctor->id, $poly->id, '2026-12-01', 1);
    }

    public function testQueueNumbersAreSequentialAndGapFree()
    {
        $doctor = Doctor::find()->one();
        $poly = Polyclinic::find()->one();
        $service = new RegistrationService();

        $numbers = [];
        for ($i = 0; $i < 3; $i++) {
            $patient = $this->makePatient("Pasien Antre {$i}");
            $visit = $service->register($patient->id, $doctor->id, $poly->id, '2026-12-02', 1);
            $numbers[] = $visit->queue_number;
        }

        $this->assertEquals(range(min($numbers), min($numbers) + 2), $numbers);
    }
}
