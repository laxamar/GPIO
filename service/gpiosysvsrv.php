<?php

/**
 *
 */

namespace Amar\GPIOSysV;
require 'vendor/autoload.php';

use Amar\GPIOSysV\GPIOSysVSrv;

define('PID_FILE', "/var/run/" . basename($argv[0], ".php") . ".pid");

if (!empty($pid_error = tryPidLock()))
    die($pid_error."\n");


/**
 * signal handler function
 * Not all are implemented
 */
function sig_handler (int $sigNo, $sigInfo) : int {
    echo "Interrupt $sigNo :" . print_r($sigInfo, 1);
    $gpio_obj = GPIOSysVSrv::getInstance(); // let's get the same instance
    switch ($sigNo) {
        case SIGTERM:
            // handle shutdown tasks
            $gpio_obj->still_running = false;
            $gpio_obj->cleanMsgQueues();
            exit;
        case SIGHUP:
            // handle restart tasks
            $gpio_obj->still_running = true;
            $gpio_obj->cleanMsgQueues();
            break;
        case SIGUSR1:
            echo "Caught SIGUSR1...\n";
            break;
        default:
            // handle all other signals
            break;
    }
    return 0;
}

# remove the lock on exit (Control+C doesn't count as 'exit'?)
register_shutdown_function('unlink', PID_FILE);

pcntl_async_signals(TRUE);

// setup signal handlers
pcntl_signal(SIGTERM, "sig_handler");
pcntl_signal(SIGHUP,  "sig_handler");
pcntl_signal(SIGUSR1, "sig_handler");

$gpio_obj = GPIOSysVSrv::getInstance();
$gpio_obj->still_running = true;
while ($gpio_obj->still_running) {
    $gpio_obj->process_queue();
}

exit;

/**
 * Try to create a run file with the pid of the current process name and make sure no two processes run it
 * @return string - error message or empty
 */
function tryPidLock() : ?string
{
    # If pid file exists, check if stale.  If exists and is not stale, return TRUE
    # Else, create pid file and return FALSE.

    if ($pid_file = @fopen(PID_FILE, 'x'))
    {
        fwrite($pid_file, getmypid());
        fclose($pid_file);
        return '';
    }

    # pid file already exists
    # check if it's stale
    if (is_file(PID_FILE))
    {
        if (is_dir(PID_FILE))
        {
            return 'PID file '.PID_FILE.' points to a directory.';
        }
        if (is_writable(PID_FILE)) {
            unlink(PID_FILE);
            # try to lock again
            return tryPidLock();
        } else {
            return 'PID file '.PID_FILE.' is not writeable.';
        }
    }

    return 'Could not create PID file '.PID_FILE;
}

