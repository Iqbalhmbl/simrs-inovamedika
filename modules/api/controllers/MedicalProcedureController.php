<?php

namespace app\modules\api\controllers;

use app\models\MedicalProcedure;

class MedicalProcedureController extends BaseApiController
{
    public $modelClass = MedicalProcedure::class;

    public $rbacRoles = [
        '*' => ['admin', 'dokter', 'kasir'],
        'create' => ['admin'],
        'update' => ['admin'],
        'delete' => ['admin'],
    ];
}
