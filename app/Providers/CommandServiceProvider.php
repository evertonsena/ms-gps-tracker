<?php namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Console\Commands\ServerGpsTK103bCommand;

class CommandServiceProvider extends ServiceProvider
{

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('command.server-gps-tk103b.command', function()
        {
            return new ServerGpsTK103bCommand;
        });

        $this->commands(
            'command.server-gps-tk103b.command'
        );
    }
}