<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiCi3\Support;

final class Headers
{
    /**
     * @param array<string,mixed>|null $server
     * @return array<string,string>
     */
    public static function fromGlobals(?array $server = null): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers) && $headers !== []) {
                return self::normalize($headers);
            }
        }

        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (is_array($headers) && $headers !== []) {
                return self::normalize($headers);
            }
        }

        $source = $server ?? $_SERVER;
        $headers = [];

        foreach ($source as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string) $value;
                continue;
            }

            if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $name = strtolower(str_replace('_', '-', $key));
                $headers[$name] = (string) $value;
            }
        }

        return $headers;
    }

    /**
     * @param array<string,mixed> $headers
     * @return array<string,string>
     */
    public static function normalize(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $normalized[strtolower($key)] = (string) $value;
        }

        return $normalized;
    }
}
