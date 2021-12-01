<?php

namespace Devolon\EazyBreak\Services;

use Devolon\EazyBreak\DTOs\EazyBreakResponseDTO;
use function json_encode;

class CallEazybreakPaymentCreationService
{
    public function __construct(private InitializeClientService $initializeClientService)
    {
    }

    public function __invoke(
        int $transactionId,
        float $amount,
        string $successUrl,
        string $cancelUrl
    ): EazyBreakResponseDTO {
        $client = ($this->initializeClientService)();
        $response = $client->request('POST', '', [
                'body' => json_encode([
                    'merchant_location' => config('eazybreak.merchant_location'),
                    'payment_id' => $transactionId,
                    'value' => $amount,
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                ]),
            ])
            ->getBody()
            ->getContents();

        return EazyBreakResponseDTO::fromArray(\json_decode($response, true)['data']);
    }
}
