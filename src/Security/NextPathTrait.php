<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Security;

use Symfony\Component\HttpFoundation\Request;

trait NextPathTrait
{
    protected function normalizeNextPath(string $candidate): ?string
    {
        // A backslash after the leading slash is normalized to '/' by browsers,
        // so '/\evil.example' is treated as the scheme-relative '//evil.example'.
        if ($candidate === '' || !str_starts_with($candidate, '/') || preg_match('#^/[\\\\/]#', $candidate) === 1) {
            return null;
        }

        return $candidate;
    }

    protected function normalizeTargetPath(Request $request, string $targetUri): ?string
    {
        if ($targetUri === '') {
            return null;
        }

        // Symfony's ExceptionListener saves the absolute URI, which normalizeNextPath()
        // rejects outright, so it has to be reduced to a same-host path first.
        $parts = parse_url($targetUri);
        if ($parts === false) {
            return null;
        }

        $host = $parts['host'] ?? null;
        if ($host !== null && $host !== $request->getHost()) {
            return null;
        }

        $path = (string)($parts['path'] ?? '');
        if (isset($parts['query'])) {
            $path .= '?' . $parts['query'];
        }

        return $this->normalizeNextPath($path);
    }
}
