<?php

declare(strict_types=1);

namespace FileRoll\WebDAV;

final class Sanitizer
{
    public static function filename(string $name): string
    {
        // Reject null bytes
        $name = str_replace("\0", '', $name);
        // Replace path separators
        $name = str_replace(['/', '\\'], '_', $name);
        // Remove control characters
        $name = preg_replace('/[\x00-\x1f\x7f]/', '', $name);
        // Replace parent-directory dot sequences
        $name = preg_replace('/\.{2,}/', '_', $name);
        // Trim dangerous leading/trailing characters
        $name = trim($name, '. ');

        return $name !== '' ? $name : 'unnamed';
    }
}
