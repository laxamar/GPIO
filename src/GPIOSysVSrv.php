<?php

namespace Amar\GPIOSysV;

use PiPHP\GPIO\FileSystem\FileSystem;
use PiPHP\GPIO\FileSystem\FileSystemInterface;
use PiPHP\GPIO\Interrupt\InterruptWatcher;
use PiPHP\GPIO\Pin\PinInterface;
use PiPHP\GPIO\Pin\Pin;
use PiPHP\GPIO\Pin\InputPin;
use PiPHP\GPIO\Pin\OutputPin;
use const PiPHP\GPIO\PinInterface\VALUE_HIGH as VALUE_HIGH;
use const PiPHP\GPIO\PinInterface\VALUE_LOW as VALUE_LOW;

class GPIOSysVSrv implements GPIOSysVInterface
{
    static private $instance;
    private $gpio_obj;
    private $debug;
    public  $still_running;

    const DEBUG_FILE = '/var/tmp/GPIOSysVSrv.log';


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
                if ($this->debug) $this->log( 'Messages Received : ', $data );
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

                $error_code = null; // need a variable
                $success    = true;
                switch ( $function_call )
                {
                    case 'setPinHigh':
                        $pin_id = $data['parms']['pin_id'] ?? null;
                        if (empty($pin_id)) {
                            $success = false;
                            $this->log($function_call.' with empty pin_id', $data);
                            break;
                        }
                        $success &= $this->setPinHigh($pin_id, $error_code);
                        break;
                    case 'setPinLow':
                        $pin_id = $data['parms']['pin_id'] ?? null;
                        if (empty($pin_id)) {
                            $success = false;
                            $this->log($function_call.' with empty pin_id', $data);
                            break;
                        }
                        $success &= $this->setPinLow($pin_id, $error_code);
                        break;
                    case 'getPin':
                        $pin_id = $data['parms']['pin_id'] ?? null;
                        $pin_status = $this->getPin($pin_id, $error_code);
                        $this->msg_back($data, ['pin_status' => $pin_status], $error_code);
                        break;
                    case 'setArrayLow':
                        $pin_array = $data['parms']['pin_array'] ?? [];
                        if (empty($pin_array)) {
                            $success = false;
                            $this->log($function_call. ' with empty array', $data);
                            break;
                        }
                        $success &= $this->setArrayLow($pin_array, $error_code);
                        break;
                    case 'setArrayHigh':
                        $pin_array = $data['parms']['pin_array'] ?? [];
                        if (empty($pin_array)) {
                            $success = false;
                            $this->log($function_call. ' with empty array', $data);
                            break;
                        }
                        $success &= $this->setArrayHigh($pin_array, $error_code);
                        break;
                    case 'setPinsBinary':
                        $dec_value = $data['parms']['dec_value'] ?? null;
                        $pin_array = $data['parms']['pin_array'] ?? [];
                        if (is_null($dec_value) || empty($pin_array))
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
                        if (is_null($dec_value) || empty($pin_array) || empty($select_pin))
                        {
                            $success = false;
                            $this->log($function_call. ' with empty values', $data);
                            break;
                        }
                        $success &= $this->flashBinary($dec_value, $pin_array, $select_pin, $error_code);
                        break;
                    case 'strobeBinary':
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
                        $success &= $this->strobeBinary($dec_value, $pin_array, $select_pin, $count, $empty, $period, $error_code);
                        break;
                    case 'flashPinHighLow':
                        $pin_id    = $data['parms']['pin_id'] ?? null;
                        $count     = $data['parms']['count'] ?? null;
                        $on_delay  = $data['parms']['on_delay'] ?? null;
                        $off_delay = $data['parms']['off_delay'] ?? null;
                        if (empty($pin_id)) {
                            $success = false;
                            $this->log($function_call.' with empty pin_id', $data);
                            break;
                        }
                        $success &= $this->flashPinHighLow($pin_id, $count, $on_delay, $off_delay, $error_code);

                        break;
                    case 'flashPinLowHigh':
                        $pin_id    = $data['parms']['pin_id'] ?? null;
                        $count     = $data['parms']['count'] ?? null;
                        $on_delay  = $data['parms']['on_delay'] ?? null;
                        $off_delay = $data['parms']['off_delay'] ?? null;
                        if (empty($pin_id)) {
                            $success = false;
                            $this->log($function_call.' with empty pin_id', $data);
                            break;
                        }
                        $success &= $this->flashPinLowHigh($pin_id, $count, $on_delay, $off_delay, $error_code);

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
        pcntl_signal(SIGALARM, null);

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
    function setPinHigh($pin_id, &$error_code = null) : bool
    {
        $pin = $this->gpio_obj->getOutputPin($pin_id);
        if ($this->debug) $this->Log('VALUE_HIGH:'.print_r(self::VALUE_HIGH,1));
        $pin->setValue(self::VALUE_HIGH);
        return true;
    }

    /**
     *  {@inheritdoc}
     */
    function setPinLow($pin_id, &$error_code = null) : bool
    {
        $pin = $this->gpio_obj->getOutputPin($pin_id);
        if ($this->debug) $this->Log('VALUE_LOW:'.print_r(self::VALUE_LOW,1));
        return $pin->setValue(self::VALUE_LOW);
    }

    /**
     *  {@inheritdoc}
     */
    function getPin($pin_id, &$error_code = null) : ?int
    {
        $pin = $this->gpio_obj->getOutputPin($pin_id);
        return $pin->getValue();
    }

    /**
     *  {@inheritdoc}
     */
    function setArrayLow($pin_array, &$error_code = null) : bool
    {
        foreach ($pin_array as $pin_id) {
            $this->setPinLow($pin_id);
        }
        return true;
    }

    /**
     *  {@inheritdoc}
     */
    function setArrayHigh($pin_array, &$error_code = null) : bool
    {
        foreach ($pin_array as $pin_id) {
            $this->setPinHigh($pin_id);
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    function setPinsBinary($value, $pin_array, string &$error_code=null) : bool
    {
        $bits = sizeof($pin_array);
        $binary = str_split(sprintf('%0'.$bits.'b', $value),1);
        if ($this->debug) $this->log($value . ' => '. print_r($binary,1));
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
     * @param int $value
     * @param array $pin_array
     * @param int $select_pin
     * @param string|null $error_code
     * @return bool
     */
    function flashBinary(int $value, array $pin_array, int $select_pin, ?string &$error_code=null) : bool
    {
        $success = true;
        // set pin to turn off output
        $success &= $this->setPinLow($select_pin, $error_code);
        // Calculate binary pins
        $bits = sizeof($pin_array);
        $binary = str_split(sprintf('%0'.$bits.'b', $value),1);
        if ($this->debug) $this->log('DEBUG: $binary'. print_r($binary,1) );
        for ($pos=0;$pos < $bits; $pos++) {
            // foreach ($binary as $pos => $bit)
            if ($binary[$pos] == 1) {
                $success &= $this->setPinHigh($pin_array[$bits-$pos-1], $error_code);
            } else {
                $success &= $this->setPinLow($pin_array[$bits-$pos-1], $error_code);
            }
        }
        return $this->flashPinHighLow($select_pin, $error_code);
    }

    /**
     * @param int|null $value
     * @param array $pin_array
     * @param int|null $select_pin
     * @param int|null $count
     * @param int|null $empty
     * @param int|null $period
     * @param string|null $error_code
     * @return bool
     */
    function strobeBinary(int $value, array $pin_array, int $select_pin, ?int $count=1, ?int $empty=0, ? int $period=1000000,
                         ?string &$error_code=null) : bool
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
        $delay = $period/($count+$empty);
        if ($this->debug) $this->log('D:'.$delay.' '.$value . ' => '. print_r($binary,1));
        return $this->flashPinHighLow($select_pin, $count, $delay/2, $delay/2, $error_code);
        // putting extra empty delay here??
        // usleep($empty * $delay);
    }

    /**
     * flashPinHighLow - Flash a pin instead of turning it on.
     *    loop $count time
     *        turn on - wait $on_delay - turn off - wait $off_delay
     * @param int $pin_id
     * @param int|null $count number of times to flash
     * @param int|null $on_delay in useconds
     * @param int|null $off_delay in useconds
     * @param string|null $error_code
     * @return bool
     */
    function flashPinHighLow(int $pin_id, ?int $count = 1, ?int $on_delay = 50000, ?int $off_delay = 50000, ?string &$error_code=null) : bool
    {
        $return_status = true;
        for ($seq=1; $seq <= $count; $seq++)
        {
            $return_status &= $this->setPinHigh($pin_id, $error_code);
            usleep($on_delay);
            $return_status &= $this->setPinLow($pin_id, $error_code);
            usleep($off_delay);
        }
        return $return_status;
    }

    /**
     * flashPinLowHigh - Flash a pin Low then High instead of turning it on/off.
     *    loop $count time
     *        turn on - wait $on_delay - turn off - wait $off_delay
     * @param int $pin_id
     * @param int|null $count number of times to flash
     * @param int|null $on_delay in useconds
     * @param int|null $off_delay in useconds
     * @param string|null $error_code
     * @return bool
     */
    function flashPinLowHigh(int $pin_id, ?int $count = 1, ?int $on_delay = 50000, ?int $off_delay = 50000, ?string &$error_code=null) : bool
    {
        $return_status = true;
        for ($seq=1; $seq <= $count; $seq++)
        {
            $return_status &= $this->setPinLow($pin_id, $error_code);
            usleep($on_delay);
            $return_status &= $this->setPinHigh($pin_id, $error_code);
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
        file_put_contents(self::DEBUG_FILE, $message.':'.print_r($data,1), FILE_APPEND | LOCK_EX );
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
     * Currently used for SIGALARM Only.
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