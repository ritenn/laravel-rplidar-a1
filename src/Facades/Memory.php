<?php

namespace Ritenn\RplidarA1\Facades;


use Illuminate\Support\Facades\Facade;

class Memory extends Facade
{

    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'SharedMemory';
    }
}