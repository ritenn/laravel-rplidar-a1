<?php

namespace Ritenn\RplidarA1\Main;


use Illuminate\Support\Collection;
use Ritenn\RplidarA1\Facades\Memory;

class SharedMemory {

    /**
     * Array of RPLidar process properties/commands/data
     * @var array
     */
    private $data;

    /**
     * @throws \Exception
     */
    protected final function setInitData() : void
    {
        $data = $this->all();

        if (is_null($data)) {

            $this->data = [
                'stats'          => null,
                'process_active' => false,
                'pid'            => null,
                'data'           => collect([]),
                'time'           => time(), //Just for debug
                'open_port'      => null,
                'close_port'     => null,
                'run_motor'      => null,
                'stop_motor'     => null,
                'scan_mode'      => null,
                'running_scan'   => false,
                'command'        => null,
                'port'           => 'initialized',
                'motor'          => 'initialized',
                'last_command'   => null,
            ];

        } else {

            $this->data = $data;
        }
    }

    /**
     * @param string $key
     * @param $content
     * @return bool
     */
    public function remember(string $key, $content) : bool
    {
        $signal = sem_get(config('rplidar.memory_key'));

        if ( sem_acquire($signal) ) {
            $this->setInitData();
            $this->data[$key] = $content;

            $size = $this->getSize($this->data);
            $oldData = $this->open(0, 'a', 0);
            $allocatedSize = !is_null($oldData) ? shmop_size($oldData) : -1;

            if (!is_null($oldData)) {
                shmop_close($oldData);
            }

            if ( $size > $allocatedSize ) {
                $this->forget();
                $allocatedSize = $size;
            }

            $shmop = $this->open($allocatedSize);

            if (is_null($shmop)) {
                return false;
            }

            $data = $this->write($shmop, serialize($this->data));
            shmop_close($shmop);
            sem_release($signal);

            return $data;
        }
    }

    /**
     * @return bool
     */
    public function forget() : bool
    {
        $shmop = $this->open(0, 'a', 0);

        if ( is_null($shmop) || shmop_delete($shmop) && shmop_close($shmop) )
        {
            return true;
        }

        return false;
    }

    /**
     * @param string $key
     * @return |null
     * @throws \Exception
     */
    public function getByKey(string $key)
    {
        $signal = sem_get(config('rplidar.memory_key') + 2);

        if ( sem_acquire($signal) ) {

            sem_release($signal);

            return !is_null($this->get()) && isset($this->get()[$key]) ? $this->get()[$key] : null;
        }
    }

    /**
     * @return Collection|null
     * @throws \Exception
     */
    public function all() : ?Collection
    {
        $signal = sem_get(config('rplidar.memory_key') + 4);

        if ( sem_acquire($signal) ) {

            sem_release($signal);

            return $this->get();
        }
    }

    /**
     * @return Collection|null
     * @throws \Exception
     */
    final private function get() : ?Collection
    {
        $shmop = $this->open(0, 'a', 0);

        if ( is_null($shmop) )
        {
            return null;
        }

        $data = collect($this->read($shmop, shmop_size($shmop)));
        shmop_close($shmop);

        return $data;
    }

    /**
     * @param int $size
     * @param string $mode
     * @param int $permissions
     * @return resource|null
     */
    final private function open(int $size, $mode = 'c', $permissions = 0666)
    {
        try {

            $block = shmop_open(config('rplidar.memory_key'), $mode, $permissions, $size);

        } catch (\Exception $e)
        {
            return null;
        }

        return $block;
    }

    /**
     * @param $shmop
     * @param string $data
     * @param int $offset
     * @return bool
     */
    final private function write($shmop, string $data, int $offset = 0) : bool
    {
        return shmop_write($shmop , $data, $offset) === strlen($data);
    }

    /**
     * @param $shmop
     * @param int $size
     * @param int $offset
     * @return mixed
     * @throws \Exception
     */
    final private function read($shmop, int $size, $offset = 0)
    {
        $block = @shmop_read($shmop, $offset, $size);

        if (!$block)
        {
            throw new \Exception('Couldn\'t read from shared memory block');
        }

        return @unserialize($block);
    }
    
    /**
     * @param $variable
     * @return int
     */
    final private function getSize($variable) : int
    {
        return strlen( serialize($variable) );
    }
}