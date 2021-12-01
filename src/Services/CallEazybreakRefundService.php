<?php

namespace Devolon\EazyBreak\Services;

use Devolon\EazyBreak\DTOs\EazyBreakResponseDTO;
use function json_encode;

class CallEazybreakRefundService
{
    public function __construct(private InitializeClientService $initializeClientService)
    {
    }

    public function __invoke(int $eazybreakTransactionId): EazyBreakResponseDTO
    {
        $client = ($this->initializeClientService)();
        $response = $client->request('POST', "$eazybreakTransactionId/rollback")
            ->getBody()
            ->getContents();

        return EazyBreakResponseDTO::fromArray(\json_decode($response, true)['data']);
    }
}
