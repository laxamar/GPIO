<?php

namespace Amar\GPIOSysV;

// use PiPHP\GPIO\Interrupt\InterruptWatcher;
// use PiPHP\GPIO\Pin\InputPin;
// use PiPHP\GPIO\Pin\OutputPin;
// use PiPHP\GPIO\Pin\Pin;
// use PiPHP\GPIO\GPIO;

class GPIOSysVClt implements GPIOSysVInterface
{
    static private $instance;
    private $debug;
    const DEBUG_FILE = '/var/tmp/GPIOSysVClt.log';

    static public function getInstance()
    {
        if( !isset( self::$instance ) )
        {
            $this_class = get_called_class();
            $_local_obj = new $this_class();

            self::$instance = $_local_obj;
        }
        return self::$instance;
    }

    /**
     * Dispatch a data block through SysV to a server
     * @param array $data to be passed to server
     * @param int|null $error_code msg_send error code if any
     * @param int|null $block_time time to block after dispatch to synchronize with server timing.
     * @return bool
     */
    private function dispatch(array $data, ?int &$error_code = null, ?int $block_time=null) : bool
    {
        $seg      = msg_get_queue(GPIOSysVInterface::MSG_QUEUE_ID);
        $msg_type = GPIOSysVInterface::MSG_TYPE_GPIO;
        $dispatch_error = null;
        if ($this->debug) {
            $this->log(__METHOD__, $data);
        }
        $dispatch_success = msg_send($seg, $msg_type, $data, true, true, $dispatch_error);
        // TODO: Take some time off for function call overhead
        if (!empty($block_time)) usleep($block_time);
        if (!empty($dispatch_error))
        {
            $this->log(__METHOD__.' $dispatch_error :', [$dispatch_error]);
            $error_code = $dispatch_error;
        }
        return $dispatch_success;
    }

    /**
     *  {@inheritdoc}
     */
    public function setPinHigh(int $pin_id, ?int &$error_code = null) : ?bool
    {
        $data = [
            'function' => 'setPinHigh',
            'parms'    => [
                'pin_id' => $pin_id
            ]
        ];
        return $this->dispatch($data, $error_code);
    }

    /**
     *  {@inheritdoc}
     */
    public function setPinLow(int $pin_id, ?int &$error_code = null) : ?bool
    {
        $data = [
            'function' => 'setPinLow',
            'parms'    => [
                'pin_id' => $pin_id
            ]
        ];
        return $this->dispatch($data, $error_code);
    }

    /**
     *  {@inheritdoc}
     */
    public function getPin(int $pin_id, ?int &$error_code=null) : ?int
    {
        $msg_queue_id = self::MSG_BACK_ID;
        $msg_type_back = self::MSG_BACK_GPIO; // TODO: add a unique number

        $data = [
            'function' => 'getPin',
            'parms' => [
                'pin_id' => $pin_id,
                'msg_queue_id' => $msg_queue_id,
                'msg_type' => $msg_type_back,
            ]
        ];
        $this->dispatch($data, $error_code);
        // Now wait for the answer (OMG)
        $seg = msg_get_queue($msg_queue_id);
        $stat = msg_stat_queue($seg);
        // TODO: Loop and Wait a reasonable amount of time before reading
        if ($stat['msg_qnum'] > 0) {
            msg_receive($seg, $msg_type_back, $msg_type, self::MSG_MAX_SIZE,
                $response, true, 0, $error_code);
            // check for errors
            if (!empty($error_code)) {
                $this->log(__METHOD__.' msg_receive() Error code :' . $error_code, $data);
            }
            if ($msg_type != $msg_type_back) {
                $this->log(__METHOD__.' Received wrong message type back instead of expected ' . $msg_type_back . ' we got :' . $msg_type, $data);
            }
            if (is_null($response)) {
                $this->log(__METHOD__.' Empty $response received:', $data);
            }
            return $data['pin_status'] ?? null;
        } else {
            $error_code = $error_code ?? 9999;
            return null;
        }

    }

    /**
     *  {@inheritdoc}
     */
    public function getPinArray(array $pin_array, ?int &$error_code=null) : ?array
    {
        $msg_queue_id = self::MSG_BACK_ID;
        $msg_type_back_array = self::MSG_BACK_ARRAY; // TODO: add a unique number

        $data = [
            'function' => 'getPinArray',
            'parms' => [
                'pin_array'    => $pin_array,
                'msg_queue_id' => $msg_queue_id,
                'msg_type'     => $msg_type_back_array,
            ]
        ];
        $this->dispatch($data, $error_code);
        // Now wait for the answer (OMG)
        $seg = msg_get_queue($msg_queue_id);
        // $stat = msg_stat_queue($seg);
        // TODO: Loop and Wait a reasonable amount of time before reading
        pcntl_signal(SIGALRM, [$this, "sigAlarmHandler"]);
        // Set an alarm to wait for 1 second before checking for "still_running"
        pcntl_alarm(1);

        if ( msg_receive($seg, $msg_type_back_array, $msg_type, self::MSG_MAX_SIZE,
                $response, true, 0, $error_code) )
        {
            // check for errors
            if (!empty($error_code)) {
                $this->log(__METHOD__.' msg_receive() Error code :' . $error_code, $data);
            }
            if ($msg_type != $msg_type_back_array) {
                $this->log(__METHOD__.' Received wrong message type back instead of expected ' . $msg_type_back_array . ' we got :' . $msg_type, $data);
            }
            if (is_null($response)) {
                $this->log(__METHOD__.' Null $response received:', $data);
            }
            return $response ?? null;
        } else {
            $this->log(__METHOD__. ' did not receive msg back', ['data' => $data, 'response' => $response, 'error' => $error_code]);
            $error_code = $error_code ?? 9999;
            return null;
        }

    }

