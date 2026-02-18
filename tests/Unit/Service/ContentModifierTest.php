<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ContentModifier;

use App\DTO\ContentModificationRequest;
use App\Service\ContentModifier\ContentModifier;
use App\Service\ContentModifier\HtmlModifier;
use App\Service\UrlRewriter\UrlRewriterInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ContentModifierTest extends TestCase
{
    private ContentModifier $contentModifier;
    private UrlRewriterInterface $urlRewriter;
    private HtmlModifier $htmlModifier;

    protected function setUp(): void
    {
        $this->urlRewriter = $this->createMock(UrlRewriterInterface::class);
        $this->htmlModifier = $this->createMock(HtmlModifier::class);
        
        $this->contentModifier = new ContentModifier(
            $this->htmlModifier,
            $this->urlRewriter,
            new NullLogger()
        );
    }

    public function testModifyImageContentReturnsUnmodified(): void
    {
        $request = new ContentModificationRequest(
            content: 'binary image data',
            contentType: 'image/jpeg',
            targetUrl: 'https://example.com/image.jpg',
            proxyBaseUrl: 'https://proxy.local'
        );

        $result = $this->contentModifier->modify($request);
        
        $this->assertEquals('binary image data', $result);
    }

    public function testModifyVideoContentReturnsUnmodified(): void
    {
        $request = new ContentModificationRequest(
            content: 'binary video data',
            contentType: 'video/mp4',
            targetUrl: 'https://example.com/video.mp4',
            proxyBaseUrl: 'https://proxy.local'
        );

        $result = $this->contentModifier->modify($request);
        
        $this->assertEquals('binary video data', $result);
    }

    public function testModifyPdfContentReturnsUnmodified(): void
    {
        $request = new ContentModificationRequest(
            content: 'pdf data',
            contentType: 'application/pdf',
            targetUrl: 'https://example.com/doc.pdf',
            proxyBaseUrl: 'https://proxy.local'
        );

        $result = $this->contentModifier->modify($request);
        
        $this->assertEquals('pdf data', $result);
    }

    public function testModifyHtmlContentUsesHtmlModifier(): void
    {
        $htmlContent = '<html><body>Test</body></html>';
        $modifiedHtml = '<html><body>Modified</body></html>';
        
        $this->htmlModifier->expects($this->once())
            ->method('modify')
            ->willReturn($modifiedHtml);
        
        $request = new ContentModificationRequest(
            content: $htmlContent,
            contentType: 'text/html',
            targetUrl: 'https://example.com/page.html',
            proxyBaseUrl: 'https://proxy.local'
        );

        $result = $this->contentModifier->modify($request);
        
        $this->assertEquals($modifiedHtml, $result);
    }

    public function testModifyXhtmlContentUsesHtmlModifier(): void
    {
        $htmlContent = '<html><body>Test</body></html>';
        $modifiedHtml = '<html><body>Modified</body></html>';
        
        $this->htmlModifier->expects($this->once())
            ->method('modify')
            ->willReturn($modifiedHtml);
        
        $request = new ContentModificationRequest(
            content: $htmlContent,
            contentType: 'application/xhtml+xml',
            targetUrl: 'https://example.com/page.xhtml',
            proxyBaseUrl: 'https://proxy.local'
        );

        $result = $this->contentModifier->modify($request);
        
        $this->assertEquals($modifiedHtml, $result);
    }

    public function testModifyJsonContentUsesUrlRewriter(): void
    {
        $jsonContent = '{"url": "https://example.com/api"}';
        $modifiedJson = '{"url": "https://proxy.local/api"}';
        
        $this->urlRewriter->expects($this->once())
            ->method('rewriteInText')
            ->with($jsonContent, 'https://proxy.local')
            ->willReturn($modifiedJson);
        
        $request = new ContentModificationRequest(
            content: $jsonContent,
            contentType: 'application/json',
            targetUrl: 'https://example.com/api',
            proxyBaseUrl: 'https://proxy.local'
        );

        $result = $this->contentModifier->modify($request);
        
        $this->assertEquals($modifiedJson, $result);
    }

    public function testModifyTextContentUsesUrlRewriter(): void
    {
        $textContent = 'Visit https://example.com/page';
        $modifiedText = 'Visit https://proxy.local/page';
        
        $this->urlRewriter->expects($this->once())
            ->method('rewriteInText')
            ->with($textContent, 'https://proxy.local')
            ->willReturn($modifiedText);
        
        $request = new ContentModificationRequest(
            content: $textContent,
            contentType: 'text/plain',
            targetUrl: 'https://example.com',
            proxyBaseUrl: 'https://proxy.local'
        );

        $result = $this->contentModifier->modify($request);
        
        $this->assertEquals($modifiedText, $result);
    }

    public function testModifyJavascriptContentUsesUrlRewriter(): void
    {
        $jsContent = 'var url = "https://example.com/api";';
        $modifiedJs = 'var url = "https://proxy.local/api";';
        
        $this->urlRewriter->expects($this->once())
            ->method('rewriteInText')
            ->with($jsContent, 'https://proxy.local')
            ->willReturn($modifiedJs);
        
        $request = new ContentModificationRequest(
            content: $jsContent,
            contentType: 'application/javascript',
            targetUrl: 'https://example.com/script.js',
            proxyBaseUrl: 'https://proxy.local'
        );

        $result = $this->contentModifier->modify($request);
        
        $this->assertEquals($modifiedJs, $result);
    }

    public function testModifyDetectsJsonByContent(): void
    {
        $jsonContent = '{"key": "value"}';
        
        $this->urlRewriter->expects($this->once())
            ->method('rewriteInText')
            ->willReturn($jsonContent);
        
        $request = new ContentModificationRequest(
            content: $jsonContent,
            contentType: 'text/plain',
            targetUrl: 'https://example.com/data',
            proxyBaseUrl: 'https://proxy.local'
        );

        $this->contentModifier->modify($request);
    }

    public function testModifyFallsBackToTextRewriteOnHtmlParsingError(): void
    {
        $htmlContent = '<html><body>Test</body></html>';
        $modifiedText = 'modified text';
        
        $this->htmlModifier->expects($this->once())
            ->method('modify')
            ->willThrowException(new \Exception('Parse error'));
        
        $this->urlRewriter->expects($this->once())
            ->method('rewriteInText')
            ->willReturn($modifiedText);
        
        $request = new ContentModificationRequest(
            content: $htmlContent,
            contentType: 'text/html',
            targetUrl: 'https://example.com/page.html',
            proxyBaseUrl: 'https://proxy.local'
        );

        $result = $this->contentModifier->modify($request);
        
        $this->assertEquals($modifiedText, $result);
    }

    public function testModifyHandlesEmptyContent(): void
    {
        $request = new ContentModificationRequest(
            content: '',
            contentType: 'text/html',
            targetUrl: 'https://example.com/empty',
            proxyBaseUrl: 'https://proxy.local'
        );

        $this->urlRewriter->expects($this->once())
            ->method('rewriteInText')
            ->willReturn('');

        $result = $this->contentModifier->modify($request);
        
        $this->assertEquals('', $result);
    }

    public function testModifyHandlesNonHtmlTextStartingWithoutBracket(): void
    {
        $textContent = 'Plain text without HTML';
        
        $this->urlRewriter->expects($this->once())
            ->method('rewriteInText')
            ->willReturn($textContent);
        
        $request = new ContentModificationRequest(
            content: $textContent,
            contentType: 'text/html',
            targetUrl: 'https://example.com/text',
            proxyBaseUrl: 'https://proxy.local'
        );

        $result = $this->contentModifier->modify($request);
        
        $this->assertEquals($textContent, $result);
    }
}
