<?php

namespace Devolon\EazyBreak\Tests\Unit;

use Devolon\EazyBreak\DTOs\EazyBreakResponseDTO;
use Devolon\EazyBreak\Services\CallEazybreakPaymentCreationService;
use Devolon\EazyBreak\Services\CallEazybreakRefundService;
use Devolon\EazyBreak\Services\InitializeClientService;
use Devolon\EazyBreak\Tests\EazyBreakTestCase;
use Devolon\Payment\Models\Transaction;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\WithFaker;
use Mockery\MockInterface;

class CallEazybreakRefundServiceTest extends EazyBreakTestCase
{
    use WithFaker;

    public function testInvoke()
    {
        // Arrange
        $eazybreakTransactionId = $this->faker->randomNumber();

        $initializeClientService = $this->mockInitializeClientService();
        $service = $this->resolveService();

        $expectedResponse = $this->successfulResponse();
        $expectedResult = EazyBreakResponseDTO::fromArray($expectedResponse['data']);

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
        $result = $service($eazybreakTransactionId);

        // Assert
        $this->assertCount(1, $requests);

        /** @var Request $sentRequest */
        $sentRequest = $requests[0]['request'];
        $this->assertEquals("$eazybreakTransactionId/rollback", $sentRequest->getUri()->getPath());
        $this->assertEquals("", $sentRequest->getBody()->getContents());
        $this->assertEquals($expectedResult, $result);
    }

    private function mockInitializeClientService(): MockInterface
    {
        return $this->mock(InitializeClientService::class);
    }

    private function resolveService(): CallEazybreakRefundService
    {
        return resolve(CallEazybreakRefundService::class);
    }

    private function successfulResponse(): array
    {
        $json = <<<JSON
{
  "status": 200,
  "type": "onlinepayment",
  "operation": "rollback",
  "count": 1,
  "data": {
    "id": 3177,
    "token": "5c5d78f4644fc81a02447babab7c7c21cdda6634",
    "url": "http:\/\/demo.eazybreak.com\/onlinepayment\/webpay?payment=5c5d78f4644fc81a02447babab7c7c21cdda6634",
    "payment_id": "10",
    "created": 1638279998,
    "changed": "2021-12-01 09:46:00",
    "status": "rolledback",
    "value": "69.00"
  }
}
JSON;

        return json_decode($json, true);
    }
}