    /**
     * return a Decimal representation of the input PINs
     * @param array $pin_array
     * @param int|null $error_code
     * @return int|null
     */
    public function getPinArrayDec(array $pin_array, ?int &$error_code=null) : ?int
    {
        $state = $this->getPinArray($pin_array, $error_code);
        return bindec(implode(' ', array_reverse($state['array_status'])));
    }

    /**
     * {@inheritdoc}
     */
    public function setArrayLow(array $pin_array, ?int &$error_code=null) : ?bool
    {
        $data = [
            'function' => 'setArrayLow',
            'parms'    => [
                'pin_array' => $pin_array
            ]
        ];
        return $this->dispatch($data, $error_code);
    }

    /**
     * {@inheritdoc}
     */
    public function setArrayHigh(array $pin_array, ?int &$error_code=null) : ?bool
    {
        $data = [
            'function' => 'setArrayHigh',
            'parms'    => [
                'pin_array' => $pin_array
            ]
        ];
        return $this->dispatch($data, $error_code);
    }

    /**
     * {@inheritdoc}
     */
    public function setPinsBinary($value, $pin_array, ?int &$error_code=null) : ?bool
    {
        $data = [
            'function' => 'setPinsBinary',
            'parms'    => [
                'value'     => $value,
                'pin_array' => $pin_array
            ]
        ];
        return $this->dispatch($data, $error_code);
    }

    /**
     * {@inheritdoc}
     */
    public function flashBinary(int $value, array $pin_array, int $select_pin,  ?int $high_delay = 50000, ?int $low_delay = 50000, ?bool $blocking=true, ?int &$error_code = null) : ?bool
    {
        $data = [
            'function' => 'flashBinary',
            'parms'    => [
                'value'      => $value,
                'pin_array'  => $pin_array,
                'select_pin' => $select_pin,
                'high_delay' => $high_delay,
                'low_delay'  => $low_delay
            ]
        ];
        $block_time = $blocking ? ($high_delay+$low_delay) : null;
        return $this->dispatch($data, $error_code, $block_time);
    }

    /**
     * {@inheritdoc}
     */
    public function strobeBinary(int $value, array $pin_array, int $select_pin, ?int $count=1, ?int $off_count=0, ?int $period=1000000, ?bool $blocking=true, ?int &$error_code=null) : ?bool
    {
        $data = [
            'function' => 'strobeBinary',
            'parms'    => [
                'value'      => $value,
                'pin_array'  => $pin_array,
                'select_pin' => $select_pin,
                'count'      => $count,
                'off_count'  => $off_count,
                'period'     => $period
            ]
        ];
        $block_time = $blocking ? $count*$period : null;
        return $this->dispatch($data, $error_code, $block_time);
    }

    /**
     * {@inheritdoc}
     */
    public function flashPinHighLow(int $pin_id, ?int $count = 1, ?int $high_delay = 50000, ?int $low_delay = 50000, ?bool $blocking=true, ?int &$error_code = null) : ?bool
    {
        $data = [
            'function' => 'flashPinHighLow',
            'parms'    => [
                'pin_id'    => $pin_id,
                'count'     => $count,
                'high_delay' => $high_delay,
                'low_delay' => $low_delay
            ]
        ];
        $block_time = $blocking ? $count*($high_delay+$low_delay) : null;
        return $this->dispatch($data, $error_code, $block_time);
    }

    /**
     * {@inheritdoc}
     */
    public function flashPinLowHigh(int $pin_id, ?int $count = 1, ?int $low_delay = 50000, ?int $high_delay = 50000, ?bool $blocking=true, ?int &$error_code = null) : ?bool
    {
        $data = [
            'function' => 'flashPinLowHigh',
            'parms'    => [
                'pin_id'    => $pin_id,
                'count'     => $count,
                'low_delay'  => $low_delay,
                'high_delay' => $high_delay
            ]
        ];
        $block_time = $blocking ? $count*($low_delay+$high_delay) : null;
        return $this->dispatch($data, $error_code, $block_time);
    }

    /**
     * log error or status to a file
     * @param string $message - to be logged
     * @param array|null $data - option $data array passed as a parameter through SysV
     */
    private function log(string $message, ?array $data = null)
    {
        file_put_contents(self::DEBUG_FILE,$message.':'.print_r($data,1), FILE_APPEND | LOCK_EX );
    }

    /**
     * set the global object $debug
     * @param bool $set_debug
     * @return bool
     */
    public function setDebug(bool $set_debug) : bool
    {
        $prev = $this->debug ?? false;
        $this->debug = $set_debug;
        return $prev;
    }

    /**
     * signal handler for timeout Alarms
     * Currently used for SIGALRM Only.
     * The rest are here for show
     */
    function sigAlarmHandler (int $sigNo, array $sigInfo) : int {
        // echo "Interrupt $sigNo :" . print_r($sigInfo, 1);
        switch ($sigNo) {
            case SIGALRM:
                // stop msg_receive loop to check for still_running
                break;
            default:
                // handle all other signals
                break;
        }
        return 0;
    }


}