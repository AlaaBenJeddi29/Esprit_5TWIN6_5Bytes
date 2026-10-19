<?php

namespace Tests\Feature\Back;

use Tests\TestCase;

class GroqConfigTest extends TestCase
{
    public function test_the_groq_config_chain_is_exposed_through_services_config(): void
    {
        $this->assertSame('test', config('services.groq.key'));
        $this->assertSame('https://api.groq.com/openai/v1/chat/completions', config('services.groq.url'));
        $this->assertSame('openai/gpt-oss-120b', config('services.groq.model'));
        $this->assertSame(30, config('services.groq.timeout'));
    }

    public function test_the_legacy_ai_and_xai_blocks_are_gone(): void
    {
        $this->assertNull(config('services.ai'));
        $this->assertNull(config('services.xai'));
    }
}
