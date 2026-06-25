<?php

declare(strict_types=1);

namespace FileRoll\File;

use FileRoll\Core\ControllerTrait;

use FileRoll\Core\Request;
use FileRoll\Core\Response;

class FolderController
{
    use ControllerTrait;
    public function create(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);
        $rawParentId = $request->input('parent_id');
        $parentId = ($rawParentId !== null && $rawParentId !== '' && $rawParentId !== 'null' && (int) $rawParentId > 0)
            ? (int) $rawParentId
            : null;
        $name = $request->input('name', '');

        if ($name === '') {
            return Response::error(400, t('error.folder_name_required'));
        }

        $c = \FileRoll\Core\Container::getInstance();
        $fileService = $c->get(\FileRoll\File\FileService::class);

        try {
            $folder = $fileService->createFolder($userId, $parentId, $name);

            if ($request->isAjax()) {
                return $response->json([
                    'success' => true,
                    'folder' => [
                        'id' => $folder->id,
                        'name' => $folder->name,
                    ],
                ]);
            }

            return $response->redirect(BASE_URL . '/files?folder=' . ($parentId ?? ''));
        } catch (\RuntimeException $e) {
            if ($request->isAjax()) {
                return Response::error(400, $e->getMessage());
            }
            return $response->redirect(BASE_URL . '/files?folder=' . ($parentId ?? '') . '&error=' . urlencode($e->getMessage()));
        }
    }
}