# Amar: GPIOSysV

[![License](https://poser.pugx.org/piphp/gpio/license)](https://packagist.org/packages/piphp/gpio)
[![Total Downloads](https://poser.pugx.org/laxamar/gpiosysv/downloads)](https://packagist.org/packages/laxamar/gpiosysv)

A userland (non-root) library for low level access to the GPIO pins on a Raspberry Pi. These pins can be used to control outputs (LEDs, motors, valves, pumps) or read inputs (sensors).

Adapted by [laxamar ![(Twitter)](http://i.imgur.com/wWzX9uB.png)](https://twitter.com/laxamar)
## Installing

From [AndrewCarterUK ![(Twitter)](http://i.imgur.com/wWzX9uB.png)](https://twitter.com/AndrewCarterUK)

This release has two components. A server that can be installed via git orcomposer. A client that uses composer to install and run.

Using [composer](https://getcomposer.org/):

`composer require laxamar/gpiosysv`

Or:

`php composer.phar require laxamar/gpiosysv`

## Examples

### Setting Output Pins
```php
use Amar\GPIOSysV\GPIOSysVClt;

// Create a GPIO object
$gpio_obj = GPIOSysVClt::getInstance();

// Set the value of the pin high (turn it on)
$success = $gpio_obj->setPinHigh(18);

$success = $gpio_obj->setPinLow(18);

// Set a series on PINs using BCD
$gpio->setPinsBinary($board, CS_PINs);
for ($dec = 0; $dec < 8; $dec++) {
    // echo "Decimal $dec";
    $gpio->strobeBinary($dec, LED_PINs, FLASH_PIN, 1, 0, $frequency, true, $error);
}

```

### Input Pin
```php
use Amar\GPIOSysV\GPIOSysVClt;

// Create a GPIO object
$gpio_obj = GPIOSysVClt::getInstance();

// Set the value of the pin high (turn it on)
$value = $gpio_obj->getPin(4);

```

## Further Reading

SitePoint published a tutorial about [powering Raspberry Pi projects with PHP](https://www.sitepoint.com/powering-raspberry-pi-projects-with-php/) which used this library and shows a push button example with a wiring diagram.

## More Resources

PiPHP maintains a [resource directory](https://github.com/PiPHP/Resources) for PHP programming on the Raspberry Pi.
