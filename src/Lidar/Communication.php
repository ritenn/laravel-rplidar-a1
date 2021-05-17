<?php

namespace Ritenn\RplidarA1\Lidar;


use Illuminate\Support\Facades\Cache;
use Ritenn\RplidarA1\COM\Port;
use Ritenn\RplidarA1\Facades\Memory;
use Ritenn\RplidarA1\Interfaces\CommunicationInterface;
use Ritenn\RplidarA1\Interfaces\PortInterface;
use Ritenn\RplidarA1\Main\SharedMemory;
use Ritenn\RplidarA1\RplidarA1ServiceProvider;
use Illuminate\Support\Collection;

class Communication implements CommunicationInterface
{
    /**
     * RPLidar hex codes
     */
    CONST FLAGS = [
        'START_FLAG_1' => 0xA5,
        'START_FLAG_2' => 0x5A,
        'RESET' => [0x40],
        'STOP' => [0x25],
        'SCAN' => [0x20],
        'EXPRESS_SCAN_LEGACY' => [0x82, 0x05, 0x00, 0x00, 0x00, 0x00, 0x00, 0x22],
        'GET_HEALTH' => [0x52],
        'GET_INFO' => [0x50],
    ];

    /**
     * Find device manual for more informations.
     *
     * Response decriptors
     */
    CONST DESCRIPTORS = [
        'GET_HEALTH' => [
            'data_builder_method' => 'getHealth',
            'response' => ['a5', '5a', '03', '00', '00', '00', '06'],
            'offsets' => [
                'status' => [0, 1],
                'error_code' => [1, 2],
            ]
        ],
        'GET_INFO' => [
            'data_builder_method' => 'getInfo',
            'response' => ['a5', '5a', '14', '00', '00', '00', '04'],
            'offsets' => [
                'model' => [0, 1],
                'firmware_minor' => [1, 1],
                'firmware_major' => [2, 1],
                'hardware' => [3, 1],
                'serial_number' => [4, 16],
            ]
        ],
        'SCAN' => [
            'data_builder_method' => 'getScan',
            'response' => ['a5', '5a', '05', '00', '00', '40', '81'],
            'offsets' => [
                'quality' => [0, 1],
                'angle' => [1, 2],
                'distance' => [3, 2],
            ]
        ],
        'EXPRESS_SCAN_LEGACY' => [
            'data_builder_method' => 'getExpressScanLegacy',
            'response' => ['a5', '5a', '54', '00', '00', '40', '82'],
            'offsets' => [
                'sync_one' => [0, 1],
                'sync_two' => [1, 1],
                'start_angle_part_one' => [2, 1],
                'start_angle_part_two' => [3, 1],
                'cabins' => [4, 80],
            ]
        ],
    ];

    /**
     * @var PortInterface $port
     */
    protected $port;

    /**
     * Scan vars
     */
    public $publishedMeasurements;
    public $scanMode = null;
    public $cachedBits = null;
    public $previousPacket = null;
    public $hasPreviousPacketCached = false;
    public $isRunningScan = false;
    public $shouldMemorize = false;

    /**
     * Communication constructor.
     * @param PortInterface $port
     */
    public function __construct(PortInterface $port)
    {
        $this->port = $port;
        $this->publishedMeasurements = collect([]);
    }

    /**
     * @param string $command
     * @return bool
     */
    public function canPerformCommand(string $command) : bool
    {
        return in_array($command, array_keys(self::FLAGS));
    }

    /**
     * @param $port
     * @param string $command
     * @return bool
     */
    public function sendCommand($port, string $command) : bool
    {
        if ( $command === 'STOP' )
        {
            $this->setScanMode(null);
        }

        return $this->port->write($port, pack('c*', self::FLAGS['START_FLAG_1'], ... self::FLAGS[$command]) ) > 0;
    }

