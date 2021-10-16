<?php

/**
 * gpiosysvsrv - SystemV Main Server loop that runs the process_queue
 * @author Jacques Amar
 * @copyright 2019-2021 Amar Micro Inc.
 */

$lib_path = dirname(__FILE__). '/../src';
set_include_path(get_include_path() . PATH_SEPARATOR . $lib_path);

$di = new RecursiveDirectoryIterator($lib_path);
foreach (new RecursiveIteratorIterator($di) as $filename => $file) {
    if ($file->isDir) {
        set_include_path(get_include_path() . PATH_SEPARATOR . $filename);
    }
}

// Take off dependence on composer
// require 'vendor/autoload.php';

spl_autoload_register(function ($class_name) {

    $class_leaf = substr(strrchr($class_name, '\\'), 1).'.php';
    echo $class_name.'|'.$class_leaf."\n";
    // $file_name = get_include_path()."/".$class_name . '.php';
    // $file_name = str_replace("\\", "/", $file_name);
    require_once($class_leaf);
});

use Amar\GPIOSysV\GPIOSysVSrv;
use PiPHP\GPIO\FileSystem\FileSystem;
use PiPHP\GPIO\FileSystem\FileSystemInterface;
use PiPHP\GPIO\Interrupt\InterruptWatcher;
use PiPHP\GPIO\Pin\Pin;
use PiPHP\GPIO\Pin\InputPin;
use PiPHP\GPIO\Pin\OutputPin;

$gpio_obj = GPIOSysVSrv::getInstance();

// track errors to DEBUG file
ini_set('error_log', GPIOSysVSrv::DEBUG_FILE);


define('PID_FILE', "/run/" . basename($argv[0], ".php") . ".pid");

// fork: a twin process is created
if(($pid = pcntl_fork()) == -1) { exit("Error forking...\n"); }

if($pid != 0) {
    // Parent code goes here
    exit();
}

if (!empty($pid_error = tryPidLock()))
    die($pid_error."\n");

# remove the lock on exit (Control+C doesn't count as 'exit'?)
register_shutdown_function('unlink', PID_FILE);

pcntl_async_signals(TRUE);

// setup signal handlers
pcntl_signal(SIGTERM, "sigHandler");
pcntl_signal(SIGHUP,  "sigHandler");
pcntl_signal(SIGUSR1, "sigHandler");
// DO NOT TAKE OVER SIGALRM!! Used internally by processQueue

// $gpio_obj->setDebug(true);
$gpio_obj->still_running = true;
while ($gpio_obj->still_running) {
    $gpio_obj->processQueue();
}

exit();

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

/**
 * signal handler function
 * Not all are implemented
 */
function sigHandler (int $sigNo, array $sigInfo) : int {
    echo "Interrupt $sigNo :" . print_r($sigInfo, 1);
    $gpio_obj = GPIOSysVSrv::getInstance(); // let's get the same instance
    switch ($sigNo) {
        case SIGTERM:
            // handle shutdown tasks
            $gpio_obj->still_running = false;
            $gpio_obj->cleanMsgQueue();
            exit;
        case SIGHUP:
            // handle restart tasks
            $gpio_obj->still_running = true;
            $gpio_obj->cleanMsgQueue();
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
