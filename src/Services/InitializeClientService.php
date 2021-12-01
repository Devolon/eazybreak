<?php

namespace Devolon\EazyBreak\Services;

use GuzzleHttp\Client;

class InitializeClientService
{
    public function __invoke(): Client
    {
        $account = config('eazybreak.account');
        $key = config('eazybreak.key');
        $url = config('eazybreak.url');

        if (!str_ends_with($url, '/')) {
            $url .= '/';
        }

        return new Client([
            'base_uri' => $url,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode(
                        $account . ':' . $key,
                    )
            ]
        ]);
    }
}