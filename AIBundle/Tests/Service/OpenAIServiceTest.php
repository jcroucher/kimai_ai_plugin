<?php

declare(strict_types=1);

namespace KimaiPlugin\AIBundle\Tests\Service;

use App\Configuration\SystemConfiguration;
use KimaiPlugin\AIBundle\Service\OpenAIService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OpenAIServiceTest extends TestCase
{
    private SystemConfiguration $configuration;
    private OpenAIService $service;

    protected function setUp(): void
    {
        $this->configuration = $this->createMock(SystemConfiguration::class);
    }

    public function testIsConfiguredReturnsFalseWhenNoApiKey(): void
    {
        $this->configuration->method('find')->willReturn(null);
        $httpClient = new MockHttpClient();
        
        $this->service = new OpenAIService($httpClient, $this->configuration);
        
        $this->assertFalse($this->service->isConfigured());
    }

    public function testIsConfiguredReturnsTrueWhenApiKeyExists(): void
    {
        $this->configuration->method('find')->willReturn('test-api-key');
        $httpClient = new MockHttpClient();
        
        $this->service = new OpenAIService($httpClient, $this->configuration);
        
        $this->assertTrue($this->service->isConfigured());
    }

    public function testChatThrowsExceptionWhenNotConfigured(): void
    {
        $this->configuration->method('find')->willReturn(null);
        $httpClient = new MockHttpClient();
        
        $this->service = new OpenAIService($httpClient, $this->configuration);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OpenAI API key not configured');
        
        $this->service->chat('Hello');
    }

    public function testChatReturnsValidResponse(): void
    {
        $this->configuration->method('find')->willReturn('test-api-key');
        
        $mockResponse = new MockResponse(json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => 'Hello! How can I help you with time tracking?'
                    ]
                ]
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 15,
                'total_tokens' => 25
            ]
        ]));
        
        $httpClient = new MockHttpClient($mockResponse);
        $this->service = new OpenAIService($httpClient, $this->configuration);
        
        $result = $this->service->chat('Hello');
        
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('usage', $result);
        $this->assertEquals('Hello! How can I help you with time tracking?', $result['content']);
    }

    public function testParseTimelogReturnsStructuredData(): void
    {
        $this->configuration->method('find')->willReturn('test-api-key');
        
        $expectedEntries = [
            [
                'date' => '2024-01-15',
                'start_time' => '09:00',
                'end_time' => '10:30',
                'duration' => 90,
                'description' => 'Meeting with client',
                'project' => 'Website Redesign',
                'client' => 'Acme Corp',
                'billable' => true,
                'rate' => 90
            ]
        ];
        
        $mockResponse = new MockResponse(json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode($expectedEntries)
                    ]
                ]
            ]
        ]));
        
        $httpClient = new MockHttpClient($mockResponse);
        $this->service = new OpenAIService($httpClient, $this->configuration);
        
        $result = $this->service->parseTimelog('9-10:30am - Meeting with client for Website Redesign at Acme Corp');
        
        $this->assertEquals($expectedEntries, $result);
    }

    public function testParseTimelogThrowsExceptionOnInvalidJson(): void
    {
        $this->configuration->method('find')->willReturn('test-api-key');
        
        $mockResponse = new MockResponse(json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => 'Invalid JSON response'
                    ]
                ]
            ]
        ]));
        
        $httpClient = new MockHttpClient($mockResponse);
        $this->service = new OpenAIService($httpClient, $this->configuration);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse AI response as JSON');
        
        $this->service->parseTimelog('Some time log');
    }

    public function testChatWithApiError(): void
    {
        $this->configuration->method('find')->willReturn('invalid-api-key');
        
        $mockResponse = new MockResponse(json_encode([
            'error' => [
                'message' => 'Invalid API key'
            ]
        ]), [
            'http_code' => 401
        ]);
        
        $httpClient = new MockHttpClient($mockResponse);
        $this->service = new OpenAIService($httpClient, $this->configuration);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OpenAI API error: Invalid API key');
        
        $this->service->chat('Hello');
    }
}