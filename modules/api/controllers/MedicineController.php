<?php

namespace app\modules\api\controllers;

use app\models\Medicine;

class MedicineController extends BaseApiController
{
    public $modelClass = Medicine::class;

    public $rbacRoles = [
        '*' => ['admin', 'dokter', 'petugas_farmasi'],
        'create' => ['admin', 'petugas_farmasi'],
        'update' => ['admin', 'petugas_farmasi'],
        'delete' => ['admin'],
    ];
}
