<?php

namespace Amar\GPIOSysV;

use PiPHP\GPIO\Interrupt\InterruptWatcher;
use PiPHP\GPIO\Pin\InputPin;
use PiPHP\GPIO\Pin\OutputPin;
use PiPHP\GPIO\Pin\Pin;

class GPIOSysVSrv implements GPIOSysVInterface
{
    static private $instance;
    private $gpio_obj;
    private $debug;
    public  $still_running;

    static public function getInstance()
    {
        if( !isset( self::$instance ) )
        {
            $this_class = get_called_class();
            $_local_obj = new $this_class();

            $_local_obj->gpio_obj = new \PiPHP\GPIO\GPIO();

            self::$instance = $_local_obj;
        }
        return self::$instance;
    }

    /**
     * process the input queue indefinitely or until a signal is sent to stop running
     */
    public function process_queue()
    {
        $seg      = msg_get_queue(self::MSG_QUEUE_ID);
        $msg_type = self::MSG_TYPE_GPIO;
        $data     = null;
        $error_code = null;
        $success  = true;
        $debug    = null;
        
        while ($this->still_running)
        {
            $stat = msg_stat_queue($seg);
            if ($this->debug) echo 'Messages in the queue: ' . $stat['msg_qnum'] . "\n";
            if ($stat['msg_qnum'] > 0) {
                msg_receive($seg, self::MSG_TYPE_GPIO, $msg_type, self::MSG_MAX_SIZE,
                    $data, true, 0, $error_code);
                // check for errors
                if (!empty($error_code))
                {
                    $this->log('Error code :'.$error_code, $data);
                    continue;
                }
                if ($msg_type != self::MSG_TYPE_GPIO)
                {
                    $this->log('Received wrong message type '.$msg_type, $data);
                    continue;
                }
                if (empty($data['function']))
                {
                    $this->log('No function call received:', $data);
                    continue;
                }
                $function_call = $data['function'];
                // Dispatch the message

                $debug = null; // need a placeholder
                $error_code = null; // need a variable
                $success    = true;
                switch ( $function_call )
                {
                    case 'set_pin':
                        $pin_id = $data['parms']['pin_id'] ?? null;
                        if (empty($pin_id)) {
                            $success = false;
                            $this->log($function_call.' with empty pin_id', $data);
                            break;
                        }
                        $success &= $this->set_pin($pin_id, $error_code);
                        break;
                    case 'clear_pin':
                        $pin_id = $data['parms']['pin_id'] ?? null;
                        if (empty($pin_id)) {
                            $success = false;
                            $this->log($function_call.' with empty pin_id', $data);
                            break;
                        }
                        $success &= $this->clear_pin($pin_id, $error_code);
                        break;
                    case 'get_pin ':
                        $pin_id = $data['parms']['pin_id'] ?? null;
                        $pin_status = $this->get_pin($pin_id, $error_code);
                        $this->msg_back($data, ['pin_status' => $pin_status], $error_code);
                        break;
                    case 'all_clear':
                        $pin_array = $data['parms']['pin_array'] ?? [];
                        if (empty($pin_array)) {
                            $success = false;
                            $this->log($function_call. ' with empty array', $data);
                            break;
                        }
                        $success &= $this->all_clear($pin_array, $error_code);
                        break;
                    case 'set_binary':
                        $dec_value = $data['parms']['dec_value'] ?? null;
                        $pin_array = $data['parms']['pin_array'] ?? [];
                        if (is_null($dec_value) || empty($pin_array))
                        {
                            $success = false;
                            $this->log($function_call. ' with empty values', $data);
                            break;
                        }
                        $success &= $this->set_binary($dec_value, $pin_array, $debug, $error_code);
                        break;
                    case 'flash_binary':
                        $dec_value  = $data['parms']['value'] ?? null;
                        $pin_array  = $data['parms']['pin_array'] ?? [];
                        $select_pin = $data['parms']['select_pin'] ?? null;
                        if (is_null($dec_value) || empty($pin_array) || empty($select_pin))
                        {
                            $success = false;
                            $this->log($function_call. ' with empty values', $data);
                            break;
                        }
                        $success &= $this->flash_binary($dec_value, $pin_array, $select_pin, $debug, $error_code);
                        break;
                    case 'blip_binary':
                        $dec_value  = $data['parms']['value'] ?? null;
                        $pin_array  = $data['parms']['pin_array'] ?? [];
                        $select_pin = $data['parms']['select_pin'] ?? null;
                        $count      = $data['parms']['count'] ?? null;
                        $empty      = $data['parms']['empty'] ?? null;
                        $period     = $data['parms']['period'] ?? null;
                        if (is_null($dec_value) || empty($pin_array) || empty($select_pin))
                        {
                            $success = false;
                            $this->log($function_call. ' with empty values', $data);
                            break;
                        }
                        $success &= $this->blip_binary($dec_value, $pin_array, $select_pin, $count, $empty, $period, $debug, $error_code);
                        break;
                    case 'flash_bit':
                        $pin_id    = $data['parms']['pin_id'] ?? null;
                        $count     = $data['parms']['count'] ?? null;
                        $on_delay  = $data['parms']['on_delay'] ?? null;
                        $off_delay = $data['parms']['off_delay'] ?? null;
                        if (empty($pin_id)) {
                            $success = false;
                            $this->log($function_call.' with empty pin_id', $data);
                            break;
                        }
                        $success &= $this->flash_bit($pin_id, $count, $on_delay, $off_delay, $error_code);

                        break;
                    default:
                        // Log error
                        $success = false;
                        $this->log('Received non existent function call type '.$function_call, $data);
                        break;
                }
                if (!$success)
                {
                    // TODO: Do something?
                }
            }
        }
    }

