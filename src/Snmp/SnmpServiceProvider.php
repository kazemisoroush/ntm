<?php

namespace Ntcm\Snmp;

use Illuminate\Support\ServiceProvider;
use Ntcm\Snmp\Model\SnmpCredential;
use Ntcm\Snmp\Observers\SnmpCredentialObserver;

class SnmpServiceProvider extends ServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var boolean
     */
    protected $defer = false;

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // register model observers...
        SnmpCredential::observe(SnmpCredentialObserver::class);

        // publish configuration files...
        $config = __DIR__ . '/../../config/snmp.php';
        $this->publishes([
            $config => config_path('snmp.php'),
        ], 'config');

        // register migrations...
        $migrations = __DIR__ . '/migrations/';
        $this->loadMigrationsFrom($migrations);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Snmp::class, function () {
            return new Snmp();
        });

        $this->app->alias(Snmp::class, 'snmp');
    }
}
