<?php

namespace Ritenn\RplidarA1\Lidar;


use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Ritenn\RplidarA1\Facades\Memory;
use Ritenn\RplidarA1\Interfaces\CommunicationInterface;
use Ritenn\RplidarA1\Interfaces\LidarCommandInterface;

/**
 * Class Commands
 * @package Ritenn\RplidarA1\Lidar
 *
 * @description this is command center for communication
 * with RPLidar background process @Ritenn\RplidarA1\Main\Process
 */
class Commands implements LidarCommandInterface
{
    /**
     * Check if background Process is active.
     * @var bool
     */
    protected $processActive;

    /**
     * @var CommunicationInterface
     */
    protected $communication;

    /**
     * Commands constructor.
     */
    public function __construct(CommunicationInterface $communication)
    {
        $this->communication = $communication;

        $isActive = Memory::getByKey('process_active');
        $this->processActive = is_null($isActive) ? true : $isActive;
    }

    /**
     * @return bool
     */
    public function startProcess() : bool
    {
        if ( ! is_null( Memory::getByKey('pid') ) )
        {
            $this->stopProcess();
        }

        $log = config('rplidar.debug')['enable'] ? base_path( config('rplidar.debug')['log_path'] ) : '/dev/null';

        $pid = exec('nohup php ' . base_path() . '/artisan process:exec > ' . $log .' 2>&1 & echo $!;', $output, $return);

        return Memory::remember('pid', (int) $pid);
    }

    /**
     * @return bool
     */
    public function stopProcess() : bool
    {
        Memory::remember('process_active', false);
        Memory::forget();

        return ! Memory::getByKey('process_active');
    }

    /**
     * @return bool
     */
    public function openPort() : bool
    {
        $cmd = Memory::getByKey('open_port');

        if (is_null($cmd) && $this->processActive) {

            Memory::remember('open_port', true);
            Memory::remember('close_port', null);

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function closePort() : bool
    {
        $cmd = Memory::getByKey('close_port');

        if (is_null($cmd) && $this->processActive) {
            Memory::remember('close_port', true);
            Memory::remember('open_port', null);

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function runMotor() : bool
    {
        $cmd = Memory::getByKey('run_motor');

        if ( is_null($cmd) && $this->processActive )
        {
            Memory::remember('stop_motor', null);
            Memory::remember('run_motor', true);

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function stopMotor() : bool
    {
        $cmd = Memory::getByKey('stop_motor');

        if ( is_null($cmd) && $this->processActive )
        {
            Memory::remember('run_motor', null);
            Memory::remember('stop_motor', true);

            return true;
        }

        return false;
    }

    /**
     * @return Collection|null
     */
    public function getData() : ?Collection
    {
        return Memory::getByKey('data');
    }

    /**
     * @command (list)
     * - GET_HEALTH
     * - GET_INFO
     * - STOP
     * - SCAN
     * - EXPRESS_SCAN_LEGACY
     *
     * @param string|null $command
     * @return bool
     */
    public function sendCommand(string $command = null) : bool
    {
        if ( is_null(  Memory::getByKey('command') ) && $this->communication->canPerformCommand($command) )
        {
            Memory::remember('last_command', $command);
            Memory::remember('command', $command);

            return true;
        }

        return false;
    }
}