<?php

namespace app\models;

use Yii;
use yii\base\NotSupportedException;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * @property int $id
 * @property string $username
 * @property string $password_hash
 * @property string $auth_key
 * @property string|null $access_token
 * @property string $full_name
 * @property string $role one of ROLE_* constants
 * @property int $status
 */
class User extends ActiveRecord implements IdentityInterface
{
    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 10;

    const ROLE_ADMIN = 'admin';
    const ROLE_PENDAFTARAN = 'petugas_pendaftaran';
    const ROLE_DOKTER = 'dokter';
    const ROLE_FARMASI = 'petugas_farmasi';
    const ROLE_LABORATORIUM = 'petugas_laboratorium';
    const ROLE_KASIR = 'kasir';

    public static function tableName()
    {
        return '{{%users}}';
    }

    public function rules()
    {
        return [
            [['username', 'password_hash', 'auth_key', 'full_name', 'role'], 'required'],
            [['username'], 'unique'],
            [['username'], 'string', 'max' => 64],
            [['full_name'], 'string', 'max' => 128],
            [['role'], 'in', 'range' => [
                self::ROLE_ADMIN, self::ROLE_PENDAFTARAN, self::ROLE_DOKTER,
                self::ROLE_FARMASI, self::ROLE_LABORATORIUM, self::ROLE_KASIR,
            ]],
            [['status'], 'default', 'value' => self::STATUS_ACTIVE],
            [['status'], 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_INACTIVE]],
        ];
    }

    public static function findIdentity($id)
    {
        return static::findOne(['id' => $id, 'status' => self::STATUS_ACTIVE]);
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        // Access tokens here are short-lived JWTs validated by JwtHttpBearerAuth;
        // this method is required by IdentityInterface but normal lookup happens
        // via the 'sub' claim in JwtHttpBearerAuth::authenticate(), not a column scan.
        return null;
    }

    public static function findByUsername($username)
    {
        return static::findOne(['username' => $username, 'status' => self::STATUS_ACTIVE]);
    }

    public function validatePassword($password)
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    public function getId()
    {
        return $this->getPrimaryKey();
    }

    public function getAuthKey()
    {
        return $this->auth_key;
    }

    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    public function fields()
    {
        return ['id', 'username', 'full_name', 'role'];
    }
}
