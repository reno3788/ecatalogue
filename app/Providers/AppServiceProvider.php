<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use App\Models\AppSetting;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Inject database application configuration overrides
        try {
            if (Schema::hasTable('app_settings')) {
                $setting = AppSetting::first();
                if ($setting && $setting->smtp_host) {
                    config([
                        'mail.default' => 'smtp',
                        'mail.mailers.smtp.host' => $setting->smtp_host,
                        'mail.mailers.smtp.port' => $setting->smtp_port ?: config('mail.mailers.smtp.port'),
                        'mail.mailers.smtp.username' => $setting->smtp_username,
                        'mail.mailers.smtp.password' => $setting->smtp_password, // will auto-decrypt via cast
                        'mail.mailers.smtp.encryption' => $setting->smtp_encryption,
                        'mail.from.address' => $setting->smtp_from_address ?: config('mail.from.address'),
                        'mail.from.name' => $setting->smtp_from_name ?: config('mail.from.name'),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Prevents blocking early bootstrap phase / migrations
        }
    }
}
