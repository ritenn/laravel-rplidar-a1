<?php

namespace Ritenn\RplidarA1\Facades;


use Illuminate\Support\Facades\Facade;
use Ritenn\RplidarA1\Interfaces\LidarCommandInterface;

class LidarCommands extends Facade
{

    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return LidarCommandInterface::class;
    }
}