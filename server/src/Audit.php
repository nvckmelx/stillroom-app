<?php
declare(strict_types=1);

namespace Stillroom;

final class Audit
{
    public static function log(string $action, string $entity = '', string $entityId = '', string $detail = ''): void
    {
        $uid = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : null;
        Db::insert('audit_log', [
            'user_id' => $uid, 'action' => $action, 'entity' => $entity,
            'entity_id' => $entityId, 'detail' => mb_substr($detail, 0, 180), 'created_at' => now(),
        ]);
    }

    public static function recent(int $limit = 20): array
    {
        return Db::all(
            'SELECT a.*, u.email FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT ' . max(1, min(200, $limit))
        );
    }
}
