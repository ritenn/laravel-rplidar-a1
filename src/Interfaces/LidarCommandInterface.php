<?php

namespace Ritenn\RplidarA1\Interfaces;


use Illuminate\Support\Facades\Cache;

interface LidarCommandInterface
{

    /**
     * @return bool
     */
    public function startProcess() : bool;

    /**
     * @return bool
     */
    public function stopProcess() : bool;

    /**
     * @return bool
     */
    public function openPort() : bool;

    /**
     * @return bool
     */
    public function closePort() : bool;

    /**
     * @return bool
     */
    public function runMotor() : bool;

    /**
     * @return bool
     */
    public function stopMotor() : bool;

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
    public function sendCommand(string $command = null) : bool;
}