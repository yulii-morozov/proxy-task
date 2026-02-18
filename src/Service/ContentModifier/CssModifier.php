<?php

declare(strict_types=1);

namespace App\Service\ContentModifier;

class CssModifier
{
    private const SUBDOMAIN_MARKER = '_sd_';

    public function modify(string $content, string $proxyBaseUrl, ?string $subdomainHost): string
    {
        if (!$subdomainHost) {
            return $content;
        }

        $pathPrefix = '/' . self::SUBDOMAIN_MARKER . '/' . $subdomainHost;

        return preg_replace_callback(
            '/url\(\s*([\'"]?)(.*?)\1\s*\)/i',
            function ($matches) use ($pathPrefix, $proxyBaseUrl) {
                $quote = $matches[1];
                $url = trim($matches[2]);

                if (empty($url) ||
                    str_starts_with($url, 'data:') ||
                    str_starts_with($url, 'http') ||
                    str_starts_with($url, '//')) {
                    return $matches[0];
                }

                if (str_starts_with($url, '/')) {
                    if (str_contains($url, '/' . self::SUBDOMAIN_MARKER . '/')) {
                        return $matches[0];
                    }

                    $newUrl = rtrim($proxyBaseUrl, '/') . $pathPrefix . $url;
                    return 'url(' . $quote . $newUrl . $quote . ')';
                }

                return $matches[0];
            },
            $content
        );
    }
}