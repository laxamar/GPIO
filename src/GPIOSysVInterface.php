<?php
namespace Amar\GPIOSysV;

/**
 * GPIOSysV Interface class
 *  Defines the functions that both server and client must have and share
 *  There are functions to set and clear pins in individual and list (array) form
 *  Support functions for moving setting pins on or off based on a value as if each pin is a bit
 *  Support for flashing bits on and off based on frequencies
 *  Support for blocking on client to allow proper timing
 *
 * @author 	Jacques Amar
 * @copyright (c) 2021	Amar Micro Inc.
 * @version	1.0
 */
interface GPIOSysVInterface
{
    const MSG_QUEUE_ID = 0x26274746;
    const MSG_BACK_ID  = 0x47462627;
    const MSG_TYPE_GPIO = 0x4746;
    const MSG_BACK_GPIO  = 0x6474;
    const MSG_BACK_ARRAY = 0x6475;
    const MSG_MAX_SIZE = 2048;

    /**
     * Set pin in output mode with pin_value
     * @param int $pin_id
     * @param int $pin_value
     * @param int|null $error_code
     * @return bool|null
     * @access public
     */
    public function setPin(int $pin_id, int $pin_value, ?int &$error_code=null) : ?bool;

    /**
     * Set pin in output mode - HIGH- Turn on any LEDs
     * @param  int	$pin_id	The GPIO Pin ID used
     * @param  int|null $error_code bubble up error code description
     * @return bool|null
     * @access 	public
     */
    public function setPinHigh(int $pin_id, ?int &$error_code=null) : ?bool;

    /**
     * Set pin in output mode - LOW - Turn off any LEDs
     * @param  	int	$pin_id	The GPIO Pin ID used
     * @param   int|null $error_code bubble up error code description
     * @return bool|null
     * @access 	public
     */
    public function setPinLow(int $pin_id, ?int &$error_code=null) : ?bool;

    /**
     * Get pin value input mode
     * @param  	int	$pin_id	The GPIO Pin ID used
     * @param   int|null $error_code bubble up error code description
     * @return int|null
     * @access 	public
     */
    public function getPin(int $pin_id, ?int &$error_code=null) : ?int;

    /**
     * get the status of an array of pins
     * @param array $pin_array
     * @param int|null $error_code
     * @return array|null
     * @access public
     */
    public function getPinArray(array $pin_array, ?int &$error_code = null) : ?array;

    /**
     * set an array of pins to Low
     * @param  array	$pin_array	The GPIO pin list
     * @param  int|null $error_code bubble up error code description
     * @return bool|null
     * @access public
     */
    public function setArrayLow(array $pin_array, ?int &$error_code=null) : ?bool ;

    /**
     * set an Array of pins High
     * @param array	$pin_array	The GPIO pin list
     * @param int|null $error_code bubble up error code description
     * @return bool|null
     * @access 	public
     */
    public function setArrayHigh(array $pin_array, ?int &$error_code=null) : ?bool ;

    /**
     * Map pins to binary representation of a $value
     * @param int $value - the decimal value to change to binary and map to pins
     * @param array	$pin_array	The list GPIO Pin IDs used
     * @param int|null $error_code bubble up error code description
     * @return bool|null
     * @access 	public
     */
    public function setPinsBinary(int $value, array $pin_array, ?int &$error_code=null) : ?bool;

    /**
     * Flash a list of GPIO pins ONCE mapped to a binary representation of a $value controlled by a master $select_pin
     * @param int $value - the decimal value to change to binary and map to pins
     * @param array	$pin_array	The list GPIO Pin IDs used
     * @param int $select_pin the controlling pin that flashes all
     * @param int|null $high_delay how long HIGH status will last (in useconds)
     * @param int|null $low_delay how long LOW status will last (in useconds)
     * @param bool|null $blocking block the caller function to allow for proper timing between client/server
     * @param int|null $error_code bubble up error code description
     * @return bool|null
     * @access 	public
     */
    public function flashBinary(int $value, array $pin_array, int $select_pin, ?int $high_delay = 50000, ?int $low_delay = 50000, ?bool $blocking=false, ?int &$error_code=null) : ?bool;

    /**
     * Strobe a BCD array multiple times
     * @param int $value to be BCD coded and strobed
     * @param array	$pin_array	The GPIO Pin ID used
     * @param int $select_pin the controlling pin that will do the strobing
     * @param int|null $count the number of times to strobe up during $period
     * @param int|null $off_count extra amount of off time for better timing after the strobe during $period
     * @param int|null $period time in useconds that the whole sequence of strobe on/off and empty wait will take place
     * @param bool|null $blocking block the caller function to allow for proper timing between client/server / will block for $period for client
     * @param int|null $error_code bubble up error code description
     * @return bool|null
     * @access 	public
     */
    public function strobeBinary(int $value, array $pin_array, int $select_pin, ?int $count=1, ?int $off_count=1, ?int $period=1000000, ?bool $blocking=false, ?int &$error_code=null) : ?bool;

    /**
     * Flash a pin (HIGH then LOW) instead of turning it simply on/off
     * @param int $pin_id	The GPIO Pin ID used
     * @param int|null $count the number of times to flash HIGH then LOW
     * @param int|null $high_delay how long HIGH status will last (in useconds)
     * @param int|null $low_delay how long LOW status will last (in useconds)
     * @param bool|null $blocking block the caller function to allow for proper timing between client/server ($high_delay+$low_delay)
     * @param int|null $error_code bubble up error code description
     * @access 	public
     */
    public function flashPinHighLow(int $pin_id, ?int $count = 1, ?int $high_delay = 50000, ?int $low_delay = 50000, ?bool $blocking=false, ?int &$error_code=null) : ?bool;

    /**
     * Flash a pin (LOW then HIGH) instead of turning it simply on/off
     * @param  	int	$pin_id	The GPIO Pin ID used
     * @param int|null $count the number of times to flash HIGH then LOW
     * @param int|null $low_delay how long LOW status will last (in useconds)
     * @param int|null $high_delay how long HIGH status will last (in useconds)
     * @param bool|null $blocking block the caller function to allow for proper timing between client/server ($high_delay+$low_delay)
     * @param int|null $error_code bubble up error code description
     * @return bool|null
     * @access 	public
     */
    public function flashPinLowHigh(int $pin_id, ?int $count = 1, ?int $low_delay = 50000, ?int $high_delay = 50000, ?bool $blocking=false, ?int &$error_code=null) : ?bool;

}