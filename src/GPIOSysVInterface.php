<?php
namespace PiPHP\GPIO;

use PiPHP\GPIO\FileSystem\FileSystem;
use PiPHP\GPIO\FileSystem\FileSystemInterface;
use PiPHP\GPIO\Interrupt\InterruptWatcher;
use PiPHP\GPIO\Pin\Pin;
use PiPHP\GPIO\Pin\InputPin;
use PiPHP\GPIO\Pin\OutputPin;

/**
 * GPIOSysV Interface class
 *  Defines the functions that both server and client must have and share
 *  There are functions to set and clear pins in individual and list (array) form
 *  Support functions for moving setting pins on or off based on a value as if each pin is a bit
 *  Support for flashing bits on and off based on frequencies
 *
 * @author 	Jacques Amar
 * @copyright	Amar Micro Inc. 2021
 * @version	0.1
 */
interface GPIOSysVInterface extends GPIOInterface
{
    const MSG_QUEUE_ID = '26274746';
    const MSG_TYPE_GPIO = '4746';

    /**
     * Set pin in output mode - HIGH- Turn on any LEDs
     * @param  	int	$pin_id	The GPIO Pin ID used
     * @access 	public
     * @author 	Jacques Amar
     * @copyright	Amar Micro Inc. 2021
     * @version	0.1
     */
    public function set_pin(int $pin_id);

    /**
     * Set pin in output mode - LOW - Turn off any LEDs
     * @param  	int	$pin_id	The GPIO Pin ID used
     * @access 	public
     * @author 	Jacques Amar
     * @copyright	Amar Micro Inc. 2021
     * @version	0.1
     */
    public function clear_pin(int $pin_id);

    /**
     * Get pin value input mode
     * @param  	int	$pin_id	The GPIO Pin ID used
     * @access 	public
     * @author 	Jacques Amar
     * @copyright	Amar Micro Inc. 2021
     * @version	0.1
     */
    public function get_pin(int $pin_id);

    /**
     * clear a list of pins passed as an array
     * @param  	array	$pin_array	The GPIO pin list
     * @access 	public
     * @author 	Jacques Amar
     * @copyright	Amar Micro Inc. 2021
     * @version	0.1
     */
    public function all_clear(array $pin_array);

    /**
     * Set pin in output mode - HIGH
     * @param  	int	$pin_id	The GPIO Pin ID used
     * @access 	public
     * @author 	Jacques Amar
     * @copyright	Amar Micro Inc. 2021
     * @version	0.1
     */
    public function set_binary($value, $PIN_ARRAY, $debug=false);

    /**
     * Set pin in output mode - HIGH
     * @param  	int	$pin_id	The GPIO Pin ID used
     * @access 	public
     * @author 	Jacques Amar
     * @copyright	Amar Micro Inc. 2021
     * @version	0.1
     */
    public function flash_binary($value, $PIN_ARRAY, $SELECT_PIN, $debug=false);

    /**
     * Set pin in output mode - HIGH
     * @param  	int	$pin_id	The GPIO Pin ID used
     * @access 	public
     * @author 	Jacques Amar
     * @copyright	Amar Micro Inc. 2021
     * @version	0.1
     */
    public function blip_binary($value, $PIN_ARRAY, $SELECT_PIN, $count=2, $empty=2, $period=1000000, $debug=false);

    /**
     * Flash a pin (HIGH then LOW) instead of turning it simply on
     * @param  	int	$pin_id	The GPIO Pin ID used
     * @access 	public
     * @author 	Jacques Amar
     * @copyright	Amar Micro Inc. 2021
     * @version	0.1
     */
    public function flash_bit(int $pin_id, $count = 5, $on_delay = 50000, $off_delay = 50000);

}