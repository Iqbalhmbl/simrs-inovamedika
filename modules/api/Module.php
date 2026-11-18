<?php

namespace app\modules\api;

/**
 * REST API module. All business-logic endpoints (master data, registration,
 * examination, pharmacy, lab, billing) live here under /api/...; only
 * /api/auth/login and /api/auth/me stay outside the module (see
 * controllers/AuthController.php) since login has no identity to attach a
 * module-level behavior to yet.
 */
class Module extends \yii\base\Module
{
    public $controllerNamespace = 'app\modules\api\controllers';
}
