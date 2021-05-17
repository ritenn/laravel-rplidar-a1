<?php

namespace Ritenn\RplidarA1\Interfaces;


use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

interface CommunicationInterface
{
    /**
     * @param string $command
     * @return bool
     */
    public function canPerformCommand(string $command) : bool;

    /**
     * @param $port
     * @param string $command
     * @return bool
     */
    public function sendCommand($port, string $command) : bool;

    /**
     * @param $port
     */
    public function parseData($port) : void;

    /**
     * @param Collection $rawData
     * @return Collection
     */
    public function getHealth(Collection $rawData) : Collection;

    /**
     * @param Collection $rawData
     * @return Collection
     */
    public function getInfo(Collection $rawData) : Collection;

    /**
     * @param Collection $rawData
     * @return Collection|null
     */
    public function getScan(Collection $rawData) : ?Collection;

    /**
     * @param Collection $currentPacket
     * @return Collection|null
     */
    public function getExpressScanLegacy(Collection $currentPacket) : ?Collection;

    /**
     * @param bool $state
     * @return mixed
     */
    public function runMotor(bool $state);
}