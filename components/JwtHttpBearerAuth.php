<?php

namespace app\components;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Yii;
use yii\filters\auth\AuthMethod;
use yii\web\UnauthorizedHttpException;
use app\models\User;

/**
 * Validates a "Authorization: Bearer <jwt>" header issued by AuthController::actionLogin.
 * On success, Yii::$app->user->identity is set to the User row named in the 'sub' claim,
 * and Yii::$app->user->identity->role drives RbacFilter checks per action.
 */
class JwtHttpBearerAuth extends AuthMethod
{
    public function authenticate($user, $request, $response)
    {
        $authHeader = $request->getHeaders()->get('Authorization');
        if ($authHeader === null || stripos($authHeader, 'Bearer ') !== 0) {
            return null;
        }
        $token = substr($authHeader, 7);

        try {
            $decoded = JWT::decode($token, new Key(Yii::$app->params['jwtSecret'], 'HS256'));
        } catch (\Throwable $e) {
            throw new UnauthorizedHttpException('Invalid or expired token: ' . $e->getMessage());
        }

        $identity = User::findOne(['id' => $decoded->sub, 'status' => User::STATUS_ACTIVE]);
        if ($identity === null) {
            throw new UnauthorizedHttpException('User not found or inactive.');
        }

        $user->switchIdentity($identity);
        return $identity;
    }

    public function challenge($response)
    {
        $response->getHeaders()->set('WWW-Authenticate', 'Bearer realm="api"');
    }
}
