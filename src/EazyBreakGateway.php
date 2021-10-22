<?php

namespace Devolon\EazyBreak;

use Devolon\EazyBreak\Services\CallEazybreakService;
use Devolon\Payment\Contracts\HasUpdateTransactionData;
use Devolon\Payment\Contracts\PaymentGatewayInterface;
use Devolon\Payment\DTOs\PurchaseResultDTO;
use Devolon\Payment\DTOs\RedirectDTO;
use Devolon\Payment\Models\Transaction;
use Devolon\Payment\Services\GenerateCallbackURLService;
use Devolon\Payment\Services\SetGatewayResultService;

class EazyBreakGateway implements PaymentGatewayInterface, HasUpdateTransactionData
{
    public const NAME = 'eazybreak';

    public function __construct(
        private GenerateCallbackURLService $generateCallbackURLService,
        private SetGatewayResultService $setGatewayResultService,
        private CallEazybreakService $callEazybreakService,
    ) {
    }

    public function purchase(Transaction $transaction): PurchaseResultDTO
    {
        $failureUrl = ($this->generateCallbackURLService)($transaction, Transaction::STATUS_FAILED);
        $successUrl = ($this->generateCallbackURLService)($transaction, Transaction::STATUS_DONE);
        $amount = number_format($transaction->money_amount, 2, '.', '');
        $url = ($this->callEazybreakService)($transaction->id, $amount, $successUrl, $failureUrl)->url;

        return PurchaseResultDTO::fromArray([
            'should_redirect' => true,
            'redirect_to' => RedirectDTO::fromArray([
                'redirect_url' => $url,
                'redirect_method' => 'GET',
            ])
        ]);
    }

    public function verify(Transaction $transaction, array $data): bool
    {
        $message = base64_encode($data['id']) . '|'
            . base64_encode($data['payment_id']) . '|'
            . base64_encode('completed') . '|'
            . base64_encode($data['value']) . '|'
            . base64_encode($data['first_name']) . '|'
            . base64_encode($data['last_name']) . '|'
            . base64_encode($data['time']) .  '|'
            . base64_encode($data['code']);

        if ($data['checksum'] !== hash_hmac('sha256', $message, config('eazybreak.key'))) {
            return false;
        }

        ($this->setGatewayResultService)($transaction, 'commit', $data);

        return true;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function updateTransactionDataRules(string $newStatus): array
    {
        if ($newStatus !== Transaction::STATUS_DONE) {
            return [];
        }

        return [
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
    }
}
