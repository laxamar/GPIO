<?php
namespace PiPHP\GPIO;

// use PiPHP\GPIO\FileSystem\FileSystem;
// use PiPHP\GPIO\FileSystem\FileSystemInterface;
// use PiPHP\GPIO\Interrupt\InterruptWatcher;
// use PiPHP\GPIO\Pin\Pin;
// use PiPHP\GPIO\Pin\InputPin;
// use PiPHP\GPIO\Pin\OutputPin;

/**
 * GPIOSysV Interface class
 *  Defines the functions that both server and client must have and share
 *  There are functions to set and clear pins in individual and list (array) form
 *  Support functions for moving setting pins on or off based on a value as if each pin is a bit
 *  Support for flashing bits on and off based on frequencies
 *
 * @author 	Jacques Amar
 * @copyright (c) 2021	Amar Micro Inc.
 * @version	0.1
 */
interface GPIOSysVInterface extends GPIOInterface
{
    const MSG_QUEUE_ID = '26274746';
    const MSG_BACK_ID  = '47462627';
    const MSG_TYPE_GPIO = '4746';
    const MSG_BACK_GPIO = '6474';
    const MSG_MAX_SIZE = 2048;

    /**
     * Set pin in output mode - HIGH- Turn on any LEDs
     * @param  	int	$pin_id	The GPIO Pin ID used
     * @access 	public
     */
    public function set_pin(int $pin_id, string &$error_code=null) : bool;

    /**
     * Set pin in output mode - LOW - Turn off any LEDs
     * @param  	int	$pin_id	The GPIO Pin ID used
     * @access 	public
     */
    public function clear_pin(int $pin_id, string &$error_code=null) : bool;

    /**
     * Get pin value input mode
     * @param  	int	$pin_id	The GPIO Pin ID used
     * @access 	public
     */
    public function get_pin(int $pin_id, string &$error_code=null) : ?int;

    /**
     * clear a list of pins passed as an array
     * @param  	array	$pin_array	The GPIO pin list
     * @access 	public
     */
    public function all_clear(array $pin_array, string &$error_code=null) : bool ;

    /**
     * Map pins to binary representation of a $value
     * @param int $value - the decimal value to change to binary and map to pins
     * @param array	$pin_array	The list GPIO Pin IDs used
     * @param bool $debug
     * @access 	public
     */
    public function set_binary(int $value, array $pin_array, ?bool $debug=false, string &$error_code=null) : bool;

    /**
     * Flash a list og GPIO pins mapped to a binary representation of a $value controlled by a master $select_pin
     * @param int $value - the decimal value to change to binary and map to pins
     * @param array	$pin_array	The list GPIO Pin IDs used
     * @param int $select_pin the controlling pin that flashes all
     * @param bool $debug
     * @access 	public
     */
    public function flash_binary(int $value, array $pin_array, int $select_pin, ?bool $debug=false, string &$error_code=null) : bool;

    /**
     * Set pin in output mode - HIGH
     * @param  	array	$pin_array	The GPIO Pin ID used
     * @access 	public
     */
    public function blip_binary(int $value, array $pin_array, int $select_pin, ?int $count=1, ?int $empty=1, ?int $period=1000000, ?bool $debug=false, string &$error_code=null) : bool;

    /**
     * Flash a pin (HIGH then LOW) instead of turning it simply on
     * @param  	int	$pin_id	The GPIO Pin ID used
     * @access 	public
     */
    public function flash_bit(int $pin_id, ?int $count = 1, ?int $on_delay = 50000, ?int $off_delay = 50000, ?string &$error_code=null) : bool;

}