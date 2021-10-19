<?php

namespace Amar\GPIOSysV;

use PiPHP\GPIO\GPIO;

class GPIOSysVSrv implements GPIOSysVInterface
{
    static private $instance;
    private $gpio_obj;
    private bool $debug = false;
    public bool $still_running;
    public const VALUE_LOW = 0;
    public const VALUE_HIGH = 1;

    const DEBUG_FILE = '/var/tmp/GPIOSysVSrv.log';
    const PIN_FILTER_OPTIONS = [
        'options' => [
            'min_range' => 1,
            'max_range' => 40
        ]
    ];
    const VALUE_FILTER_OPTIONS = [
        'options' => [
            'min_range' => 0
        ]
    ];

    /**
     * Singleton object to handle all pins
     * @return mixed
     */
    static public function getInstance()
    {
        if( !isset( self::$instance ) )
        {
            $this_class = get_called_class();
            $_local_obj = new $this_class();

            $_local_obj->gpio_obj = new \PiPHP\GPIO\GPIO();
            // Default debug off
            // $_local_obj->debug = false;

            self::$instance = $_local_obj;
        }
        return self::$instance;
    }

    /**
     * process the input queue indefinitely or until a signal is sent to stop running
     */
    public function processQueue()
    {
        $seg      = msg_get_queue(self::MSG_QUEUE_ID);
        $msg_type = self::MSG_TYPE_GPIO;
        $data     = null;
        $error_code = null;
        $success    = true; // re-initialized inside loop
        $no_blocking = false; // Make sure server does not do extra blocking

        pcntl_async_signals(TRUE);

        // setup signal handlers
        pcntl_signal(SIGALRM, [$this, "sigAlarmHandler"]);

        while ($this->still_running)
        {
            // $stat = msg_stat_queue($seg);
            // if ($this->debug) $this->log( 'Messages in the msg_queue: ' . $stat['msg_qnum'] );
            // if ($stat['msg_qnum'] > 0) {
            // Set an alarm to wait for 1 second before checking for "still_running"
            pcntl_alarm(1);
            while (msg_receive($seg, self::MSG_TYPE_GPIO, $msg_type, self::MSG_MAX_SIZE,
                    $data, true, 0, $error_code) )
            {
                // Clear alarm during processing
                if ($this->debug) $this->log( __METHOD__.' Messages Received : ', $data );
                // check for errors
                if (!empty($error_code))
                {
                    $this->log(__METHOD__.' Error code :'.$error_code, $data);
                    continue;
                }
                if ($msg_type != self::MSG_TYPE_GPIO)
                {
                    $this->log(__METHOD__.' Received wrong message type '.$msg_type, $data);
                    continue;
                }
                if (empty($data['function']))
                {
                    $this->log(__METHOD__.' No function call received:', $data);
                    continue;
                }
                $function_call = $data['function'];
                // Dispatch the message

                $error_code = null; // need a variable
                $success    = true;
                switch ( $function_call )
                {
                    case 'setPin':
                        $pin_id    = $data['parms']['pin_id'] ?? null;
                        $pin_value = $data['parms']['pin_value'] ?? null;

                        if( filter_var( $pin_id, FILTER_VALIDATE_INT, self::PIN_FILTER_OPTIONS ) === false ||
                            filter_var( $pin_value, FILTER_VALIDATE_INT, self::VALUE_FILTER_OPTIONS ) === false
                        )
                        {
                            $success = false;
                            $this->log($function_call.' with empty pin_id or pin_value', $data);
                            break;
                        }
                        $success &= $this->setPin($pin_id, $pin_value, $error_code);
                        break;
                    case 'setPinHigh':
                        $pin_id = $data['parms']['pin_id'] ?? null;
                        if( filter_var( $pin_id, FILTER_VALIDATE_INT, self::PIN_FILTER_OPTIONS ) === false )
                        {
                            $success = false;
                            $this->log($function_call.' with empty pin_id', $data);
                            break;
                        }
                        $success &= $this->setPinHigh($pin_id, $error_code);
                        break;
                    case 'setPinLow':
                        $pin_id = $data['parms']['pin_id'] ?? null;
                        if( filter_var( $pin_id, FILTER_VALIDATE_INT, self::PIN_FILTER_OPTIONS ) === false )
                        {
                            $success = false;
                            $this->log($function_call.' with empty pin_id', $data);
                            break;
                        }
                        $success &= $this->setPinLow($pin_id, $error_code);
                        break;
                    case 'getPin':
                        $pin_id = $data['parms']['pin_id'] ?? null;
                        if( filter_var( $pin_id, FILTER_VALIDATE_INT, self::PIN_FILTER_OPTIONS ) === false )
                        {
                            $success = false;
                            $this->log($function_call.' with empty pin_id', $data);
                            break;
                        }
                        $pin_status = $this->getPin($pin_id, $error_code);
                        $this->msgBack($data, ['pin_status' => $pin_status], $error_code);
                        break;
                    case 'getPinArray':
                        $pin_array = $data['parms']['pin_array'] ?? null;
                        if (empty($pin_array))
                        {
                            $success = false;
                            $this->log($function_call. ' with empty array', $data);
                            break;
                        }
                        $array_status = $this->getPinArray($pin_array, $error_code);
                        $this->msgBack($data, ['array_status' => $array_status], $error_code);
                        break;
                    case 'setArrayLow':
                        $pin_array = $data['parms']['pin_array'] ?? [];
                        if (empty($pin_array))
                        {
                            $success = false;
                            $this->log($function_call. ' with empty array', $data);
                            break;
                        }
                        $success &= $this->setArrayLow($pin_array, $error_code);
                        break;
                    case 'setArrayHigh':
                        $pin_array = $data['parms']['pin_array'] ?? [];
                        if (empty($pin_array))
                        {
                            $success = false;
                            $this->log($function_call. ' with empty array', $data);
                            break;
                        }
                        $success &= $this->setArrayHigh($pin_array, $error_code);
                        break;
                    case 'setPinsBinary':
                        $dec_value = $data['parms']['value'] ?? null;
                        $pin_array = $data['parms']['pin_array'] ?? [];
                        if( filter_var( $dec_value, FILTER_VALIDATE_INT, self::VALUE_FILTER_OPTIONS ) === false ||
                            empty($pin_array)
                        )
                        {
                            $success = false;
                            $this->log($function_call. ' with empty values', $data);
                            break;
                        }
                        $success &= $this->setPinsBinary($dec_value, $pin_array, $error_code);
                        break;
                    case 'flashBinary':
                        $dec_value  = $data['parms']['value'] ?? null;
                        $pin_array  = $data['parms']['pin_array'] ?? [];
                        $select_pin = $data['parms']['select_pin'] ?? null;
                        $select_dir = $data['parms']['select_dir'] ?? null;
                        $high_delay = $data['parms']['high_delay'] ?? null;
                        $low_delay  = $data['parms']['low_delay'] ?? null;
                        if( filter_var( $select_pin, FILTER_VALIDATE_INT, self::PIN_FILTER_OPTIONS ) === false ||
                            filter_var( $dec_value, FILTER_VALIDATE_INT, self::VALUE_FILTER_OPTIONS ) === false ||
                            empty($pin_array)
                        )
                        {
                            $success = false;
                            $this->log($function_call. ' with empty values', $data);
                            break;
                        }
                        $success &= $this->flashBinary($dec_value,
                            $pin_array,
                            $select_pin,
                            $select_dir,
                            $high_delay,
                            $low_delay,
                            $no_blocking,
                            $error_code);
                        break;
                    case 'strobeBinary':
                        $dec_value  = $data['parms']['value'] ?? null;
                        $pin_array  = $data['parms']['pin_array'] ?? [];
                        $select_pin = $data['parms']['select_pin'] ?? null;
                        $count      = $data['parms']['count'] ?? null;
                        $off_count  = $data['parms']['off_count'] ?? null;
                        $period     = $data['parms']['period'] ?? null;
                        if( filter_var( $select_pin, FILTER_VALIDATE_INT, self::PIN_FILTER_OPTIONS ) === false ||
                            filter_var( $dec_value, FILTER_VALIDATE_INT, self::VALUE_FILTER_OPTIONS ) === false ||
                            empty($pin_array)
                        )
                        {
                            $success = false;
                            $this->log($function_call. ' with empty values', $data);
                            break;
                        }
                        $success &= $this->strobeBinary($dec_value, $pin_array, $select_pin, $count, $off_count, $period, $no_blocking, $error_code);
                        break;
                    case 'flashPinHighLow':
                        $pin_id    = $data['parms']['pin_id'] ?? null;
                        $count     = $data['parms']['count'] ?? null;
                        $high_delay  = $data['parms']['high_delay'] ?? null;
                        $low_delay = $data['parms']['low_delay'] ?? null;
                        if( filter_var( $pin_id, FILTER_VALIDATE_INT, self::PIN_FILTER_OPTIONS ) === false )
                        {
                            $success = false;
                            $this->log($function_call.' with empty pin_id', $data);
                            break;
                        }
                        $success &= $this->flashPinHighLow($pin_id, $count, $high_delay, $low_delay, $no_blocking, $error_code);

                        break;
                    case 'flashPinLowHigh':
                        $pin_id    = $data['parms']['pin_id'] ?? null;
                        $count     = $data['parms']['count'] ?? null;
                        $on_delay  = $data['parms']['on_delay'] ?? null;
                        $off_delay = $data['parms']['off_delay'] ?? null;
                        if( filter_var( $pin_id, FILTER_VALIDATE_INT, self::PIN_FILTER_OPTIONS ) === false )
                        {
                            $success = false;
                            $this->log($function_call.' with empty pin_id', $data);
                            break;
                        }
                        $success &= $this->flashPinLowHigh($pin_id, $count, $on_delay, $off_delay, $no_blocking, $error_code);

                        break;
                    default:
                        // Log error
                        $success = false;
                        $this->log(__METHOD__.' Received non existent function call type '.$function_call, $data);
                        break;
                }
                if (!$success)
                {
                    // TODO: Do something?
                }
            }
        }
        pcntl_signal(SIGALRM, null);

    }

