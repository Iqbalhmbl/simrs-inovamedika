<?php

namespace app\modules\api\controllers;

use Yii;
use app\models\User;
use yii\web\BadRequestHttpException;

/**
 * Admin-only user management (spec item 9: Pengelolaan Pengguna dan Hak Akses).
 * Creating/updating a user also keeps the Yii2 RBAC assignment (auth_assignment)
 * in sync with the `role` column, so RbacFilter and authManager never disagree
 * about what a user can do.
 */
class UserController extends BaseApiController
{
    public $modelClass = User::class;

    public $rbacRoles = [
        '*' => ['admin'],
    ];

    public function actionCreate()
    {
        $body = Yii::$app->request->bodyParams;
        if (empty($body['password'])) {
            throw new BadRequestHttpException('password wajib diisi.');
        }

        $user = new User();
        $user->load($body, '');
        $user->password_hash = Yii::$app->security->generatePasswordHash($body['password']);
        $user->auth_key = Yii::$app->security->generateRandomString();

        if (!$user->save()) {
            Yii::$app->response->statusCode = 422;
            return ['message' => 'Validasi gagal', 'errors' => $user->errors];
        }

        $this->syncRbacAssignment($user);

        Yii::$app->response->statusCode = 201;
        return $user;
    }

    public function actionUpdate($id)
    {
        $user = User::findOne($id);
        if ($user === null) {
            throw new \yii\web\NotFoundHttpException('Pengguna tidak ditemukan.');
        }
        $body = Yii::$app->request->bodyParams;
        $user->load($body, '');
        if (!empty($body['password'])) {
            $user->password_hash = Yii::$app->security->generatePasswordHash($body['password']);
        }
        if (!$user->save()) {
            Yii::$app->response->statusCode = 422;
            return ['message' => 'Validasi gagal', 'errors' => $user->errors];
        }

        $this->syncRbacAssignment($user);
        return $user;
    }

    private function syncRbacAssignment(User $user): void
    {
        $auth = Yii::$app->authManager;
        $auth->revokeAll($user->id);
        $role = $auth->getRole($user->role);
        if ($role !== null) {
            $auth->assign($role, $user->id);
        }
    }
}