    /**
     * Dispatch a data block through SysV to a server
     * @param array $data to be passed to server
     * @param array $response
     * @param null $error_code msg_send error code if any
     * @return bool
     */
    protected function msg_back(array $data, array $response, &$error_code = null) : bool
    {
        $seg      = msg_get_queue($data['msg_queue_id']);
        $msg_type = $data['msg_type'];
        $response_error = null;
        $dispatch_success = msg_send($seg, $msg_type, $response, true, true, $response_error_error);
        if (!$dispatch_success || !empty($response_error))
        {
            $error_code .= $response_error;
            $this->log('Sending MSG back error: '.$error_code, $data);
        }
        return $dispatch_success;
    }


    /**
     *  {@inheritdoc}
     */
    function set_pin($pin_id, &$error_code = null) : bool
    {
        $pin = $this->gpio_obj->getOutputPin($pin_id);
        $pin->setValue(\PiPHP\GPIO\PinInterface::VALUE_HIGH);
        return true;
    }

    /**
     *  {@inheritdoc}
     */
    function clear_pin($pin_id, &$error_code = null) : bool
    {
        $pin = $this->gpio_obj->getOutputPin($pin_id);
        return $pin->setValue(\PiPHP\GPIO\PinInterface::VALUE_LOW);
    }

    /**
     *  {@inheritdoc}
     */
    function get_pin($pin_id, &$error_code = null) : ?int
    {
        $pin = $this->gpio_obj->getOutputPin($pin_id);
        return $pin->getValue();
    }

