<?php

namespace app\controllers;

use Yii;
use Firebase\JWT\JWT;
use yii\rest\Controller;
use yii\filters\Cors;
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

        // See BaseApiController for the full explanation. In short:
        // yii\filters\Cors answers the browser's preflight (OPTIONS) AND
        // stops further processing for it on its own - but only if it runs
        // BEFORE verbFilter/authenticator, since those would otherwise
        // reject an OPTIONS request (no Authorization header, wrong verb)
        // before Cors ever gets a chance to add headers. Putting
        // 'corsFilter' first via the + operator guarantees that order -
        // this is the single most important fix here, since /auth/login is
        // the very first request the frontend makes.
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
