<?php

namespace PiPHP\GPIO;

use PiPHP\GPIO\Interrupt\InterruptWatcher;
use PiPHP\GPIO\Pin\InputPin;
use PiPHP\GPIO\Pin\OutputPin;
use PiPHP\GPIO\Pin\Pin;
use PiPHP\GPIO\GPIO;

class GPIOSysVClt implements GPIOSysVInterface
{
    static private $instance;
    private $debug;

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
        $dispatch_success = msg_send($seg, $msg_type, $data, true, true, $dispatch_error);
        if (!empty($dispatch_error))
        {
            $error_code .= $dispatch_error;
        }
        return $dispatch_success;
    }

    /**
     *  {@inheritdoc}
     */
    public function set_pin(int $pin_id, &$error_code = null) :bool
    {
        $data = [
            'function' => 'set_pin',
            'parms'    => [
                'pin_id' => $pin_id
            ]
        ];
        return $this->dispatch($data, $error_code);
    }

    /**
     *  {@inheritdoc}
     */
    public function clear_pin($pin_id, &$error_code = null) : bool
    {
        $data = [
            'function' => 'set_pin',
            'parms'    => [
                'pin_id' => $pin_id
            ]
        ];
        return $this->dispatch($data, $error_code);
    }

    public function get_pin(int $pin_id, &$error_code=null) : bool
    {
        // TODO: implement
        return $pin_id;
    }

    public function all_clear($pin_array, &$error_code=null) : bool
    {
        $data = [
            'function' => 'set_pin',
            'parms'    => [
                'pin_array' => $pin_array
            ]
        ];
        return $this->dispatch($data, $error_code);
    }

    /**
     * {@inheritdoc}
     */
    function set_binary($value, $pin_array, $debug=false, &$error_code=null) :bool
    {
        $data = [
            'function' => 'set_binary',
            'parms'    => [
                'value'     => $value,
                'pin_array' => $pin_array
            ]
        ];
        return $this->dispatch($data, $error_code);
    }

    /**
     * @param int $value
     * @param array $pin_array
     * @param null $toggle_pin
     * @param false $debug
     */
    function flash_binary(int $value = null, array $pin_array = [], int $toggle_pin = null, ?bool $debug=false, &$error_code = null) : bool
    {
        $data = [
            'function' => 'flash_binary',
            'parms'    => [
                'value'      => $value,
                'pin_array'  => $pin_array,
                'toggle_pin' => $toggle_pin
            ]
        ];
        return $this->dispatch($data, $error_code);
    }

    /**
     * @param null $value
     * @param array $pin_array
     * @param null $toggle_pin
     * @param int $count
     * @param int $empty
     * @param int $period
     * @param false $debug
     */
    function blip_binary($value = null, $pin_array = [], $toggle_pin = null, $count=1, $empty=0, $period=1000000, $debug=false, &$error_code=null) : bool
    {
        $data = [
            'function' => 'blip_binary',
            'parms'    => [
                'value'      => $value,
                'pin_array'  => $pin_array,
                'toggle_pin' => $toggle_pin,
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
     *        turn on - wait $on_dalay - turn off - wait $off_delay
     * @param int $pin_id
     * @param int $count number of times to flash
     * @param int $on_delay in useconds
     * @param int $off_delay in useconds
     */
    public function flash_bit(int $pin_id, ?int $count = 1, ?int $on_delay = 50000, ?int $off_delay = 50000, ?string &$error_code = null) : bool
    {
        $data = [
            'function' => 'flash_bit',
            'parms'    => [
                'pin_id' => $pin_id
            ]
        ];
        return $this->dispatch($data, $error_code);
    }

    /**
     * log error or status to a file
     * @param $message - to be logged
     * @param null $data - option $data array passed as a parameter through SysV
     */
    private function log(string $message, ?bool $data = null)
    {
        // TODO: Implement log() method.
    }

    function getInputPin(int $number)
    {
        // TODO: Implement getInputPin() method.
    }

    function getOutputPin(int $number)
    {
        // TODO: Implement getOutputPin() method.
    }

    public function createWatcher()
    {
        // TODO: Implement createWatcher() method.
    }

}