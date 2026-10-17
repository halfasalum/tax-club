<?php

namespace App\Providers;

use App\Support\Sms\BeemSmsGateway;
use App\Support\Sms\LogSmsGateway;
use App\Support\Sms\SmsGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SmsGateway::class, fn (): SmsGateway => match (config('services.sms.driver')) {
            'beem' => new BeemSmsGateway(config('services.beem')),
            default => new LogSmsGateway,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
