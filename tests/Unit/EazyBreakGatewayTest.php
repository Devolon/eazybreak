<?php

namespace Devolon\EazyBreak\Tests\Unit;

use Devolon\EazyBreak\DTOs\EazyBreakResponseDTO;
use Devolon\Payment\Contracts\HasUpdateTransactionData;
use Devolon\Payment\Contracts\PaymentGatewayInterface;
use Devolon\Payment\DTOs\PurchaseResultDTO;
use Devolon\Payment\DTOs\RedirectDTO;
use Devolon\Payment\Models\Transaction;
use Devolon\Payment\Services\GenerateCallbackURLService;
use Devolon\Payment\Services\PaymentGatewayDiscoveryService;
use Devolon\EazyBreak\EazyBreakGateway;
use Devolon\EazyBreak\Services\CallEazybreakService;
use Devolon\EazyBreak\Tests\EazyBreakTestCase;
use Devolon\Payment\Services\SetGatewayResultService;
use Hamcrest\Core\AnyOf;
use Illuminate\Foundation\Testing\WithFaker;
use Mockery\MockInterface;

class EazyBreakGatewayTest extends EazyBreakTestCase
{
    use WithFaker;

    public function testGetName()
    {
        // Arrange
        $gateway = $this->resolveGateway();

        // Act
        $result = $gateway->getName();

        // Assert
        $this->assertEquals('eazybreak', $result);
    }

    public function testItRegisteredAsGateway()
    {
        // Arrange
        $paymentGatewayDiscoveryService = $this->resolvePaymentGatewayDiscoveryService();

        // Act
        $result = $paymentGatewayDiscoveryService->get('eazybreak');

        // Assert
        $this->assertInstanceOf(EazyBreakGateway::class, $result);
        $this->assertInstanceOf(HasUpdateTransactionData::class, $result);
    }

    public function testPurchase()
    {
        // Arrange
        $redirectUrl = $this->faker->url;
        $successUrl = $this->faker->url;
        $failureUrl = $this->faker->url;
        $generateCallbackURLService = $this->mockGenerateCallBackUrlService();
        $callEazybreakService = $this->mockCallEazybreakService();
        $transaction = Transaction::factory()->create(['money_amount' => $this->faker->randomFloat('1')]);
        $amount = number_format($transaction->money_amount, 2, '.', '');
        $easyBreakResponseDTO = new EazyBreakResponseDTO(
            $this->faker->randomNumber(),
            $this->faker->word(),
            $redirectUrl,
            $this->faker->randomNumber(),
            $this->faker->word(),
            $amount,
        );
        $expectedPurchaseResultDTO = PurchaseResultDTO::fromArray([
            'should_redirect' => true,
            'redirect_to' => RedirectDTO::fromArray([
                'redirect_url' => $redirectUrl,
                'redirect_method' => 'GET',
            ]),
        ]);
        $gateway = $this->discoverGateway();

        // Expect
        $generateCallbackURLService
            ->shouldReceive('__invoke')
            ->with($transaction, AnyOf::anyOf([Transaction::STATUS_DONE, Transaction::STATUS_FAILED]))
            ->andReturnUsing(function ($tx, $status) use ($successUrl, $failureUrl) {
                return match ($status) {
                    Transaction::STATUS_DONE => $successUrl,
                    Transaction::STATUS_FAILED => $failureUrl,
                };
            });
        $callEazybreakService->shouldReceive('__invoke')
            ->once()
            ->withArgs([$transaction->id, (float)$amount, $successUrl, $failureUrl])
            ->andReturn($easyBreakResponseDTO);

        // Act
        $result = $gateway->purchase($transaction);

        // Assert
        $this->assertEquals($expectedPurchaseResultDTO, $result);
    }

