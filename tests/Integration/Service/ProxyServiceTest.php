<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\DTO\ProxyRequest;
use App\Service\ContentModifier\ContentModifierInterface;
use App\Service\ContentModifier\CssModifier;
use App\Service\ProxyService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ProxyServiceTest extends TestCase
{
    private ContentModifierInterface&MockObject $contentModifier;
    private CssModifier $cssModifier;
    private string $defaultTarget = 'https://example.com';

    protected function setUp(): void
    {
        $this->contentModifier = $this->createMock(ContentModifierInterface::class);
        $this->cssModifier = new CssModifier();
    }

    private function makeService(MockHttpClient $httpClient): ProxyService
    {
        return new ProxyService(
            $httpClient,
            $this->contentModifier,
            $this->cssModifier,
            new NullLogger(),
            $this->defaultTarget,
        );
    }

    private function makeHtmlResponse(string $content, int $statusCode = 200): MockResponse
    {
        return new MockResponse($content, [
            'http_code' => $statusCode,
            'response_headers' => ['content-type' => 'text/html'],
        ]);
    }

    public function testFetchAndModifySuccessfulRequest(): void
    {
        $htmlContent = '<html><body>Test</body></html>';
        $modifiedContent = '<html><body>Modified</body></html>';

        $httpClient = new MockHttpClient(new MockResponse($htmlContent, [
            'http_code' => 200,
            'response_headers' => [
                'content-type' => 'text/html',
                'content-length' => strlen($htmlContent),
            ],
        ]));

        $this->contentModifier->expects($this->once())
            ->method('modify')
            ->willReturn($modifiedContent);

        $response = $this->makeService($httpClient)->fetchAndModify(
            new ProxyRequest(path: 'page.html', proxyBaseUrl: 'https://proxy.local')
        );

        $this->assertSame($modifiedContent, $response->content);
        $this->assertSame(200, $response->statusCode);
        $this->assertSame('text/html', $response->contentType);
    }

    public function testFetchAndModifyWithCustomTargetHost(): void
    {
        $htmlContent = '<html><body>Test</body></html>';

        $httpClient = new MockHttpClient(new MockResponse($htmlContent, [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'text/html'],
        ]));

        $this->contentModifier->expects($this->once())
            ->method('modify')
            ->willReturn($htmlContent);

        $response = $this->makeService($httpClient)->fetchAndModify(
            new ProxyRequest(path: 'page.html', proxyBaseUrl: 'https://proxy.local', targetHost: 'custom.example.com')
        );

        $this->assertSame(200, $response->statusCode);
    }

    public function testFetchAndModifySkipsModificationForImages(): void
    {
        $imageData = 'binary image data';

        $httpClient = new MockHttpClient(new MockResponse($imageData, [
            'http_code' => 200,
            'response_headers' => [
                'content-type' => 'image/jpeg',
                'content-length' => strlen($imageData),
            ],
        ]));

        $this->contentModifier->expects($this->never())->method('modify');

        $response = $this->makeService($httpClient)->fetchAndModify(
            new ProxyRequest(path: 'image.jpg', proxyBaseUrl: 'https://proxy.local')
        );

        $this->assertSame($imageData, $response->content);
    }

    public function testFetchAndModifySkipsModificationForFonts(): void
    {
        $fontData = 'binary font data';

        $httpClient = new MockHttpClient(new MockResponse($fontData, [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'font/woff2'],
        ]));

        $this->contentModifier->expects($this->never())->method('modify');

        $response = $this->makeService($httpClient)->fetchAndModify(
            new ProxyRequest(path: 'font.woff2', proxyBaseUrl: 'https://proxy.local')
        );

        $this->assertSame($fontData, $response->content);
    }

    public function testFetchAndModifyThrowsExceptionForOversizedContent(): void
    {
        $largeContent = str_repeat('x', 10485761);

        $httpClient = new MockHttpClient(new MockResponse($largeContent, [
            'http_code' => 200,
            'response_headers' => [
                'content-type' => 'text/html',
                'content-length' => strlen($largeContent),
            ],
        ]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Content size exceeds maximum allowed size');

        $this->makeService($httpClient)->fetchAndModify(
            new ProxyRequest(path: 'large.html', proxyBaseUrl: 'https://proxy.local')
        );
    }

    public function testFetchAndModifyHandlesTransportException(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('', [
            'http_code' => 0,
            'error' => 'Connection timeout',
        ]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch content');

        $this->makeService($httpClient)->fetchAndModify(
            new ProxyRequest(path: 'timeout.html', proxyBaseUrl: 'https://proxy.local')
        );
    }

    public function testFetchAndModifyPreservesStatusCode(): void
    {
        $htmlContent = '<html><body>Not Found</body></html>';

        $httpClient = new MockHttpClient(new MockResponse($htmlContent, [
            'http_code' => 404,
            'response_headers' => ['content-type' => 'text/html'],
        ]));

        $this->contentModifier->expects($this->once())
            ->method('modify')
            ->willReturn($htmlContent);

        $response = $this->makeService($httpClient)->fetchAndModify(
            new ProxyRequest(path: 'notfound.html', proxyBaseUrl: 'https://proxy.local')
        );

        $this->assertSame(404, $response->statusCode);
    }

    public function testFetchAndModifyPreservesHeaders(): void
    {
        $htmlContent = '<html><body>Test</body></html>';

        $httpClient = new MockHttpClient(new MockResponse($htmlContent, [
            'http_code' => 200,
            'response_headers' => [
                'content-type' => 'text/html',
                'cache-control' => 'max-age=3600',
                'etag' => '"abc123"',
            ],
        ]));

        $this->contentModifier->expects($this->once())
            ->method('modify')
            ->willReturn($htmlContent);

        $response = $this->makeService($httpClient)->fetchAndModify(
            new ProxyRequest(path: 'page.html', proxyBaseUrl: 'https://proxy.local')
        );

        $this->assertArrayHasKey('cache-control', $response->headers);
        $this->assertArrayHasKey('etag', $response->headers);
    }

    public function testFetchAndModifySkipsModificationForJavascript(): void
    {
        $jsContent = 'var x = 1;';

        $httpClient = new MockHttpClient(new MockResponse($jsContent, [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/javascript'],
        ]));

        $this->contentModifier->expects($this->never())->method('modify');

        $response = $this->makeService($httpClient)->fetchAndModify(
            new ProxyRequest(path: 'script.js', proxyBaseUrl: 'https://proxy.local')
        );

        $this->assertSame($jsContent, $response->content);
    }

    public function testFetchAndModifyUsesCssModifierForCssContent(): void
    {
        $cssContent = 'body { background: url(/image.png); }';

        $httpClient = new MockHttpClient(new MockResponse($cssContent, [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'text/css'],
        ]));

        $this->contentModifier->expects($this->never())->method('modify');

        $response = $this->makeService($httpClient)->fetchAndModify(
            new ProxyRequest(path: 'style.css', proxyBaseUrl: 'https://proxy.local', targetHost: 'example.com')
        );

        $this->assertSame(200, $response->statusCode);
    }

    public function testFetchAndModifyBuildsCorrectTargetUrl(): void
    {
        $htmlContent = '<html><body>Test</body></html>';

        $callback = function (string $method, string $url) use ($htmlContent): MockResponse {
            $this->assertSame('https://example.com/test/path', $url);

            return new MockResponse($htmlContent, [
                'http_code' => 200,
                'response_headers' => ['content-type' => 'text/html'],
            ]);
        };

        $this->contentModifier->expects($this->once())
            ->method('modify')
            ->willReturn($htmlContent);

        $this->makeService(new MockHttpClient($callback))->fetchAndModify(
            new ProxyRequest(path: 'test/path', proxyBaseUrl: 'https://proxy.local')
        );
    }
}