    /**
     * Dispatch a data block through SysV to a server
     * @param array $data to be passed to server
     * @param array $response
     * @param int|null $error_code msg_send error code if any
     * @return bool
     */
    protected function msgBack(array $data, array $response, ?int &$error_code = null) : ?bool
    {
        $msg_queue_id = $data['parms']['msg_queue_id'] ?? self::MSG_BACK_ID;
        $msg_type     = $data['parms']['msg_type'] ?? self::MSG_BACK_GPIO;
        $seg          = msg_get_queue($msg_queue_id);
        $response_error = null;
        $dispatch_success = msg_send($seg, $msg_type, $response, true, true, $response_error);
        if (!$dispatch_success || !empty($response_error))
        {
            $error_code .= $response_error;
            $this->log(__METHOD__.' Sending MSG back error: '.print_r($error_code,1), ['data' => $data, 'response' => $response]);
        }
        return $dispatch_success;
    }

    /**
     *  {@inheritdoc}
     */
    public function setPin(int $pin_id, int $pin_value, ?int &$error_code=null) : ?bool
    {
        $pin = $this->gpio_obj->getOutputPin($pin_id);
        $pin->setValue($pin_value);
        return true;
    }

    /**
     *  {@inheritdoc}
     */
    public function setPinHigh(int $pin_id, ?int &$error_code = null) : ?bool
    {
        $pin = $this->gpio_obj->getOutputPin($pin_id);
        $pin->setValue(self::VALUE_HIGH);
        return true;
    }

