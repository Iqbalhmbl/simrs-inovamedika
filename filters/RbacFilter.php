<?php

namespace app\filters;

use Yii;
use yii\base\ActionFilter;
use yii\web\ForbiddenHttpException;

/**
 * Simple role-based access filter (RBAC is just 6 fixed roles for this scope,
 * so a permission-node hierarchy would be overkill).
 *
 * Usage in a controller's behaviors():
 *   'rbac' => [
 *       'class' => RbacFilter::class,
 *       'roles' => [
 *           'index' => ['admin', 'petugas_pendaftaran', 'dokter'],
 *           'create' => ['admin', 'petugas_pendaftaran'],
 *       ],
 *   ],
 */
class RbacFilter extends ActionFilter
{
    /** @var array action => [role, ...] */
    public $roles = [];

    public function beforeAction($action)
    {
        $allowed = $this->roles[$action->id] ?? $this->roles['*'] ?? null;
        if ($allowed === null) {
            return true; // no restriction declared for this action
        }

        $identity = Yii::$app->user->identity;
        if ($identity === null || !in_array($identity->role, $allowed, true)) {
            throw new ForbiddenHttpException('Anda tidak memiliki akses untuk tindakan ini.');
        }

        return true;
    }
}
