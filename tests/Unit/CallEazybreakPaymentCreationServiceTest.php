<?php

namespace Devolon\EazyBreak\Tests\Unit;

use Devolon\EazyBreak\DTOs\EazyBreakResponseDTO;
use Devolon\EazyBreak\Services\CallEazybreakPaymentCreationService;
use Devolon\EazyBreak\Services\InitializeClientService;
use Devolon\EazyBreak\Tests\EazyBreakTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\WithFaker;
use Mockery\MockInterface;

class CallEazybreakPaymentCreationServiceTest extends EazyBreakTestCase
{
    use WithFaker;

    public function testInvoke()
    {
        // Arrange
        $eazybreakMerchantLocation = $this->faker->word;
        config(['eazybreak.merchant_location' => $eazybreakMerchantLocation]);

        $transactionId = $this->faker->randomNumber();
        $amount = $this->faker->randomFloat();
        $successUrl = $this->faker->url;
        $cancelUrl = $this->faker->url;

        $initializeClientService = $this->mockInitializeClientService();
        $service = $this->resolveService();

        $expectedResponse = $this->successfulResponse();
        $expectedResult = EazyBreakResponseDTO::fromArray($expectedResponse['data']);
        $expectedRequestBody = json_encode([
            'merchant_location' => $eazybreakMerchantLocation,
            'payment_id' => $transactionId,
            'value' => $amount,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($expectedResponse)),
        ]);

        $requests = [];
        $history = Middleware::history($requests);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);
        $client = new Client(['handler' => $handlerStack]);


        // Expect
        $initializeClientService
            ->shouldReceive('__invoke')
            ->once()
            ->andReturn($client);

        // Act
        $result = $service($transactionId, $amount, $successUrl, $cancelUrl);

        // Assert
        $this->assertCount(1, $requests);

        /** @var Request $sentRequest */
        $sentRequest = $requests[0]['request'];
        $this->assertEquals('', $sentRequest->getUri()->getPath());
        $this->assertEquals($expectedRequestBody, $sentRequest->getBody()->getContents());
        $this->assertEquals($expectedResult, $result);
    }

    private function mockInitializeClientService(): MockInterface
    {
        return $this->mock(InitializeClientService::class);
    }

    private function resolveService(): CallEazybreakPaymentCreationService
    {
        return resolve(CallEazybreakPaymentCreationService::class);
    }

    private function successfulResponse(): array
    {
        $json = <<<JSON
{
  "status": 200,
  "type": "onlinepayment",
  "operation": "create",
  "count": 1,
  "data": {
    "id": 3183,
    "token": "0da8e039bfeb9602bd096c358c46a665f09176d3",
    "url": "http:\/\/demo.eazybreak.com\/onlinepayment\/webpay?payment=0da8e039bfeb9602bd096c358c46a665f09176d3",
    "payment_id": "11",
    "created": 1638346134,
    "changed": "",
    "status": "created",
    "value": "69.00"
  }
}
JSON;

        return json_decode($json, true);
    }
}