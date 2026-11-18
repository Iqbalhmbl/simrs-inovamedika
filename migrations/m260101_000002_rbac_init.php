<?php

use yii\db\Migration;

/**
 * Loads Yii2's built-in RBAC (DbManager) schema and defines the 6 roles
 * required by the spec. Permissions are enforced per-controller/action via
 * RbacFilter (see filters/RbacFilter.php) using these role names directly
 * (no fine-grained permission nodes needed for this scope).
 */
class m260101_000002_rbac_init extends Migration
{
    public function safeUp()
    {
        $sql = file_get_contents(
            Yii::getAlias('@vendor/yiisoft/yii2/rbac/migrations/schema-pgsql.sql')
        );
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            $this->db->createCommand($stmt)->execute();
        }

        $auth = Yii::$app->authManager;

        $roles = [
            'admin' => 'Administrator - full access',
            'petugas_pendaftaran' => 'Petugas Pendaftaran - registration & queue',
            'dokter' => 'Dokter - examination, medical records, procedures, prescriptions, lab requests',
            'petugas_farmasi' => 'Petugas Farmasi - pharmacy & prescription dispensing',
            'petugas_laboratorium' => 'Petugas Laboratorium - lab results',
            'kasir' => 'Kasir - billing & payments',
        ];

        foreach ($roles as $name => $description) {
            $role = $auth->createRole($name);
            $role->description = $description;
            $auth->add($role);
        }
    }

    public function safeDown()
    {
        Yii::$app->authManager->removeAll();
        $sql = file_get_contents(
            Yii::getAlias('@vendor/yiisoft/yii2/rbac/migrations/schema-pgsql.sql')
        );
        foreach (['auth_assignment', 'auth_item_child', 'auth_item', 'auth_rule'] as $t) {
            $this->execute("DROP TABLE IF EXISTS {$t} CASCADE");
        }
    }
}
