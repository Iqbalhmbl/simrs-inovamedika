<?php

namespace app\modules\api\controllers;

use app\models\Doctor;

class DoctorController extends BaseApiController
{
    public $modelClass = Doctor::class;

    public $rbacRoles = [
        '*' => ['admin', 'petugas_pendaftaran', 'dokter'],
        'create' => ['admin'],
        'update' => ['admin'],
        'delete' => ['admin'],
    ];
}
