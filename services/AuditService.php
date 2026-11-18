<?php

namespace app\services;

use Yii;
use app\models\AuditHistory;

/**
 * Central helper for writing to audit_histories. Keep every call to this
 * inside the same DB transaction as the entity change it is recording, so
 * the audit row and the change it describes are always consistent together.
 */
class AuditService
{
    public static function record(string $entityType, int $entityId, string $action, ?array $oldData, ?array $newData): void
    {
        $history = new AuditHistory([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'old_data' => $oldData !== null ? json_encode($oldData) : null,
            'new_data' => $newData !== null ? json_encode($newData) : null,
            'changed_by' => Yii::$app->user->id ?? null,
        ]);
        $history->save(false);
    }
}
