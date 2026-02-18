<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ContentModifier;

use App\DTO\HtmlModificationRequest;
use App\Service\ContentModifier\HtmlModifier;
use App\Service\UrlRewriter\UrlRewriterInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class HtmlModifierTest extends TestCase
{
    private HtmlModifier $htmlModifier;
    private UrlRewriterInterface $urlRewriter;
    private array $targetDomains;
    private array $mainDomains;

    protected function setUp(): void
    {
        $this->urlRewriter = $this->createMock(UrlRewriterInterface::class);
        $this->targetDomains = ['example.com', 'www.example.com'];
        $this->mainDomains = ['example.com'];
        
        $this->htmlModifier = new HtmlModifier(
            $this->urlRewriter,
            new NullLogger(),
            $this->targetDomains,
            $this->mainDomains
        );
    }

    public function testModifyAddsBaseTag(): void
    {
        $html = '<html><head></head><body>Test</body></html>';
        
        $this->urlRewriter->expects($this->once())
            ->method('rewriteInDom');
        
        $request = new HtmlModificationRequest(
            html: $html,
            targetUrl: 'https://example.com/page',
            proxyBaseUrl: 'https://proxy.local'
        );
        
        $result = $this->htmlModifier->modify($request);
        
        $this->assertStringContainsString('<base href="https://proxy.local/"', $result);
    }

    public function testModifyAddsBaseTagWithSubdomain(): void
    {
        $html = '<html><head></head><body>Test</body></html>';
        
        $this->urlRewriter->expects($this->once())
            ->method('rewriteInDom');
        
        $request = new HtmlModificationRequest(
            html: $html,
            targetUrl: 'https://sub.example.com/page',
            proxyBaseUrl: 'https://proxy.local',
            subdomainHost: 'sub.example.com'
        );
        
        $result = $this->htmlModifier->modify($request);
        
        $this->assertStringContainsString('<base href="https://proxy.local/_sd_/sub.example.com/"', $result);
    }

    public function testModifyReplacesExistingBaseTag(): void
    {
        $html = '<html><head><base href="https://old.example.com/"></head><body>Test</body></html>';
        
        $this->urlRewriter->expects($this->once())
            ->method('rewriteInDom');
        
        $request = new HtmlModificationRequest(
            html: $html,
            targetUrl: 'https://example.com/page',
            proxyBaseUrl: 'https://proxy.local'
        );
        
        $result = $this->htmlModifier->modify($request);
        
        $this->assertStringContainsString('<base href="https://proxy.local/"', $result);
        $this->assertStringNotContainsString('old.example.com', $result);
    }

    public function testModifyInjectsProxyJavaScript(): void
    {
        $html = '<html><head></head><body>Test</body></html>';
        
        $this->urlRewriter->expects($this->once())
            ->method('rewriteInDom');
        
        $request = new HtmlModificationRequest(
            html: $html,
            targetUrl: 'https://example.com/page',
            proxyBaseUrl: 'https://proxy.local'
        );
        
        $result = $this->htmlModifier->modify($request);
        
        $this->assertStringContainsString('window.__PROXY_CONFIG__', $result);
        $this->assertStringContainsString('/assets/js/proxy-rewriter.js', $result);
    }

    public function testModifyIncludesProxyConfigWithCorrectData(): void
    {
        $html = '<html><head></head><body>Test</body></html>';
        
        $this->urlRewriter->expects($this->once())
            ->method('rewriteInDom');
        
        $request = new HtmlModificationRequest(
            html: $html,
            targetUrl: 'https://example.com/page',
            proxyBaseUrl: 'https://proxy.local',
            subdomainHost: 'sub.example.com'
        );
        
        $result = $this->htmlModifier->modify($request);
        
        $this->assertStringContainsString('"proxyBase":"https://proxy.local"', $result);
        $this->assertStringContainsString('"currentSubdomain":"sub.example.com"', $result);
        $this->assertStringContainsString('"subdomainMarker":"_sd_"', $result);
    }

    public function testModifyCallsUrlRewriter(): void
    {
        $html = '<html><head></head><body>Test</body></html>';
        
        $this->urlRewriter->expects($this->once())
            ->method('rewriteInDom')
            ->with(
                $this->isInstanceOf(\DOMDocument::class),
                $this->anything()
            );
        
        $request = new HtmlModificationRequest(
            html: $html,
            targetUrl: 'https://example.com/page',
            proxyBaseUrl: 'https://proxy.local'
        );
        
        $this->htmlModifier->modify($request);
    }

    public function testModifyHandlesEmptyHead(): void
    {
        $html = '<html><body>Test</body></html>';
        
        $this->urlRewriter->expects($this->once())
            ->method('rewriteInDom');
        
        $request = new HtmlModificationRequest(
            html: $html,
            targetUrl: 'https://example.com/page',
            proxyBaseUrl: 'https://proxy.local'
        );
        
        $result = $this->htmlModifier->modify($request);
        
        $this->assertNotEmpty($result);
    }

    public function testModifyHandlesInvalidHtml(): void
    {
        $html = 'Not valid HTML';
        
        $request = new HtmlModificationRequest(
            html: $html,
            targetUrl: 'https://example.com/page',
            proxyBaseUrl: 'https://proxy.local'
        );
        
        $result = $this->htmlModifier->modify($request);
        
        $this->assertEquals($html, $result);
    }

    public function testModifyHandlesException(): void
    {
        $html = '<html><head></head><body>Test</body></html>';
        
        $this->urlRewriter->expects($this->once())
            ->method('rewriteInDom')
            ->willThrowException(new \Exception('Rewrite error'));
        
        $request = new HtmlModificationRequest(
            html: $html,
            targetUrl: 'https://example.com/page',
            proxyBaseUrl: 'https://proxy.local'
        );
        
        $result = $this->htmlModifier->modify($request);
        
        $this->assertEquals($html, $result);
    }

    public function testModifyInsertsScriptsAtBeginningOfHead(): void
    {
        $html = '<html><head><title>Test</title></head><body>Test</body></html>';
        
        $this->urlRewriter->expects($this->once())
            ->method('rewriteInDom');
        
        $request = new HtmlModificationRequest(
            html: $html,
            targetUrl: 'https://example.com/page',
            proxyBaseUrl: 'https://proxy.local'
        );
        
        $result = $this->htmlModifier->modify($request);
        
        $configPos = strpos($result, 'window.__PROXY_CONFIG__');
        $titlePos = strpos($result, '<title>');
        
        $this->assertNotFalse($configPos);
        $this->assertNotFalse($titlePos);
        $this->assertLessThan($titlePos, $configPos);
    }

    public function testModifyHandlesComplexHtml(): void
    {
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Complex Page</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <h1>Title</h1>
        <p>Content</p>
    </div>
    <script src="script.js"></script>
</body>
</html>';
        
        $this->urlRewriter->expects($this->once())
            ->method('rewriteInDom');
        
        $request = new HtmlModificationRequest(
            html: $html,
            targetUrl: 'https://example.com/page',
            proxyBaseUrl: 'https://proxy.local'
        );
        
        $result = $this->htmlModifier->modify($request);
        
        $this->assertStringContainsString('window.__PROXY_CONFIG__', $result);
        $this->assertStringContainsString('<base href=', $result);
    }
}
