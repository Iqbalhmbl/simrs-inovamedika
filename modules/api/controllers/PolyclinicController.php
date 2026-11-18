<?php

namespace app\modules\api\controllers;

use app\models\Polyclinic;

class PolyclinicController extends BaseApiController
{
    public $modelClass = Polyclinic::class;

    public $rbacRoles = [
        '*' => ['admin', 'petugas_pendaftaran', 'dokter'],
        'create' => ['admin'],
        'update' => ['admin'],
        'delete' => ['admin'],
    ];
}
