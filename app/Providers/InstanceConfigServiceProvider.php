<?php

namespace App\Providers;

use App\Mail\Transport\PostalTransport;
use App\Support\InstanceConfig;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class InstanceConfigServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Mail::extend('postal', fn (array $config) => new PostalTransport(
            $config['endpoint'] ?? '',
            $config['key'] ?? '',
        ));

        // Apply DB-backed mail settings over the .env defaults. Guarded so a
        // fresh install (no table) or a bad value never white-screens the app.
        try {
            if (Schema::hasTable('instance_settings')) {
                InstanceConfig::applyMail();
                InstanceConfig::applyFilesystem();
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
