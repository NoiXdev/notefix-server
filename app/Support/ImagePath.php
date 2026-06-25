<?php

namespace App\Support;

class ImagePath
{
    /**
     * A safe image relpath: sharded segments + filename, no traversal.
     * e.g. "5061b1e2/bad1/4fc7/a4f6/e16577f5dca4/x.png".
     */
    public static function isSafe(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/')) {
            return false;
        }
        if (str_contains($path, '..') || str_contains($path, '\\')) {
            return false;
        }

        return (bool) preg_match('#^[A-Za-z0-9][A-Za-z0-9._/-]*$#', $path);
    }
}