    /**
     *  {@inheritdoc}
     */
    public function setPinLow(int $pin_id, ?int &$error_code = null) : ?bool
    {
        $pin = $this->gpio_obj->getOutputPin($pin_id);
        return $pin->setValue(self::VALUE_LOW);
    }

    /**
     *  {@inheritdoc}
     */
    public function getPin(int $pin_id, ?int &$error_code = null) : ?int
    {
        $pin = $this->gpio_obj->getInputPin($pin_id);
        return $pin->getValue();
    }

    /**
     *  {@inheritdoc}
     */
    public function getPinArray(array $pin_array, ?int &$error_code = null) : ?array
    {
        $state = [];
        foreach ($pin_array as $pin_id)
        {
            $pin   = $this->gpio_obj->getInputPin($pin_id);
            $state[$pin_id] = $pin->getValue();
        }
        return $state;
    }

    /**
     *  {@inheritdoc}
     */
    public function setArrayLow(array $pin_array, ?int &$error_code = null) : ?bool
    {
        foreach ($pin_array as $pin_id) {
            $this->setPinLow($pin_id);
        }
        return true;
    }

    /**
     *  {@inheritdoc}
     */
    public function setArrayHigh(array $pin_array, ?int &$error_code = null) : ?bool
    {
        foreach ($pin_array as $pin_id) {
            $this->setPinHigh($pin_id);
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function setPinsBinary(int $value, array $pin_array, ?int &$error_code=null) : ?bool
    {
        $bits = sizeof($pin_array);
        $binary = str_split(sprintf('%0'.$bits.'b', $value),1);
        if ($this->debug) $this->log(__METHOD__.' '.$value , $binary);
        // $this->all_off($PIN_ARRAY);
        $return_status = true;
        for ($pos=0;$pos < $bits; $pos++) {
            // foreach ($binary as $pos => $bit)
            if ($binary[$pos] == 1) {
                $return_status &= $this->setPinHigh($pin_array[$bits-$pos-1], $error_code);
            } else {
                $return_status &= $this->setPinLow($pin_array[$bits-$pos-1], $error_code);
            }
        }
        return $return_status;
    }

    /**
     * {@inheritdoc}
     */
    public function flashBinary(int $value, array $pin_array, int $select_pin, ?int $select_dir=0, ?int $high_delay = 50000, ?int $low_delay = 50000,
                         ?bool $blocking=false, ?int &$error_code=null) : ?bool
    {
        $success = true;
        // set pin to turn off output
        $success &= $this->setPinLow($select_pin, $error_code);
        // Calculate binary pins
        $bits = sizeof($pin_array);
        $binary = str_split(sprintf('%0'.$bits.'b', $value),1);
        if ($this->debug) $this->log(__METHOD__.' $binary', $binary );
        for ($pos=0;$pos < $bits; $pos++) {
            // foreach ($binary as $pos => $bit)
            if ($binary[$pos] == 1) {
                $success &= $this->setPinHigh($pin_array[$bits-$pos-1], $error_code);
            } else {
                $success &= $this->setPinLow($pin_array[$bits-$pos-1], $error_code);
            }
        }
        if ($select_dir == 0)
        {
            return $this->flashPinHighLow($select_pin, 1, $high_delay, $low_delay, $blocking, $error_code);
        } else {
            return $this->flashPinLowHigh($select_pin, 1, $high_delay, $low_delay, $blocking, $error_code);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function strobeBinary(int $value, array $pin_array, int $select_pin, ?int $select_dir=0,  ?int $count=1, ?int $off_count=0, ? int $period=1000000,
                         ?bool $blocking=false, ?int &$error_code=null) : ?bool
    {
        // set pin to turn off output
        $this->setPinLow($select_pin);
        // Calculate binary pins
        $bits = sizeof($pin_array);
        $binary = str_split(sprintf('%0'.$bits.'b', $value),1);
        for ($pos=0;$pos < $bits; $pos++) {
            // foreach ($binary as $pos => $bit)
            if ($binary[$pos] == 1) {
                $this->setPinHigh($pin_array[$bits-$pos-1], $error_code);
            } else {
                $this->setPinLow($pin_array[$bits-$pos-1], $error_code);
            }
        }
        $delay = $period/($count+$off_count);
        if ($this->debug) $this->log(__METHOD__.' D:'.$delay.' V:'.$value , $binary);
        if (empty($select_dir)) {
            return $this->flashPinHighLow($select_pin, $count, $delay/2, $delay/2, $error_code);
        } else {
            return $this->flashPinLowHigh($select_pin, $count, $delay/2, $delay/2, $error_code);
        }
        // putting extra empty delay here??
        // usleep($empty * $delay);
    }

    /**
     * {@inheritdoc}
     */
    public function flashPinHighLow(int $pin_id, ?int $count = 1, ?int $high_delay = 50000, ?int $low_delay = 50000,
                                    ?bool $blocking=false, ?int &$error_code=null) : ?bool
    {
        $return_status = true;
        for ($seq=1; $seq <= $count; $seq++)
        {
            $return_status &= $this->setPinHigh($pin_id, $error_code);
            usleep($high_delay);
            $return_status &= $this->setPinLow($pin_id, $error_code);
            usleep($low_delay);
        }
        return $return_status;
    }

    /**
     * {@inheritdoc}
     */
    public function flashPinLowHigh(int $pin_id, ?int $count = 1, ?int $low_delay = 50000, ?int $high_delay = 50000,
                                    ?bool $blocking=false, ?int &$error_code=null) : ?bool
    {
        $return_status = true;
        for ($seq=1; $seq <= $count; $seq++)
        {
            $return_status &= $this->setPinLow($pin_id, $error_code);
            usleep($low_delay);
            $return_status &= $this->setPinHigh($pin_id, $error_code);
            usleep($high_delay);
        }
        return $return_status;
    }


    /**
     * cleanMshQueue Empty any remaining values in msg_queue and remove
     */
    public function cleanMsgQueue()
    {
        $seg = msg_get_queue(self::MSG_QUEUE_ID);
        $mst = self::MSG_TYPE_GPIO;
        $message = null;
        $received_message_type = null;
        $error_code = null;

        while ( msg_receive(
            $seg,
            $mst,
            $received_message_type,
            self::MSG_MAX_SIZE,
            $message,
            true,
            MSG_IPC_NOWAIT,
            $error_code
        ) )
        {
            if ($this->debug) $this->log(__METHOD__, ['msg_type' => $received_message_type, 'message' => $message]);
        }
        msg_remove_queue($seg);
    }

    /**
     * log error or status to a file
     * @param string $message - text to be logged
     * @param array|null $data - optional $data array passed as a parameter through SysV
     */
    private function log(string $message, ?array $data = null)
    {
        $date = date("Y-m-d H:i:s");
        file_put_contents(self::DEBUG_FILE, $date.'|'.$message.':'.print_r($data,1), FILE_APPEND | LOCK_EX );
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

    /**
     * signal handler function
     * Currently used for SIGALRM Only.
     * The rest are here for show
     */
    function sigAlarmHandler (int $sigNo, array $sigInfo) : int {
        // echo "Interrupt $sigNo :" . print_r($sigInfo, 1);
        switch ($sigNo) {
            case SIGTERM:
                // handle shutdown tasks
                $this->still_running = false;
                $this->cleanMsgQueue();
                exit;
            case SIGHUP:
                // handle restart tasks
                $this->still_running = true;
                $this->cleanMsgQueue();
                break;
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