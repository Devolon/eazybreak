<?php

namespace Devolon\EazyBreak\Services;

use Devolon\EazyBreak\DTOs\EazyBreakResponseDTO;
use GuzzleHttp\Client;

class CallEazybreakService
{
    private Client $client;
    public function __construct()
    {
        $account = config('eazybreak.account');
        $key = config('eazybreak.key');
        $this->client = new Client([
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode(
                    $account . ':' . $key,
                )
            ]
        ]);
    }

    public function __invoke(
        int $transactionId,
        float $amount,
        string $successUrl,
        string $cancelUrl
    ): EazyBreakResponseDTO {
        $response = $this->client->request('POST', config('eazybreak.url'), [
                'body' => \json_encode([
                    'merchant_location' => config('eazybreak.merchant_location'),
                    'payment_id' => $transactionId,
                    'value' => $amount,
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                ]),
            ])
            ->getBody()
            ->getContents();

        return EazyBreakResponseDTO::fromArray(\json_decode($response, true)['data'], true);
    }
}
