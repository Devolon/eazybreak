<?php

namespace Devolon\EazyBreak\Tests\Unit;

use Devolon\EazyBreak\Services\InitializeClientService;
use Devolon\EazyBreak\Tests\EazyBreakTestCase;
use GuzzleHttp\Client;
use Illuminate\Foundation\Testing\WithFaker;

class InitializeClientServiceTest extends EazyBreakTestCase
{
    use WithFaker;

    public function testInvoke()
    {
        // Arrange
        $eazybreakAccount = $this->faker->word;
        $eazybreakKey = $this->faker->word;
        $eazybreakUrl = $this->faker->url;
        config([
            'eazybreak' => [
                'account' => $eazybreakAccount,
                'key' => $eazybreakKey,
                'url' => $eazybreakUrl,
            ],
        ]);
        $service = $this->resolveService();
        $expectedClient = new Client([
            'base_uri' => "$eazybreakUrl/",
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode(
                        $eazybreakAccount . ':' . $eazybreakKey,
                    )
            ],
        ]);

        // Act
        $result = $service();

        // Assert
        $this->assertEquals($expectedClient, $result);
    }

    private function resolveService(): InitializeClientService
    {
        return resolve(InitializeClientService::class);
    }
}