    /**
     * @param $port
     */
    public final function parseData($port) : void
    {
        $binaryData = $this->port->read($port);
        $binaryData = is_null($this->cachedBits) ? $binaryData : $this->cachedBits . $binaryData;

        if ( ! is_null($binaryData) && $binaryData !== '' )
        {
            $buffor = $this->prepareBuffor($binaryData);

            if ( ! is_null($buffor) ) {

                $bufforSize = count($buffor);

                foreach ($buffor as $key => $data) {

                    if ($key == ($bufforSize - 1)) {
                        $this->shouldMemorize = true;
                    }

                    if ($this->isRunningScan) {

                        $descriptor = self::DESCRIPTORS[$this->scanMode];
                        $response = str_split($data, 2);

                        $data = $this->mapDescriptorOffsets($response, $descriptor);
                        $this->parseMemorizeResponseData($data, $descriptor);

                    } else {

                        $this->descriptor($data);

                    }
                }
            }
        }
    }

    /**
     * @param string $binaryData
     * @return array|null
     */
    private function prepareBuffor(string $binaryData) : ?array
    {
        $this->shouldMemorize = false;
        $bufforChunks = 64;
        $bufforLimit = 500;

        if ( $this->isRunningScan )
        {
            /**
             * Bits length
             * @Scan = 5
             * @Express = 84
             */
            $scanPacketLength = $this->scanMode === 'SCAN' ? 5 : 84;
            /**
             * Hex string length
             * @Scan = 10
             * @Express = 168
             */
            $bufforChunks = $this->scanMode === 'SCAN' ? 10 : 168;

            /**
             * In case of some noise, lets skip that packet.
             */
            if ( strlen($binaryData) < $scanPacketLength )
            {
                return null;
            }

            /**
             * Cache rest over bits.
             */
            $chunksCount = strlen($binaryData) / $scanPacketLength;

            if ( $chunksCount != floor($chunksCount) )
            {
                $bitsRestOver = str_split($binaryData, (int) floor($chunksCount) * $scanPacketLength);
                $binaryData = $bitsRestOver[0];

                $this->cachedBits = $bitsRestOver[1];

            } else {

                $this->cachedBits = null;
            }
        }

        /**
         * From now on, it will be easier to work on hex array.
         */
        $buffor = bin2hex($binaryData);
        $buffor = $this->isRunningScan ? substr($buffor,0, $bufforLimit) : $buffor;

        return str_split($buffor, $bufforChunks);
    }


    /**
     * @param string $data
     * @return Collection
     */
    private function descriptor(string $data) : Collection
    {
        $response = str_split($data, 2);
        $descriptorBytes  = array_slice($response, 0, 7);

        $descriptor = collect(self::DESCRIPTORS)
                        ->filter(function($dictionary, $descriptor) use (&$descriptorBytes) {

                            return $dictionary['response'] === $descriptorBytes;
                        });

        if ( $descriptor->isEmpty() )
        {
            return collect([
                'data' => 'Descriptor error (raw response): ' . $data
            ]);
        }

        $descriptor = $descriptor->first();

        if ( count( array_diff($descriptorBytes, $descriptor['response']) ) === 0 )
        {
            $response = array_slice($response, 7, count($response));

            $data = $this->mapDescriptorOffsets($response, $descriptor);
            $this->parseMemorizeResponseData($data, $descriptor);

        } else {

            return collect([
                'data' => 'Descriptor error (raw response): ' . $data
            ]);
        }

        return collect([
            'data' => $data
        ]);
    }

    /**
     * @param array $response
     * @param array $descriptor
     * @return array
     */
    private function mapDescriptorOffsets(array $response, array $descriptor) : array
    {
        foreach( $descriptor['offsets'] as $key => $offset )
        {
            $bytes = array_slice($response, $offset[0], $offset[1]);

            foreach( $bytes as $byteKey => $byte )
            {
                $bytes[$byteKey] = hexdec($byte);
            }

            $data[$key] = collect($bytes);
        }

        return $data;
    }

    /**
     * @param array $response
     */
    private function parseMemorizeResponseData(array $responseData, array $descriptor) : bool
    {
        $data = call_user_func( [$this, $descriptor['data_builder_method']], collect($responseData));

        if ( $this->shouldMemorize )
        {
            return Memory::remember('data', $data);
        }

        return true;
    }

    /**
     * @param Collection $rawData
     * @return Collection
     */
    public function getHealth(Collection $rawData) : Collection
    {
        $statusList = [
            0 => 'Good',
            1 => 'Warning',
            2 => 'Error'
        ];

        return collect([
                'status' => $statusList[$rawData->get('status')->first()],
                'error' => $rawData->get('error_code')->map(function($value) {
                    return dechex($value);
                })->implode('x')
            ]);
    }

