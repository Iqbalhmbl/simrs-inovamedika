<?php

namespace app\modules\api\controllers;

use Yii;
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

        return $behaviors;
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
