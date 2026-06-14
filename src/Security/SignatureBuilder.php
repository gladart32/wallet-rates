<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;

final class SignatureBuilder
{
    public function buildCanonical(Request $request): string
    {
        $method = strtoupper($request->getMethod());
        $path = $request->getPathInfo();
        $canonicalQuery = $this->canonicalizeQuery($request->getQueryString() ?? '');
        $bodyHash = hash('sha256', $request->getContent());

        return $method."\n".$path."\n".$canonicalQuery."\n".$bodyHash;
    }

    public function sign(string $canonical, string $secret): string
    {
        return hash_hmac('sha512', $canonical, $secret);
    }

    public function verify(Request $request, string $secret, string $providedHex): bool
    {
        $expected = $this->sign($this->buildCanonical($request), $secret);

        return is_string($providedHex)
            && $providedHex !== ''
            && hash_equals($expected, strtolower($providedHex));
    }

    private function canonicalizeQuery(string $queryString): string
    {
        if ($queryString === '') {
            return '';
        }

        parse_str($queryString, $parsed);

        $pairs = [];
        foreach ($parsed as $key => $value) {
            if (is_array($value)) {
                $value = '';
            }

            $pairs[] = [
                $this->percentEncode((string) $key),
                $this->percentEncode((string) $value),
            ];
        }

        usort(
            $pairs,
            static fn (array $a, array $b): int => strcmp($a[0], $b[0]) ?: strcmp($a[1], $b[1]),
        );

        $parts = array_map(static fn (array $p): string => $p[0].'='.$p[1], $pairs);

        return implode('&', $parts);
    }

    private function percentEncode(string $value): string
    {
        return rawurlencode($value);
    }
}
