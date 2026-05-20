<?php

namespace App\Providers;

use App\Contracts\Repositories\CommunicationLogRepositoryInterface;
use App\Contracts\Repositories\CommunicationRepositoryInterface;
use App\Contracts\Repositories\NotificationTemplateRepositoryInterface;
use App\Repositories\Eloquent\EloquentCommunicationLogRepository;
use App\Repositories\Eloquent\EloquentCommunicationRepository;
use App\Repositories\Eloquent\EloquentNotificationTemplateRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    private const REPOSITORY_BINDINGS = [
        CommunicationRepositoryInterface::class => EloquentCommunicationRepository::class,
        CommunicationLogRepositoryInterface::class => EloquentCommunicationLogRepository::class,
        NotificationTemplateRepositoryInterface::class => EloquentNotificationTemplateRepository::class,
    ];

    public function register(): void
    {
        foreach (self::REPOSITORY_BINDINGS as $abstract => $concrete) {
            $this->app->bind($abstract, $concrete);
        }
    }

    public function boot(): void
    {
        //
    }
}
