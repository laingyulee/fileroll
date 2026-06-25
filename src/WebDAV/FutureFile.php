<?php

declare(strict_types=1);

namespace FileRoll\WebDAV;

use Sabre\DAV;

class FutureFile implements DAV\IFile
{
    private UploadDirectoryNode $folder;

    public function __construct(UploadDirectoryNode $folder)
    {
        $this->folder = $folder;
    }

    public function getFolder(): UploadDirectoryNode
    {
        return $this->folder;
    }

    public function put($data): void
    {
        throw new DAV\Exception\Forbidden('Permission denied to put into this file');
    }

    public function get()
    {
        $chunks = [];
        foreach ($this->folder->getChildren() as $child) {
            if ($child instanceof UploadFileNode && $child->getName() !== '.file') {
                $chunks[] = $child;
            }
        }
        usort($chunks, fn($a, $b) => strcmp($a->getName(), $b->getName()));

        $tmp = tmpfile();
        if ($tmp === false) {
            throw new DAV\Exception\ServiceUnavailable('Could not create temporary file');
        }

        foreach ($chunks as $chunk) {
            $in = $chunk->get();
            if (is_resource($in)) {
                rewind($in);
                stream_copy_to_stream($in, $tmp);
                fclose($in);
            } else {
                fwrite($tmp, $in);
            }
        }

        rewind($tmp);
        return $tmp;
    }

    public function getContentType(): ?string
    {
        return 'application/octet-stream';
    }

    public function getETag(): ?string
    {
        return $this->folder->getETag();
    }

    public function getSize(): int
    {
        $size = 0;
        foreach ($this->folder->getChildren() as $child) {
            if ($child instanceof UploadFileNode && $child->getName() !== '.file') {
                $size += $child->getSize();
            }
        }
        return $size;
    }

    public function delete(): void
    {
        $this->folder->delete();
    }

    public function getName(): string
    {
        return '.file';
    }

    public function setName($name): void
    {
        throw new DAV\Exception\Forbidden('Permission denied to rename this file');
    }

    public function getLastModified(): ?int
    {
        return $this->folder->getLastModified();
    }
}
