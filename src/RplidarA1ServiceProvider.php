<?php

declare(strict_types=1);

namespace Ritenn\RplidarA1;


use Illuminate\Support\ServiceProvider;
use Ritenn\RplidarA1\COM\Port;
use Ritenn\RplidarA1\Commands\ExecMemory;
use Ritenn\RplidarA1\Commands\ExecProcess;
use Ritenn\RplidarA1\Commands\StopProcess;
use Ritenn\RplidarA1\Commands\StartProcess;
use Ritenn\RplidarA1\Interfaces\CommunicationInterface;
use Ritenn\RplidarA1\Interfaces\LidarCommandInterface;
use Ritenn\RplidarA1\Interfaces\PortInterface;
use Ritenn\RplidarA1\Lidar\Commands;
use Ritenn\RplidarA1\Lidar\Communication;
use Ritenn\RplidarA1\Main\SharedMemory;

class RplidarA1ServiceProvider extends ServiceProvider
{
    /**
     * Boot
     *
     * @return void
     */
    public function boot() : void
    {
        $this->setCommands();
        $this->setConfig();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register() : void
    {
        $this->bindings();
    }

    /**
     * Set configs & allows to publish
     */
    private function setConfig() : void
    {
        $vendorPath = __DIR__ . '/Config/rplidar.php';

        $this->publishes([
            $vendorPath  => config_path('rplidar.php')
        ]);

        $this->mergeConfigFrom(
            $vendorPath, 'rplidar'
        );

    }

    /**
     * Set commands
     */
    private function setCommands() : void
    {
        $this->commands([
            ExecProcess::class,
        ]);
    }

    /**
     * Set bindings
     */
    private function bindings() : void
    {
        $this->app->bind(PortInterface::class, Port::class);
        $this->app->bind(CommunicationInterface::class, Communication::class);

        $this->app->bind(LidarCommandInterface::class, function ($app) {
            return new Commands( $app->make(CommunicationInterface::class) );
        });

        $this->app->singleton('SharedMemory', function ($app) {
            return new SharedMemory();
        });
    }
}