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

    const VALUE_LOW = 0;
    const VALUE_HIGH = 1;

    const ARRAY_FILTER_OPTIONS = [
        'pin_id'     => [
            'filter' => FILTER_VALIDATE_INT,
            'flags'    => FILTER_REQUIRE_SCALAR,
            'options' => array('min_range' => 1, 'max_range' => 40)
        ],
        'pin_value' => [
            'filter' => FILTER_VALIDATE_INT,
            'flags'    => FILTER_REQUIRE_SCALAR,
            'options' => array('min_range' => self::VALUE_LOW, 'max_range' => self::VALUE_HIGH)
        ],
        'value' => [
            'filter' => FILTER_VALIDATE_INT,
            'options' => array('min_range' => 0)
        ],
        'pin_array' => [
            'filter'   => FILTER_VALIDATE_INT,
            'flags'    => FILTER_REQUIRE_ARRAY || FILTER_REQUIRE_SCALAR,
            'options' => array('min_range' => 1, 'max_range' => 40)
        ],
        'value_array' => [
            'filter'   => FILTER_VALIDATE_INT,
            'flags'    => FILTER_REQUIRE_ARRAY || FILTER_REQUIRE_SCALAR,
            'options' => array('min_range' => self::VALUE_LOW, 'max_range' => self::VALUE_HIGH)
        ],
        'select_pin' => [
            'filter' => FILTER_VALIDATE_INT,
            'flags'    => FILTER_REQUIRE_SCALAR,
            'options' => array('min_range' => 1, 'max_range' => 40)
        ]
    ];


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
     * Set pin in output mode - HIGH - Turn on any LEDs
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
     * @param int|null $select_dir the controlling pin goes 0=>High/Low or 1=>Low/High
     * @param int|null $high_delay how long HIGH status will last (in useconds)
     * @param int|null $low_delay how long LOW status will last (in useconds)
     * @param bool|null $blocking block the caller function to allow for proper timing between client/server
     * @param int|null $error_code bubble up error code description
     * @return bool|null
     * @access 	public
     */
    public function flashBinary(int $value, array $pin_array, int $select_pin, ?int $select_dir=0, ?int $high_delay = 50000, ?int $low_delay = 50000, ?bool $blocking=false, ?int &$error_code=null) : ?bool;

    /**
     * Strobe a BCD array multiple times
     * @param int $value to be BCD coded and strobed
     * @param array	$pin_array	The GPIO Pin ID used
     * @param int $select_pin the controlling pin that will do the strobing
     * @param int|null $select_dir the direction of the flash 0-> High/Low 1->Low/High
     * @param int|null $count the number of times to strobe up during $period
     * @param int|null $off_count extra amount of off time for better timing after the strobe during $period
     * @param int|null $period time in useconds that the whole sequence of strobe on/off and empty wait will take place
     * @param bool|null $blocking block the caller function to allow for proper timing between client/server / will block for $period for client
     * @param int|null $error_code bubble up error code description
     * @return bool|null
     * @access 	public
     */
    public function strobeBinary(int $value, array $pin_array, int $select_pin, ?int $select_dir=0, ?int $count=1, ?int $off_count=1, ?int $period=1000000, ?bool $blocking=false, ?int &$error_code=null) : ?bool;

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

    /**
     * Shift a bit using the pins assigned to a shift register
     * @param array $PINs	The GPIO Pins used
     * @param int $bit the bit to shift in
     * @param int|null $delay how long HIGH status will last (in useconds)
     * @param bool|null $blocking block the caller function to allow for proper timing between client/server (2*$delay)
     * @param int|null $error_code bubble up error code description
     * @return bool|null
     * @access 	public
     */
    public function shiftDataBit(array $PINs, int $bit, ?int $delay=null, ?bool $blocking=false, ?int &$error_code=null) : ?bool;

    /**
     * Shift an array of bist using the pins assigned to a shift register
     * @param array $PINs	The GPIO Pins used
     * @param int $bit_array the bit to shift in
     * @param int|null $delay how long HIGH status will last (in useconds)
     * @param bool|null $blocking block the caller function to allow for proper timing between client/server (2*$delay)
     * @param int|null $error_code bubble up error code description
     * @return bool|null
     * @access 	public
     */
    public function shiftDataArray(array $PINs, array $bit_array, ?int $delay=null, ?bool $blocking=false, ?int &$error_code=null) : ?bool;

}