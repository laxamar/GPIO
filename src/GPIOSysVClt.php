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
     * @param null $error_code msg_send error code if any
     * @return bool
     */
    private function dispatch(array $data, &$error_code = null) : bool
    {
        $seg      = msg_get_queue(GPIOSysVInterface::MSG_QUEUE_ID);
        $msg_type = GPIOSysVInterface::MSG_TYPE_GPIO;
        $dispatch_error = null;
        if ($this->debug) {
            $this->log('Dispatch :', $data);
        }
        $dispatch_success = msg_send($seg, $msg_type, $data, true, true, $dispatch_error);
        if (!empty($dispatch_error))
        {
            $this->log('   Error :', [$dispatch_error]);
            $error_code .= $dispatch_error;
        }
        return $dispatch_success;
    }

    /**
     *  {@inheritdoc}
     */
    public function setPinHigh(int $pin_id, &$error_code = null) : ?bool
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
    public function setPinLow(int $pin_id, &$error_code = null) : ?bool
    {
        $data = [
            'function' => 'setPinLow',
            'parms'    => [
                'pin_id' => $pin_id
            ]
        ];
        return $this->dispatch($data, $error_code);
    }

    public function getPin(int $pin_id, &$error_code=null) : ?int
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
                $this->log('Error code :' . $error_code, $data);
            }
            if ($msg_type != $msg_type_back) {
                $this->log('Received wrong message type back instead of expected ' . $msg_type_back . ' we got :' . $msg_type, $data);
            }
            if (is_null($response)) {
                $this->log('Empty receive:', $data);
            }
            return $data['pin_status'] ?? null;
        } else {
            $error_code .= '9999';
            return null;
        }

    }

    /**
     * {@inheritdoc}
     */
    public function setArrayLow(array $pin_array, &$error_code=null) : ?bool
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
    public function setArrayHigh(array $pin_array, &$error_code=null) : ?bool
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
    function setPinsBinary($value, $pin_array, &$error_code=null) : ?bool
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
     * @param int|null $value
     * @param array $pin_array
     * @param int|null $select_pin
     * @param null $error_code
     * @return bool
     */
    function flashBinary(int $value, array $pin_array, int $select_pin, &$error_code = null) : ?bool
    {
        $data = [
            'function' => 'flashBinary',
            'parms'    => [
                'value'      => $value,
                'pin_array'  => $pin_array,
                'select_pin' => $select_pin
            ]
        ];
        return $this->dispatch($data, $error_code);
    }

    /**
     * @param int $value
     * @param array $pin_array
     * @param int $select_pin
     * @param int|null $count
     * @param int|null $empty
     * @param int|null $period
     * @param null $error_code
     * @return bool
     */
    function strobeBinary(int $value, array $pin_array, int $select_pin, ?int $count=1, ?int $empty=0, ?int $period=1000000, &$error_code=null) : ?bool
    {
        $data = [
            'function' => 'strobeBinary',
            'parms'    => [
                'value'      => $value,
                'pin_array'  => $pin_array,
                'select_pin' => $select_pin,
                'count'      => $count,
                'empty'      => $empty,
                'period'     => $period
            ]
        ];
        return $this->dispatch($data, $error_code);
    }

    /**
     * flash_pin - Flash a pin instead of turning it on.
     *    loop $count time
     *        turn on - wait $on_delay - turn off - wait $off_delay
     * @param int $pin_id
     * @param int|null $count number of times to flash
     * @param int|null $on_delay in useconds
     * @param int|null $off_delay in useconds
     * @param string|null $error_code
     * @return bool
     */
    public function flashPinHighLow(int $pin_id, ?int $count = 1, ?int $on_delay = 50000, ?int $off_delay = 50000, ?string &$error_code = null) : ?bool
    {
        $data = [
            'function' => 'flashPinHighLow',
            'parms'    => [
                'pin_id'    => $pin_id,
                'count'     => $count,
                'on_delay'  => $on_delay,
                'off_delay' => $off_delay
            ]
        ];
        return $this->dispatch($data, $error_code);
    }

    public function flashPinLowHigh(int $pin_id, ?int $count = 1, ?int $on_delay = 50000, ?int $off_delay = 50000, ?string &$error_code = null) : ?bool
    {
        $data = [
            'function' => 'flashPinLowHigh',
            'parms'    => [
                'pin_id'    => $pin_id,
                'count'     => $count,
                'on_delay'  => $on_delay,
                'off_delay' => $off_delay
            ]
        ];
        return $this->dispatch($data, $error_code);
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
    function setDebug(bool $set_debug) : bool
    {
        $prev = $this->debug ?? false;
        $this->debug = $set_debug;
        return $prev;
    }

}