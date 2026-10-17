<?php

namespace App\Providers;

use App\Http\Middleware\EnsureAccountActive;
use App\Listeners\AwardLearningPoints;
use App\Support\Sms\BeemSmsGateway;
use App\Support\Sms\LogSmsGateway;
use App\Support\Sms\SmsGateway;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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
        // Re-check account status on every Livewire request, not only page loads.
        Livewire::addPersistentMiddleware([EnsureAccountActive::class]);

        Event::subscribe(AwardLearningPoints::class);
    }
}