    /**
     * @param Collection $rawData
     * @return Collection
     */
    public function getInfo(Collection $rawData) : Collection
    {
        return collect([
            'model' => $rawData->get('model')->first(),
            'firmware' => $rawData->get('firmware_major')->first() . '.' . $rawData->get('firmware_minor')->first(),
            'hardware' => $rawData->get('hardware')->first(),
            'serial_number' => $rawData->get('serial_number')->map(function($value) {
                    return dechex($value);
                })->implode('')
        ]);
    }

    /**
     * @param Collection $rawData
     * @return Collection|null
     */
    public function getScan(Collection $rawData) : ?Collection
    {
        if ( ! $this->isRunningScan )
        {
            return $this->setScanMode('SCAN');
        }

        $quality = collect($rawData->get('quality'));
        $qualityValue = $quality->first() >> 2;
        $startFlag = $quality->first() & 1;
        $inversedBit = ( $quality->first() & 2 ) >> 1;

        $angleRaw = collect($rawData->get('angle'));
        $angle = ((( $angleRaw->last() << 8 ) | $angleRaw->first() ) >> 1 ) / 64.0;
        $angle = round($angle);

        $distanceRaw = collect($rawData->get('distance'));
        $checkBit = $distanceRaw->first() & 1;
        $distance = (( $distanceRaw->last() << 8 ) | $distanceRaw->first() );
        $distance = $distance > 0 ? ( $distance / 4.0 ) / 10.0 : 0;

        $data = [
            'angle' => $angle,
            'distance' => $distance,
            'quality' => $qualityValue
        ];

        if ( $checkBit === 1 && $inversedBit != $startFlag && $distance > 0 && $distance <= 1200.0 && $angle <= 360 && $qualityValue > 0)
        {
            $this->publishedMeasurements->put($angle, $data);
            $this->publishedMeasurements = $this->publishedMeasurements->sortKeys();
        }

        return $this->publishedMeasurements;
    }

    /**
     * @param Collection $currentPacket
     * @return Collection|null
     */
    public function getExpressScanLegacy(Collection $currentPacket) : ?Collection
    {
        if ( ! $this->isRunningScan )
        {
            return $this->setScanMode('EXPRESS_SCAN_LEGACY');
        }

        /**
         * In this mode we need current packet + previous one to calculate angle.
         */
        if ( $this->hasPreviousPacketCached ) {

            $cabins = $this->previousPacket->get('cabins');
            $sync1 = (int) $this->previousPacket->get('sync_one')->first();
            $sync2 = (int) $this->previousPacket->get('sync_two')->first();
            $previousStartAngle1 = $this->previousPacket->get('start_angle_part_one')->first();
            $previousStartAngle2 = $this->previousPacket->get('start_angle_part_two')->first();
            $startAngle1Current = $currentPacket->get('start_angle_part_one')->first();
            $startAngle2Current = $currentPacket->get('start_angle_part_two')->first();

            //@$startFlagBit - It's pretty uselesss, because it change state only in transition to second packet and then remains as 0.
            //$startFlagBit = $previousStartAngle2 >> 7;

            $syncFlag = (($sync1 >> 4) << 4) | $sync2 >> 4;
            $receivedCheckSum = $sync1 & 0xF | ($sync2 & 0xF) << 4;

            /**
             * Just some data checks provided by device documentation.
             */
            if ( ! $this->isExpressScanPacketValid($cabins, $receivedCheckSum, $previousStartAngle1, $previousStartAngle2, $syncFlag) )
            {
                return $this->publishedMeasurements;
            }

            $currentStartAngle = ((( $startAngle2Current & 0x7F ) << 8) | $startAngle1Current ) >> 6;
            $previousStartAngle = ((( $previousStartAngle2 & 0x7F ) << 8) | $previousStartAngle1 ) >> 6;

            $diffAngle = $this->expressScanAngleDifference($currentStartAngle, $previousStartAngle);

            /**
             * We've 32 cabins so that is where it comes from.
             */
            $angleInclination = $diffAngle / 32;

            $cabinsChunks = array_chunk($cabins->toArray(), 5);

            /**
             * foreach is slightly faster then each/map and in this case we need to decode data as fast as possible.
             */
            foreach ($cabinsChunks as $key => $cabin)
            {
                if ( count($cabin) < 5 )
                {
                    continue;
                }

                /**
                 * Calculation is pretty simple:
                 *
                 * $previousStartAngle + $angleDiff * $cabin(n) - cabin(n) angle offset
                 */
                $this->publishExpressCabinData($cabin, $previousStartAngle, $angleInclination);
            }
        }

        /**
         * Cache current packet
         */
        $this->publishedMeasurements = $this->publishedMeasurements->sortKeys();
        $this->hasPreviousPacketCached = true;
        $this->previousPacket = $currentPacket;

        return !isset($syncFlag) ? collect([]) : $this->publishedMeasurements;
    }

