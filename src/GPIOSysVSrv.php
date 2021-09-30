<?php

namespace PiPHP\GPIO;

class GPIOSysVSrv implements GPIOSysVInterface
{

    static private $instance;
    private $gpio_obj;
    private $debug;

    static public function getInstance()
    {
        if( !isset( self::$instance ) )
        {
            $this_class = get_called_class();
            $_local_obj = new $this_class();

            $_local_obj->gpio_obj = new GPIOSysVSrv();

            self::$instance = $_local_obj;
        };
        return self::$instance;
    }

    function process_queue()
    {
        $seg      = msg_get_queue(MSQ_QUEUE_ID);
        $msg_type = MSG_TYPE_GPIO;
        $data     = null;
        $error_code = null;
        
        while ($still_running)
        {
            $stat = msg_stat_queue($seg);
            if ($debug) echo 'Messages in the queue: ' . $stat['msg_qnum'] . "\n";
            if ($stat['msg_qnum'] > 0) {
                msg_receive($seg, MSG_TYPE_GPIO, $msg_type, MSG_MAX_SIZE, $data, true, 0, $error_code);
                // check for errors
                if (!empty($error_code))
                {
                    $this->log('Error code :'.$error_code);
                    continue;
                }
                if ($msg_type != MSG_TYPE_GPIO)
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

                switch ( $function_call )
                {
                    case 'set_pin':
                        $pin_id = $data['parms']['pin_id'] ?? null;
                        $this->set_pin($pin_id);
                        break;
                    case 'clear_pin':
                        $pin_id = $data['parms']['pin_id'] ?? null;
                        $this->clear_pin($pin_id);
                        break;
                    case 'get_pin ':
                        $pin_id = $data['parms']['pin_id'] ?? null;
                        $pin_status = $this->get_pin($pin_id);
                        $this->msg_back($data, ['return' => $pin_status]);
                        break;
                    case 'all_clear':
                        $pin_array = $data['parms']['pin_array'] ?? [];
                        if (!empty($pin_array)) {
                            $this->all_clear($pin_array);
                        } else {
                            $this->log('all_clear with empty array', $data);
                        }
                        break;
                    case 'set_binary':
                        $dec_value = $data['parms']['dec_value'] ?? null;
                        $pin_array = $data['parms']['pin_array'] ?? [];
                        if (!is_null($dec_value) && !empty($pin_array))
                        {
                            $this->set_binary($dec_value, $pin_array);
                        } else {
                            $this->log('set_binary with empty values', $data);
                        }
                        break;
                    case 'flash_binary':
                        $dec_value = $data['parms']['dec_value'] ?? null;
                        $pin_array = $data['parms']['pin_array'] ?? [];
                        if (!is_null($dec_value) && !empty($pin_array))
                        {
                            $this->flash_binary($dec_value, $pin_array);
                        } else {
                            $this->log('flash_binary with empty values', $data);
                        }
                        break;
                    case 'blip_binary':

                        break;
                    case 'flash_bit':

                        break;
                    default:
                        // Log error
                        $this->log('Received non existent function call type '.$function_call, $data);
                        continue;

                        break;
                }
            }
        }
    }

    function set_pin($pin_id) {
        $pin = $this->gpio_obj->getOutputPin($pin_id);
        $pin->setValue(PinInterface::VALUE_HIGH);
    }

    function clear_pin($pin_id) {
        $pin = $this->gpio_obj->getOutputPin($pin_id);
        $pin->setValue(PinInterface::VALUE_LOW);
    }

    function get_pin($pin_id) {
        $pin = $this->gpio_obj->getOutputPin($pin_id);
        return $pin->getValue();
    }

    function all_clear($pin_array) {
        foreach ($pin_array as $pin_id) {
            $this->clear_pin($pin_id);
        }
    }

    function set_binary($value, $pin_array, $debug=false)
    {
        $bits = sizeof($pin_array);
        // $binary = array_reverse(str_split(decbin($value),1));
        $binary = array_reverse(str_split(sprintf('%0'.$bits.'b', $value),1));
        if ($debug) echo $value . ' => '. print_r($binary,1);
        // $this->all_off($PIN_ARRAY);
        for ($pos=0;$pos < $bits; $pos++) {
            // foreach ($binary as $pos => $bit)
            if ($binary[$pos] == 1) {
                $this->set_pin($pin_array[$pos]);
            } else {
                $this->clear_pin($pin_array[$pos]);
            }
        }
    }

    function flash_binary($value = null, $pin_array = [], $toggle_pin = null, $debug=false)
    {
        // set pin to turn off output
        $this->clear_pin($toggle_pin);
        // Calculate binary pins
        $bits = sizeof($pin_array);
        $binary = str_split(sprintf('%0'.$bits.'b', $value),1);
        if ($debug) $this->log('DEBUG: $binary'. print_r($binary,1) );
        for ($pos=0;$pos < $bits; $pos++) {
            // foreach ($binary as $pos => $bit)
            if ($binary[$pos] == 1) {
                $this->set_pin($pin_array[$bits-$pos-1]);
            } else {
                $this->clear_pin($pin_array[$bits-$pos-1]);
            }
        }
        $this->flash_bit($toggle_pin);
    }

    function blip_binary($value = null, $pin_array = [], $toggle_pin = null, $count=1, $empty=0, $period=1000000, $debug=false)
    {
        // set pin to turn off output
        $this->clear_pin($toggle_pin);
        // Calculate binary pins
        $bits = sizeof($pin_array);
        $binary = str_split(sprintf('%0'.$bits.'b', $value),1);
        for ($pos=0;$pos < $bits; $pos++) {
            // foreach ($binary as $pos => $bit)
            if ($binary[$pos] == 1) {
                $this->set_pin($pin_array[$bits-$pos-1]);
            } else {
                $this->clear_pin($pin_array[$bits-$pos-1]);
            }
        }
        $delay = $period/($count+$empty);
        if ($debug) $this->log('D:'.$delay.' '.$value . ' => '. print_r($binary,1));
        $this->flash_bit($toggle_pin, $count, $delay/2, $delay/2);
        // putting extra empty delay here??
        // usleep($empty * $delay);
    }

    /**
     * flash_pin - Flash a apin instead of turning it on
     */
    function flash_bit($pin_id, $count = 1, $on_delay = 50000, $off_delay = 50000)
    {
        for ($seq=1; $seq <= $count; $seq++)
        {
            $this->clear_pin($PIN_ID);
            usleep($on_delay);
            $this->set_pin($PIN_ID);
            usleep($off_delay);
        }
        // usleep()
    }

    private function log($message, $data = null)
    {

    }


}