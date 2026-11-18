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

        // Frontend (React, e.g. http://localhost:5173) and backend
        // (http://localhost:8080) run on different origins, so the browser
        // sends a CORS preflight (OPTIONS) before every real request. This
        // must run before the authenticator, and the OPTIONS request itself
        // must never require a token (the browser sends no Authorization
        // header on a preflight) - see beforeAction() below.
        $behaviors['corsFilter'] = [
            'class' => Cors::class,
            'cors' => [
                'Origin' => array_filter(explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: 'http://localhost:5173,http://127.0.0.1:5173')),
                'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
                'Access-Control-Request-Headers' => ['*'],
                'Access-Control-Allow-Credentials' => null,
                'Access-Control-Max-Age' => 3600,
            ],
        ];

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

        return $behaviors;
    }

    protected function authExceptActions(): array
    {
        return [];
    }

    /**
     * yii\filters\Cors answers the preflight's headers, but Yii still runs
     * the rest of the behavior chain (including the JWT authenticator)
     * unless we short-circuit here - an OPTIONS preflight carries no
     * Authorization header, so it would otherwise fail with 401 and the
     * browser would never send the real request.
     */
    public function beforeAction($action)
    {
        if (Yii::$app->request->isOptions) {
            Yii::$app->response->statusCode = 200;
            Yii::$app->end();
        }
        return parent::beforeAction($action);
    }

    public function actions()
    {
        // Drop the default ActiveController actions we don't want auto-wired
        // (e.g. 'delete' where a controller intentionally omits it); each
        // controller that extends this opts in to specific CRUD actions.
        return parent::actions();
    }
}
