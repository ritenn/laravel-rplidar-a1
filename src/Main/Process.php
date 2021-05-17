<?php

namespace Ritenn\RplidarA1\Main;


use Illuminate\Support\Facades\Cache;
use Ritenn\RplidarA1\COM\Port;
use Ritenn\RplidarA1\Facades\Memory;
use Ritenn\RplidarA1\Facades\LidarCommands;
use Ritenn\RplidarA1\Interfaces\CommunicationInterface;
use Ritenn\RplidarA1\Interfaces\PortInterface;
use Ritenn\RplidarA1\RplidarA1ServiceProvider;

class Process
{
    /**
     * @var int
     */
    protected $time;

    /**
     * @var CommunicationInterface $lidarCommunication
     */
    protected $lidarCommunication;

    /**
     * @var
     */
    protected $isActive;

    /**
     * @var PortInterface $port
     */
    protected $port;

    /**
     * @var #resource $portResource
     */
    protected $portResource;

    /**
     * Process constructor.
     * @param CommunicationInterface $lidarCommunication
     * @param PortInterface $port
     */
    public function __construct(CommunicationInterface $lidarCommunication, PortInterface $port)
    {
        $this->isActive = true;
        $this->lidarCommunication = $lidarCommunication;
        $this->port = $port;

        $this->main();
    }

    /**
     * Main program loop.
     */
    public function main() : void
    {
        while ( $this->isActive ) {

            if ( config('rplidar.debug')['enable'] )
            {
                Memory::remember('time', time());
            }

            if ($this->shouldOpenPort()) {

                $resource = $this->port->open();

                if ( $resource && is_resource($resource) )
                {
                    $this->portResource = $resource;
                    Memory::remember('port', 'opened');
                }
            }

            if ($this->shouldClosePort()) {
                $this->port->close($this->portResource);
                Memory::remember('port', 'closed');
            }

            if ($this->shouldRunMotor()) {
                $this->lidarCommunication->runMotor(true);
                Memory::remember('motor', 'running');
            }

            if ($this->shouldStopMotor()) {
                $this->lidarCommunication->runMotor(false);
                Memory::remember('motor', 'holding');
            }

            if ( $this->shouldSendCommand() ) {

                if ($this->lidarCommunication->sendCommand($this->portResource, Memory::getByKey('command'))) {
                    Memory::remember('command', null);
                }
            }

            if ( isset($this->portResource) && is_resource($this->portResource) ) {
                $this->lidarCommunication->parseData($this->portResource);
            }

            /**
             * Sometimes there are problems with semaphore and unserialize if it's reading too quickly.
             */
            $isStillActive = Memory::getByKey('process_active');
            $this->isActive = is_null( $isStillActive ) ? true : $isStillActive;
        }

        Memory::forget();
    }

    /**
     * @param string $key
     * @return bool
     */
    public function readAndToggle(string $key) : bool
    {
        $state = Memory::getByKey($key);

        if (!is_null($state) && filter_var($state, FILTER_VALIDATE_BOOLEAN))
        {
            Memory::remember($key, false);
        }

        return is_null($state) ? false : $state;
    }

    /**
     * @return bool|null
     */
    public function shouldOpenPort() : ?bool
    {
        return $this->readAndToggle('open_port');
    }

    /**
     * @return bool|null
     */
    public function shouldClosePort() : ?bool
    {
        return $this->readAndToggle('close_port');
    }

    /**
     * @return bool|null
     */
    public function shouldRunMotor() : ?bool
    {
        return $this->readAndToggle('run_motor');
    }

    /**
     * @return bool|null
     */
    public function shouldStopMotor() : ?bool
    {
        return $this->readAndToggle('stop_motor');
    }

    /**
     * @return bool|null
     */
    public function shouldSendCommand() : ?bool
    {
        return $this->readAndToggle('command');
    }
}