    public function testVerifySuccessfully()
    {
        // Arrange
        $setGatewayResultService = $this->mockSetGatewayResultService();
        $gateway = $this->discoverGateway();
        $transaction = Transaction::factory()->create();
        $id = $this->faker->word();
        $payment_id = $this->faker->word();
        $value = $this->faker->word();
        $first_name = $this->faker->word();
        $last_name = $this->faker->word();
        $time = $this->faker->word();
        $code = $this->faker->word();
        config(['eazybreak.key' => $this->faker->word()]);
        $message = base64_encode($id) . '|'
            . base64_encode($payment_id) . '|'
            . base64_encode('completed') . '|'
            . base64_encode($value) . '|'
            . base64_encode($first_name) . '|'
            . base64_encode($last_name) . '|'
            . base64_encode($time) .  '|'
            . base64_encode($code);
        $checksum = hash_hmac('sha256', $message, config('eazybreak.key'));
        $data = compact('id', 'payment_id', 'value', 'first_name', 'last_name', 'time', 'code', 'checksum');

        // Expect
        $setGatewayResultService
            ->shouldReceive('__invoke')
            ->with($transaction, 'commit', $data)
            ->once();

        // Act
        $result = $gateway->verify($transaction, $data);

        // Assert
        $this->assertTrue($result);
        $transaction->refresh();
    }

    public function testVerifyFailed()
    {
        // Arrange
        $setGatewayResultService = $this->mockSetGatewayResultService();
        $gateway = $this->discoverGateway();
        $transaction = Transaction::factory()->create();
        $id = $this->faker->word();
        $payment_id = $this->faker->word();
        $value = $this->faker->word();
        $first_name = $this->faker->word();
        $last_name = $this->faker->word();
        $time = $this->faker->word();
        $code = $this->faker->word();
        config(['eazybreak.key' => $this->faker->word()]);
        $message = base64_encode($id) . '|'
            . base64_encode($payment_id) . '|'
            . base64_encode('completed') . '|'
            . base64_encode($value) . '|'
            . base64_encode($first_name) . '|'
            . base64_encode($last_name) . '|'
            . base64_encode($time) .  '|'
            . base64_encode($code);
        $checksum = hash_hmac('sha256', $message, config('eazybreak.key') . '-wrong');
        $data = compact('id', 'payment_id', 'value', 'first_name', 'last_name', 'time', 'code', 'checksum');

        // Expect
        $setGatewayResultService
            ->shouldNotReceive('__invoke');

        // Act
        $result = $gateway->verify($transaction, $data);

        // Assert
        $this->assertFalse($result);
        $transaction->refresh();
    }

    public function testUpdateTransactionDataRulesWithDoneStatus()
    {
        // Arrange
        $gateway = $this->resolveGateway();
        $expected = [
            'id' => [
                'required',
                'string',
            ],
            'payment_id' => [
                'required',
                'string',
            ],
            'value' => [
                'required',
                'numeric',
            ],
            'first_name' => [
                'required',
                'string',
            ],
            'last_name' => [
                'required',
                'string',
            ],
            'time' => [
                'required',
                'integer',
            ],
            'code' => [
                'required',
                'string',
            ],
            'checksum' => [
                'required',
                'string',
            ],
        ];

        // Act
        $result = $gateway->updateTransactionDataRules('done');

        // Assert
        $this->assertEquals($expected, $result);
    }

    public function testUpdateTransactionDataRulesWithFailedStatus()
    {
        // Arrange
        $gateway = $this->resolveGateway();
        $expected = [];

        // Act
        $result = $gateway->updateTransactionDataRules('failed');

        // Assert
        $this->assertEquals($expected, $result);
    }

    private function resolveGateway(): EazyBreakGateway
    {
        return resolve(EazyBreakGateway::class);
    }

    private function resolvePaymentGatewayDiscoveryService(): PaymentGatewayDiscoveryService
    {
        return resolve(PaymentGatewayDiscoveryService::class);
    }

    private function discoverGateway(): PaymentGatewayInterface
    {
        $paymentDiscoveryService = $this->resolvePaymentGatewayDiscoveryService();

        return $paymentDiscoveryService->get('eazybreak');
    }

    private function mockGenerateCallBackUrlService(): MockInterface
    {
        return $this->mock(GenerateCallbackURLService::class);
    }

    private function mockCallEazybreakService(): MockInterface
    {
        return $this->mock(CallEazybreakService::class);
    }

    private function mockSetGatewayResultService(): MockInterface
    {
        return $this->mock(SetGatewayResultService::class);
    }
}
