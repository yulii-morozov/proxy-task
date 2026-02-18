<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\ContentModificationRequest;
use App\DTO\ProxyRequest;
use App\DTO\ProxyResponse;
use App\Service\ContentModifier\ContentModifierInterface;
use App\Service\ContentModifier\CssModifier;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ProxyService
{
    private const MAX_CONTENT_SIZE = 10485760;
    private const REQUEST_TIMEOUT = 30;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ContentModifierInterface $contentModifier,
        private readonly CssModifier $cssModifier,
        private readonly LoggerInterface $logger,
        private readonly string $defaultTarget
    ) {
    }

    public function fetchAndModify(ProxyRequest $request): ProxyResponse
    {
        $targetUrl = $this->buildTargetUrl($request);

        $this->logger->info('Proxying request', [
            'path' => $request->path,
            'target_url' => $targetUrl,
            'target_host' => $request->targetHost,
        ]);

        try {
            $response = $this->fetchFromTarget($targetUrl);

            $headers = $response->getHeaders(false);
            $statusCode = $response->getStatusCode();
            $contentType = $headers['content-type'][0] ?? 'text/html';

            $this->validateContentSize($headers);

            $content = $response->getContent(false);
            $this->validateContentLength($content);

            $this->logResponse($targetUrl, $statusCode, $contentType, $content, $headers);

            $modifiedContent = $this->modifyContentIfNeeded(
                $content,
                $contentType,
                $targetUrl,
                $request
            );

            return new ProxyResponse(
                content: $modifiedContent,
                statusCode: $statusCode,
                contentType: $contentType,
                headers: $headers
            );

        } catch (TransportExceptionInterface $e) {
            $this->handleTransportError($e, $targetUrl);
        }
    }

    private function fetchFromTarget(string $targetUrl): ResponseInterface
    {
        return $this->httpClient->request('GET', $targetUrl, [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => '*/*',
                'Accept-Language' => 'en-US,en;q=0.9',
            ],
            'timeout' => self::REQUEST_TIMEOUT,
            'max_duration' => self::REQUEST_TIMEOUT,
        ]);
    }

    private function buildTargetUrl(ProxyRequest $request): string
    {
        $cleanPath = ltrim($request->path, '/');

        if ($request->targetHost) {
            $baseUrl = 'https://' . $request->targetHost;
        } else {
            $baseUrl = rtrim($this->defaultTarget, '/');
        }

        return $cleanPath !== '' ? $baseUrl . '/' . $cleanPath : $baseUrl;
    }

    private function validateContentSize(array $headers): void
    {
        $contentLength = (int) ($headers['content-length'][0] ?? 0);

        if ($contentLength > self::MAX_CONTENT_SIZE) {
            throw new \RuntimeException('Content size exceeds maximum allowed size');
        }
    }

    private function validateContentLength(string $content): void
    {
        if (strlen($content) > self::MAX_CONTENT_SIZE) {
            throw new \RuntimeException('Content size exceeds maximum allowed size');
        }
    }

    private function logResponse(
        string $targetUrl,
        int $statusCode,
        string $contentType,
        string $content,
        array $headers
    ): void {
        $this->logger->info('Got response from target', [
            'target_url' => $targetUrl,
            'status_code' => $statusCode,
            'content_type' => $contentType,
            'content_length' => strlen($content),
            'content_encoding' => $headers['content-encoding'][0] ?? 'none',
        ]);
    }

    private function modifyContentIfNeeded(
        string $content,
        string $contentType,
        string $targetUrl,
        ProxyRequest $request
    ): string {
        if (!$this->shouldModifyContent($contentType, $request->path)) {
            $this->logger->info('Passing through without modification', [
                'content_type' => $contentType,
                'path' => $request->path,
            ]);

            return $content;
        }

        if (str_contains(strtolower($contentType), 'text/css')) {
            return $this->cssModifier->modify(
                $content,
                $request->proxyBaseUrl,
                $request->targetHost
            );
        }

        $modificationRequest = new ContentModificationRequest(
            content: $content,
            contentType: $contentType,
            targetUrl: $targetUrl,
            proxyBaseUrl: $request->proxyBaseUrl,
            subdomainHost: $request->targetHost
        );

        return $this->contentModifier->modify($modificationRequest);
    }

    private function shouldModifyContent(string $contentType, string $path): bool
    {
        $contentTypeLower = strtolower($contentType);

        if (str_contains($contentTypeLower, 'image/') ||
            str_contains($contentTypeLower, 'font/') ||
            str_contains($contentTypeLower, 'application/octet-stream')) {
            return false;
        }

        return str_contains($contentTypeLower, 'text/html') ||
               str_contains($contentTypeLower, 'application/xhtml+xml') ||
               str_contains($contentTypeLower, 'text/css');
    }

    private function handleTransportError(TransportExceptionInterface $exception, string $targetUrl): never
    {
        $this->logger->error('Failed to fetch content', [
            'error' => $exception->getMessage(),
            'target_url' => $targetUrl
        ]);

        throw new \RuntimeException('Failed to fetch content: ' . $exception->getMessage(), 0, $exception);
    }
}