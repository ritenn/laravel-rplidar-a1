<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Debug
    |--------------------------------------------------------------------------
    */
    'debug' => [
        'enable'   => true,
        'log_path' => 'storage/logs/rplidar.log'
    ],
    /*
    |--------------------------------------------------------------------------
    | Shared memory key - u can access it from other process/program if needed.
    |--------------------------------------------------------------------------
    */
    'memory_key' => 0xff2021,
    /*
    |--------------------------------------------------------------------------
    | Assign port
    |--------------------------------------------------------------------------
    */
    'port' => '/dev/ttyUSB0',
    /*
    |--------------------------------------------------------------------------
    | Configure Linux stty
    |--------------------------------------------------------------------------
    |
    | This is the best settings for Raspbery PI 4 and Lidar A1 I've found so far.
    | However you may want to add more parameters, just play with it and add
    | as many as you need.
    |
    | @null  - just sets parameter
    | @false - appends "-" to command which is stty negation
    | @true  - sets parameter as it is
    */
    'stty' => [
        115200    => null, // Baud rate
        'raw'     => null,
        'echo'    => false,
        'echoe'   => false,
        'echok'   => false,
        'echoctl' => false,
        'echoke'  => false,
    ]
];