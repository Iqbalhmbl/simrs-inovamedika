<?php

namespace app\controllers;

use Yii;
use Firebase\JWT\JWT;
use yii\rest\Controller;
use yii\filters\ContentNegotiator;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;
use yii\filters\auth\CompositeAuth;
use app\components\JwtHttpBearerAuth;
use app\models\User;

class AuthController extends Controller
{
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
            'except' => ['login'],
        ];
        return $behaviors;
    }

    public function verbs()
    {
        return ['login' => ['POST'], 'me' => ['GET']];
    }

    /**
     * POST /api/auth/login {"username": "...", "password": "..."}
     * Returns a JWT (24h expiry) carrying the user's id ('sub') and role.
     */
    public function actionLogin()
    {
        $username = Yii::$app->request->post('username');
        $password = Yii::$app->request->post('password');

        $user = User::findByUsername((string) $username);
        if ($user === null || !$user->validatePassword((string) $password)) {
            throw new UnauthorizedHttpException('Username atau password salah.');
        }

        $now = time();
        $payload = [
            'iss' => Yii::$app->params['jwtIssuer'] ?? 'simrs-yii',
            'iat' => $now,
            'exp' => $now + 86400,
            'sub' => $user->id,
            'role' => $user->role,
        ];
        $token = JWT::encode($payload, Yii::$app->params['jwtSecret'], 'HS256');

        return [
            'token' => $token,
            'user' => $user->toArray(),
        ];
    }

    /** GET /api/auth/me - returns the identity resolved from the Authorization header. */
    public function actionMe()
    {
        return Yii::$app->user->identity;
    }
}
