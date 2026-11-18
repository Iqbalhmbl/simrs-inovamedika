<?php

namespace app\modules\api\controllers;

use Yii;
use yii\filters\Cors;
use yii\filters\ContentNegotiator;
use yii\filters\auth\CompositeAuth;
use yii\rest\ActiveController;
use yii\web\Response;
use app\components\JwtHttpBearerAuth;
use app\filters\RbacFilter;

/**
 * Base for all API controllers. Every error response (validation, 401, 403,
 * 404, 409, 500) is normalized to the same JSON envelope by
 * app\filters\ErrorResponseFormatter (wired in config/web.php's 'response'
 * component), so API consumers (React, Postman, Node) only ever deal with
 * one shape: {"success": false, "error": {"code", "message", "details"}}.
 */
abstract class BaseApiController extends ActiveController
{
    /** @var array action => [role,...] - override in subclasses */
    public $rbacRoles = [];

    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::class,
            'formats' => ['application/json' => Response::FORMAT_JSON],
        ];

        $behaviors['authenticator'] = [
            'class' => CompositeAuth::class,
            'authMethods' => [JwtHttpBearerAuth::class],
            'except' => $this->authExceptActions(),
        ];

        $behaviors['rbac'] = [
            'class' => RbacFilter::class,
            'roles' => $this->rbacRoles,
        ];

        // Frontend (React, e.g. http://localhost:5173) and backend run on
        // different origins, so the browser sends a CORS preflight (OPTIONS)
        // before every real request. yii\filters\Cors answers that preflight
        // AND stops further processing for OPTIONS on its own - no need for
        // a manual short-circuit. What matters is ORDER: Yii runs behaviors
        // in the order they appear in this array, and corsFilter must run
        // BEFORE verbFilter/authenticator/rbac (an OPTIONS preflight has no
        // Authorization header and isn't a GET/POST, so if those ran first
        // they would 401/405 the preflight before Cors ever got a chance to
        // add headers - this was exactly the earlier bug). Putting
        // 'corsFilter' first in the returned array via the + operator
        // guarantees it is attached, and therefore triggered, first.
        $corsFilter = [
            'corsFilter' => [
                'class' => Cors::class,
                'cors' => [
                    'Origin' => array_filter(explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: 'http://localhost:5173,http://127.0.0.1:5173')),
                    'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
                    'Access-Control-Request-Headers' => ['*'],
                    'Access-Control-Allow-Credentials' => null,
                    'Access-Control-Max-Age' => 3600,
                ],
            ],
        ];

        return $corsFilter + $behaviors;
    }

    protected function authExceptActions(): array
    {
        return [];
    }

    public function actions()
    {
        // Drop the default ActiveController actions we don't want auto-wired
        // (e.g. 'delete' where a controller intentionally omits it); each
        // controller that extends this opts in to specific CRUD actions.
        return parent::actions();
    }
}
