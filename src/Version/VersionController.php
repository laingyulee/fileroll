<?php

declare(strict_types=1);

namespace FileRoll\Version;

use FileRoll\Core\ControllerTrait;

use FileRoll\Core\Request;
use FileRoll\Core\Response;
use FileRoll\Core\Container;
use FileRoll\File\FileRepository;

class VersionController
{
    use ControllerTrait;
    public function listItems(Request $request, Response $response, array $params): Response
    {
        $userId = $this->getUserId($request);
        $fileId = (int) ($params['id'] ?? 0);

        $c = Container::getInstance();
        $versionRepo = $c->get(VersionRepository::class);
        $fileRepo = $c->get(\FileRoll\File\FileRepository::class);

        $file = $fileRepo->findByIdAndOwner($fileId, $userId);
        if ($file === null) {
            return Response::error(404, t('error.file_not_found'));
        }

        $versions = $versionRepo->findByFileId($fileId);

        if ($request->isAjax()) {
            $versionData = array_map(fn($v) => [
                'id' => $v->id,
                'version_number' => $v->versionNumber,
                'size' => $v->size,
                'formatted_size' => $v->getFormattedSize(),
                'created_by' => $v->createdBy,
                'created_at' => $v->createdAt,
            ], $versions);

            return $response->json(['versions' => $versionData]);
        }

        ob_start();
        include __DIR__ . '/../../templates/files/versions.php';
        $html = ob_get_clean();

        return $response->html($html);
    }

    public function restore(Request $request, Response $response, array $params): Response
    {
        $userId = $this->getUserId($request);
        $fileId = (int) ($params['id'] ?? 0);
        $versionId = (int) ($params['vid'] ?? 0);

        $c = Container::getInstance();
        $versionRepo = $c->get(VersionRepository::class);
        $fileRepo = $c->get(\FileRoll\File\FileRepository::class);

        $file = $fileRepo->findByIdAndOwner($fileId, $userId);
        if ($file === null) {
            return Response::error(404, t('error.file_not_found'));
        }

        $version = $versionRepo->findById($versionId);
        if ($version === null || $version->fileId !== $fileId) {
            return Response::error(404, t('error.version_not_found'));
        }

        $currentVersionNumber = $versionRepo->getLatestVersionNumber($fileId) + 1;

        $versionRepo->create([
            'file_id' => (string) $fileId,
            'version_number' => (string) $currentVersionNumber,
            'content_hash' => $file->contentHash,
            'storage_path' => $file->storagePath,
            'size' => (string) $file->size,
            'created_by' => (string) $userId,
        ]);

        $fileRepo->update($fileId, [
            'content_hash' => $version->contentHash,
            'storage_path' => $version->storagePath,
            'size' => (string) $version->size,
        ]);

        if ($request->isAjax()) {
            return $response->json(['success' => true]);
        }

        return $response->redirect(BASE_URL . '/files/' . $fileId . '/versions');
    }

    public function delete(Request $request, Response $response, array $params): Response
    {
        $userId = $this->getUserId($request);
        $fileId = (int) ($params['id'] ?? 0);
        $versionId = (int) ($params['vid'] ?? 0);

        $c = Container::getInstance();
        $versionRepo = $c->get(VersionRepository::class);
        $fileRepo = $c->get(\FileRoll\File\FileRepository::class);

        $file = $fileRepo->findByIdAndOwner($fileId, $userId);
        if ($file === null) {
            return Response::error(404, t('error.file_not_found'));
        }

        $version = $versionRepo->findById($versionId);
        if ($version === null || $version->fileId !== $fileId) {
            return Response::error(404, t('error.version_not_found'));
        }

        $versionRepo->delete($versionId);

        if ($request->isAjax()) {
            return $response->json(['success' => true]);
        }

        return $response->redirect(BASE_URL . '/files/' . $fileId . '/versions');
    }
}