    /**
     *  {@inheritdoc}
     */
    function all_clear($pin_array, &$error_code = null) : bool
    {
        foreach ($pin_array as $pin_id) {
            $this->clear_pin($pin_id);
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    function set_binary($value, $pin_array, $debug=false, string &$error_code=null) : bool
    {
        $bits = sizeof($pin_array);
        $binary = str_split(sprintf('%0'.$bits.'b', $value),1);
        if ($debug) $this->log($value . ' => '. print_r($binary,1));
        // $this->all_off($PIN_ARRAY);
        $return_status = true;
        for ($pos=0;$pos < $bits; $pos++) {
            // foreach ($binary as $pos => $bit)
            if ($binary[$pos] == 1) {
                $return_status &= $this->set_pin($pin_array[$bits-$pos-1], $error_code);
            } else {
                $return_status &= $this->clear_pin($pin_array[$bits-$pos-1], $error_code);
            }
        }
        return $return_status;
    }

    /**
     * @param int $value
     * @param array $pin_array
     * @param int $select_pin
     * @param false $debug
     * @param string|null $error_code
     * @return bool
     */
    function flash_binary(int $value, array $pin_array, int $select_pin, ?bool $debug=false, ?string &$error_code=null) : bool
    {
        $success = true;
        // set pin to turn off output
        $success &= $this->clear_pin($select_pin, $error_code);
        // Calculate binary pins
        $bits = sizeof($pin_array);
        $binary = str_split(sprintf('%0'.$bits.'b', $value),1);
        if ($debug) $this->log('DEBUG: $binary'. print_r($binary,1) );
        for ($pos=0;$pos < $bits; $pos++) {
            // foreach ($binary as $pos => $bit)
            if ($binary[$pos] == 1) {
                $success &= $this->set_pin($pin_array[$bits-$pos-1], $error_code);
            } else {
                $success &= $this->clear_pin($pin_array[$bits-$pos-1], $error_code);
            }
        }
        return $this->flash_bit($select_pin, $error_code);
    }

    /**
     * @param int|null $value
     * @param array $pin_array
     * @param int|null $select_pin
     * @param int|null $count
     * @param int|null $empty
     * @param int|null $period
     * @param false $debug
     * @param string|null $error_code
     * @return bool
     */
    function blip_binary(int $value, array $pin_array, int $select_pin, ?int $count=1, ?int $empty=0, ? int $period=1000000,
                         ?bool $debug=false, ?string &$error_code=null) : bool
    {
        // set pin to turn off output
        $this->clear_pin($select_pin);
        // Calculate binary pins
        $bits = sizeof($pin_array);
        $binary = str_split(sprintf('%0'.$bits.'b', $value),1);
        for ($pos=0;$pos < $bits; $pos++) {
            // foreach ($binary as $pos => $bit)
            if ($binary[$pos] == 1) {
                $this->set_pin($pin_array[$bits-$pos-1], $error_code);
            } else {
                $this->clear_pin($pin_array[$bits-$pos-1], $error_code);
            }
        }
        $delay = $period/($count+$empty);
        if ($debug) $this->log('D:'.$delay.' '.$value . ' => '. print_r($binary,1));
        return $this->flash_bit($select_pin, $count, $delay/2, $delay/2, $error_code);
        // putting extra empty delay here??
        // usleep($empty * $delay);
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
    function flash_bit(int $pin_id, ?int $count = 1, ?int $on_delay = 50000, ?int $off_delay = 50000, ?string &$error_code=null) : bool
    {
        $return_status = true;
        for ($seq=1; $seq <= $count; $seq++)
        {
            $return_status &= $this->clear_pin($pin_id, $error_code);
            usleep($on_delay);
            $return_status &= $this->set_pin($pin_id, $error_code);
            usleep($off_delay);
        }
        return $return_status;
    }


    public function cleanMsgQueue()
    {
        $seg      = msg_get_queue(self::MSG_QUEUE_ID);
        msg_remove_queue($seg);
    }

    /**
     * log error or status to a file
     * @param string $message - text to be logged
     * @param array|null $data - optional $data array passed as a parameter through SysV
     */
    private function log(string $message, ?array $data = null)
    {
        // TODO: Implement log() method.
    }

    /*****
     * Section copied verbatim from PiPHP:GPIO
     */
    /**
     * {@inheritdoc}
     */
    public function getInputPin($number)
    {
        return new InputPin($this->gpio_obj->fileSystem, $number);
    }

    /**
     * {@inheritdoc}
     */
    public function getOutputPin($number, $exportDirection = Pin::DIRECTION_OUT)
    {
        if ($exportDirection !== Pin::DIRECTION_OUT && $exportDirection !== Pin::DIRECTION_LOW && $exportDirection !== Pin::DIRECTION_HIGH) {
            throw new \InvalidArgumentException('exportDirection has to be an OUT type (OUT/LOW/HIGH).');
        }

        return new OutputPin($this->gpio_obj->fileSystem, $number, $exportDirection);
    }

    /**
     * {@inheritdoc}
     */
    public function createWatcher()
    {
        return new InterruptWatcher($this->gpio_obj->fileSystem, $this->gpio_obj->streamSelect);
    }
}