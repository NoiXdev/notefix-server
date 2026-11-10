<?php

namespace App\Providers;

use App\Models\PassportClient;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        URL::forceScheme('https');

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::useClientModel(PassportClient::class);

        // Passport 13 ships no authorization (consent) view and binds the
        // AuthorizationViewResponse contract only when a view is registered here.
        // Without this, AuthorizationController@authorize cannot resolve its
        // method dependencies and every /oauth/authorize request 500s — even for
        // first-party clients that ultimately skip the consent screen.
        Passport::authorizationView('oauth.authorize');
    }
}
