<?php

declare(strict_types=1);

namespace AnyLLM\Tests;

use AnyLLM\Testing\FakeProvider;
use AnyLLM\Messages\UserMessage;
use PHPUnit\Framework\TestCase;

final class SimpleTest extends TestCase
{
    public function test_it_generates_text(): void
    {
        $provider = (new FakeProvider())
            ->willReturn('Hello, World!');

        $response = $provider->generateText(
            model: 'fake-model',
            prompt: 'Say hello',
        );

        $this->assertEquals('Hello, World!', $response->text);
        
        $provider->assertCalled('generateText', fn($params) => 
            $params['prompt'] === 'Say hello'
        );
    }

    public function test_it_handles_chat(): void
    {
        $provider = (new FakeProvider())
            ->willReturn('I am a helpful assistant!');

        $response = $provider->chat(
            model: 'fake-model',
            messages: [UserMessage::create('Hello')],
        );

        $this->assertStringContainsString('assistant', $response->content);
        
        $provider->assertCalledTimes('chat', 1);
    }

    public function test_provider_supports_capabilities(): void
    {
        $provider = new FakeProvider();

        $this->assertTrue($provider->supports('chat'));
        $this->assertTrue($provider->supports('streaming'));
        $this->assertTrue($provider->supports('tools'));
    }
}

