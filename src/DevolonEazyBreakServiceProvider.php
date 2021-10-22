<?php

namespace Devolon\EazyBreak;

use Devolon\Payment\Contracts\PaymentGatewayInterface;
use Illuminate\Support\ServiceProvider;

class DevolonEazyBreakServiceProvider extends ServiceProvider
{
    public function register()
    {
        if (env('IS_EAZYBREAK_AVAILABLE', false)) {
            $this->app->tag(EazyBreakGateway::class, PaymentGatewayInterface::class);
        }

        $this->mergeConfigFrom(__DIR__ . '/../config/eazybreak.php', 'eazybreak');
        $this->publishes([
            __DIR__ . '/../config/eazybreak.php' => config_path('eazybreak.php')
        ], 'eazybreak-config');
    }
}
