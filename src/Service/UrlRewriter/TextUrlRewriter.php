<?php

declare(strict_types=1);

namespace App\Service\UrlRewriter;

use App\DTO\UrlRewriteContext;

final class TextUrlRewriter
{
    private const URL_ATTRIBUTES = ['href', 'src', 'action', 'data', 'poster', 'srcset'];

    public function __construct(
        private readonly array $proxyTargets,
    ) {
    }

    public function rewrite(string $content, string $proxyBaseUrl): string
    {
        $content = $this->unwrapCachedUrls($content);

        foreach ($this->proxyTargets as $target) {
            $content = $this->rewriteHostUrls($content, $target, $proxyBaseUrl);
        }

        return $content;
    }

    private function unwrapCachedUrls(string $content): string
    {
        return preg_replace_callback(
            '/cached\.php\?(?:[^"\'\s>]*&)?f=([^&"\'\s>]+)/i',
            fn($m) => urldecode($m[1]),
            $content
        );
    }

    private function rewriteHostUrls(string $content, string $target, string $proxyBaseUrl): string
    {
        $parsed = parse_url($target);
        $host = $parsed['host'] ?? '';
        if (empty($host)) {
            return $content;
        }

        $hostBase = preg_replace('/^www\./', '', $host);
        $quoted = preg_quote($hostBase, '#');

        $patterns = [
            '#https?://(www\.)?' . $quoted . '(/[^\s"\'<>\)};,]*?)(?=["\'<>\)};,\s]|$)#i',
            '#//(www\.)?' . $quoted . '(/[^\s"\'<>\)};,]*?)(?=["\'<>\)};,\s]|$)#i',
            '#https?:?\\\/\\\/+(www\.)?' . $quoted . '((?:\\\/|[^\s"\'<>\)};,])*)#i',
        ];

        foreach ($patterns as $index => $pattern) {
            $content = preg_replace_callback(
                $pattern,
                function ($matches) use ($proxyBaseUrl, $index) {
                    $url = $matches[0];
                    if (str_starts_with($url, $proxyBaseUrl)) {
                        return $url;
                    }

                    if ($index === 2) {
                        $cleanPath = str_replace('\/', '/', $matches[2] ?? $matches[3] ?? '');
                        return str_replace('/', '\/', rtrim($proxyBaseUrl, '/') . $cleanPath);
                    }

                    return rtrim($proxyBaseUrl, '/') . ($matches[2] ?? '');
                },
                $content
            );
        }

        return $content;
    }

    public function rewriteUrl(string $url, UrlRewriteContext $context): string
    {
        if (empty($url) || $url[0] === '#') {
            return $url;
        }

        if (preg_match('/^(data:|mailto:|tel:|javascript:)/i', trim($url))) {
            return $url;
        }

        $url = trim($url);

        if (str_contains($url, 'cached.php')) {
            $url = $this->unwrapCachedUrl($url);
        }

        if (str_contains($url, '://') || str_starts_with($url, '//')) {
            return $this->rewrite($url, $context->proxyBaseUrl);
        }

        if (str_starts_with($url, '/')) {
            return rtrim($context->proxyBaseUrl, '/') . $url;
        }

        return rtrim($context->proxyBaseUrl, '/') . '/' . ltrim($url, './');
    }

    private function unwrapCachedUrl(string $url): string
    {
        $parsed = parse_url($url);
        if (!isset($parsed['query'])) {
            return $url;
        }

        parse_str($parsed['query'], $params);

        return !empty($params['f']) ? urldecode((string)$params['f']) : $url;
    }

    public function rewriteSrcset(string $srcset, UrlRewriteContext $context): string
    {
        $sources = preg_split('/,\s*/', $srcset);
        $rewritten = [];

        foreach ($sources as $source) {
            $parts = preg_split('/\s+/', trim($source), 2);
            $url = $parts[0] ?? '';
            $descriptor = $parts[1] ?? '';

            if ($url !== '') {
                $newUrl = $this->rewriteUrl($url, $context);
                $rewritten[] = $descriptor ? "$newUrl $descriptor" : $newUrl;
            }
        }

        return implode(', ', $rewritten);
    }

    public function rewriteInlineStyle(string $style, UrlRewriteContext $context): string
    {
        return preg_replace_callback(
            '/url\(\s*["\']?([^"\')]+)["\']?\s*\)/i',
            function ($m) use ($context) {
                $url = html_entity_decode($m[1]);
                $newUrl = $this->rewriteUrl($url, $context);
                return "url('$newUrl')";
            },
            $style
        );
    }
}