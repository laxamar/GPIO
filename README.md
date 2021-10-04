# Amar: GPIOSysV

[![License](https://poser.pugx.org/piphp/gpio/license)](https://packagist.org/packages/piphp/gpio)
[![Total Downloads](https://poser.pugx.org/laxamar/gpiosysv/downloads)](https://packagist.org/packages/laxamar/gpiosysv)

A library for low level access to the GPIO pins on a Raspberry Pi. These pins can be used to control outputs (LEDs, motors, valves, pumps) or read inputs (sensors).

Adapted by [laxamar ![(Twitter)](http://i.imgur.com/wWzX9uB.png)](https://twitter.com/laxamar)
## Installing

From [AndrewCarterUK ![(Twitter)](http://i.imgur.com/wWzX9uB.png)](https://twitter.com/AndrewCarterUK)


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
```

### Input Pin Interrupts
```php
use PiPHP\GPIO\GPIO;
use PiPHP\GPIO\Pin\InputPinInterface;

// Create a GPIO object
$gpio = new GPIO();

// Retrieve pin 18 and configure it as an input pin
$pin = $gpio->getInputPin(18);

// Configure interrupts for both rising and falling edges
$pin->setEdge(InputPinInterface::EDGE_BOTH);

// Create an interrupt watcher
$interruptWatcher = $gpio->createWatcher();

// Register a callback to be triggered on pin interrupts
$interruptWatcher->register($pin, function (InputPinInterface $pin, $value) {
    echo 'Pin ' . $pin->getNumber() . ' changed to: ' . $value . PHP_EOL;

    // Returning false will make the watcher return false immediately
    return true;
});

// Watch for interrupts, timeout after 5000ms (5 seconds)
while ($interruptWatcher->watch(5000));
```

## Further Reading

SitePoint published a tutorial about [powering Raspberry Pi projects with PHP](https://www.sitepoint.com/powering-raspberry-pi-projects-with-php/) which used this library and shows a push button example with a wiring diagram.

## More Resources

PiPHP maintains a [resource directory](https://github.com/PiPHP/Resources) for PHP programming on the Raspberry Pi.