    /**
     * @param array $cabin
     * @param $previousStartAngle
     * @param $angleInclination
     */
    private function publishExpressCabinData(array &$cabin, &$previousStartAngle, &$angleInclination) : void
    {
        $angle_q16[0] = $previousStartAngle - ( $cabin[4] & 0xF | ( ( $cabin[0] & 0x3 ) << 4 ) );
        $previousStartAngle += $angleInclination;

        $angle_q16[1] = $previousStartAngle - ( $cabin[4] >> 4 | ( ( $cabin[2] & 0x3 ) << 4 ) );
        $previousStartAngle += $angleInclination;

        $distance = [
            ( ( $cabin[1] << 8 | $cabin[0] ) >> 2 ) / 10,
            ( ( $cabin[3] << 8 | $cabin[2] ) >> 2 ) / 10
        ];

        for ( $i = 0; $i < 2; ++$i)
        {

            if ( $angle_q16[$i] < 0 )
            {
                $angle_q16[$i] += 360;

            } else if ( $angle_q16[$i] >= (360) ) {

                $angle_q16[$i] -= 360;
            }

            $decodedCabin = [
                'angle' => round($angle_q16[$i]),
                'distance' => $distance[$i],
            ];

            if ( $decodedCabin['distance'] > 0 && $decodedCabin['distance'] <= 1200.0 && $decodedCabin['angle'] >= 0 && $decodedCabin['angle'] <= 360 ) {

                $this->publishedMeasurements->put($decodedCabin['angle'], $decodedCabin);
            }
        }
    }

    /**
     * @param int $currentStartAngle
     * @param int $previousStartAngle
     * @return int
     */
    private function expressScanAngleDifference(int $currentStartAngle, int $previousStartAngle) : int
    {
        $diffAngle = $currentStartAngle - $previousStartAngle;

        if ( $previousStartAngle > $currentStartAngle )
        {
            $diffAngle += 360;
        }

        return $diffAngle;
    }

    /**
     * @param Collection $cabins
     * @param int $receivedCheckSum
     * @param int $previousStartAngle1
     * @param int $previousStartAngle2
     * @param int $syncFlag
     * @return bool
     */
    private function isExpressScanPacketValid(collection $cabins, int $receivedCheckSum, int $previousStartAngle1, int $previousStartAngle2, int $syncFlag) : bool
    {
        $checkSum = 0 ^ $previousStartAngle1 ^ $previousStartAngle2;

        foreach ($cabins as $value)
        {
            $checkSum ^= $value;
        }

        return $receivedCheckSum === $checkSum && $syncFlag == 0xA5;
    }

    /**
     * @param string|null $mode
     */
    private function setScanMode(?string $mode = null) : void
    {
        if ( is_null($mode) )
        {
            $this->cachedBits = null;
        }

        $this->isRunningScan = is_null($mode) ? false : true;
        $this->scanMode = is_null($mode) ? '' : $mode;
    }

    /**
     * Controls "Data Terminal Ready" - DTR - state via c++ ioctl.
     *
     * DTR signal is required to control motor, but unfortunately
     * php doesn't have such function so I'm using small c++ program.
     *
     * @TODO convert exe to php extension
     *
     * @param bool $state
     * @return bool
     */
    public function runMotor(bool $state) : bool
    {
        $dtr = $state ?  'clear' : 'set';

        $dtrSwitchPath = __DIR__ . '/../C++/dtr/ioctl';

        return $this->port->exec($dtrSwitchPath . ' ' . $this->port->portName . ' ' . $dtr)
                    ->get('status');
    }
}