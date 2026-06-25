<?php

declare(strict_types=1);

namespace FileRoll\Core;

trait ControllerTrait
{
    private function getUserId(Request $request): int
    {
        $userId = $request->attributes['user_id'] ?? null;
        if ($userId === null) {
            throw new \RuntimeException(t('error.auth_required'));
        }
        return (int) $userId;
    }
}
