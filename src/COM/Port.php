<?php

namespace Ritenn\RplidarA1\COM;


use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Ritenn\RplidarA1\Interfaces\PortInterface;

class Port implements PortInterface
{
    /**
     * Config port name
     * @var $portName
     */
    public $portName;

    /**
     * Communication constructor.
     */
    public function __construct()
    {
        $this->portName = config('rplidar.port');
    }

    /**
     * @return bool
     */
    public function configure() : bool
    {
        $commands = collect(config('rplidar.stty'))
            ->map(function($value, $command) {

                return ( is_null($value) || $value ? '' : '-' ) . $command;
            })
            ->values()
            ->implode(' ');

        return $this->exec('stty -F ' . $this->portName . ' ' . $commands)
            ->get('status');
    }


    public function open()
    {
        if ( $this->configure() )
        {
            throw new \Exception('Cannot configure port.');
        }

        $portResource = fopen($this->portName, "rb+");

        if( ! $portResource ) {

            return null;
        }

        stream_set_timeout($portResource, 0);
        stream_set_blocking($portResource, false);

        return $portResource;
    }

    /**
     * @param $portResource
     * @return bool
     */
    public function close($portResource) : bool
    {
        return fclose($portResource);
    }

    /**
     * @param $portResource
     * @return string|null
     */
    public function read($portResource) : ?string
    {
        if ( ! $portResource || ! is_resource($portResource ) ) {
            return null;
        }

        return fread($portResource, 8192);
    }

    /**
     * @param $data
     * @param $portResource
     * @return int|null
     */
    public function write($portResource, $data) : ?int
    {
        if ( ! $portResource ) {

            return null;
        }

        $bytes = fwrite($portResource, $data);
        fflush($portResource);

        return $bytes;
    }

    /**
     * @param $command
     * @return Collection
     */
    public function exec($command) : Collection
    {
        $exec = exec($command, $output, $status);

        return collect([
            'last_line'   => $exec,
            'output'      => $output,
            'status'      => $status
        ]);
    }

}