<?php

namespace App\Providers;

use App\Models\Analytics;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Video;
use App\Policies\AnalyticsPolicy;
use App\Policies\CampaignPolicy;
use App\Policies\UserPolicy;
use App\Policies\VideoPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Campaign::class => CampaignPolicy::class,
        Video::class => VideoPolicy::class,
        User::class => UserPolicy::class,
        Analytics::class => AnalyticsPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}