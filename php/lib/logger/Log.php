<?php
/**
 * Finally, a light, permissions-checking logging class.
 *
 * Originally written for use with wpSearch
 *
 * Usage:
 * $log = new KLogger('/var/log/', KLogger::INFO);
 * $log->logInfo('Returned a million search results'); //Prints to the log file
 * $log->logFatal('Oh dear.'); //Prints to the log file
 * $log->logDebug('x = 5'); //Prints nothing due to current severity threshhold
 *
 * @author  Kenny Katzgrau <katzgrau@gmail.com>
 *
 * @since   July 26, 2008 — Last update July 1, 2012
 * @link    http://codefury.net
 *
 * @version 0.2.0
 */

namespace logger;

/**
 * Class documentation.
 */
class Log
{
    /**
     * Error severity, from low to high. From BSD syslog RFC, secion 4.1.1.
     *
     * @link http://www.faqs.org/rfcs/rfc3164.html
     */
    const EMERG = 0; // Emergency: system is unusable
    const ALERT = 1; // Alert: action must be taken immediately
    const CRIT = 2; // Critical: critical conditions
    const ERR = 3; // Error: error conditions
    const WARN = 4; // Warning: warning conditions
    const NOTICE = 5; // Notice: normal but significant condition
    const INFO = 6; // Informational: informational messages
    const DEBUG = 7; // Debug: debug messages

    //custom logging level
    /**
     * Log nothing at all.
     */
    const OFF = 8;
    /**
     * Alias for CRIT.
     *
     * @deprecated
     */
    const FATAL = 2;

    /**
     * Internal status codes.
     */
    const STATUS_LOG_OPEN = 1;
    const STATUS_OPEN_FAILED = 2;
    const STATUS_LOG_CLOSED = 3;

    /**
     * We need a default argument value in order to add the ability to easily
     * print out objects etc. But we can't use null, 0, false, etc, because those
     * are often the values the developers will test for. So we'll make one up.
     */
    const NO_ARGUMENTS = 'KLogger::NO_ARGUMENTS';

    /**
     * Current status of the log file.
     *
     * @var int
     */
    private $_logStatus = self::STATUS_LOG_CLOSED;
    /**
     * Holds messages generated by the class.
     *
     * @var array
     */
    private $_messageQueue = [];
    /**
     * Path to the log file.
     *
     * @var string
     */
    private $_logFilePath = null;
    /**
     * Current minimum logging threshold.
     *
     * @var int
     */
    private $_severityThreshold = self::INFO;
    /**
     * This holds the file handle for this instance's log file.
     *
     * @var resource
     */
    private $_fileHandle = null;

    /**
     *  Guid of one stack of calls.
     */
    private $_guid;

    /**
     * Standard messages produced by the class. Can be modified for il8n.
     *
     * @var array
     */
    private $_messages = [
        'writefail'   => 'The file exists, but could not be opened for writing.
		Check that appropriate permissions have been set.',
        'opensuccess' => 'The log file was opened successfully.',
        'openfail'    => 'The file could not be opened. Check permissions.',
    ];

    /**
     * Default severity of log messages, if not specified.
     *
     * @var int
     */
    private static $_defaultSeverity = self::DEBUG;
    /**
     * Valid PHP date() format string for log timestamps.
     *
     * @var string
     */
    private static $_dateFormat = 'Y-m-d G:i:s';
    /**
     * Octal notation for default permissions of the log file.
     *
     * @var int
     */
    private static $_defaultPermissions = 0777;
    /**
     * Array of KLogger instances, part of Singleton pattern.
     *
     * @var array
     */
    private static $instances = [];

    /**
     * Partially implements the Singleton pattern. Each $logDirectory gets one
     * instance.
     *
     * @param string $logDirectory File path to the logging directory
     * @param int    $severity     One of the pre-defined severity constants
     *
     * @return Log
     */
    public static function instance($logDirectory = false, $severity = false)
    {
        if ($severity === false) {
            $severity = self::$_defaultSeverity;
        }

        if ($logDirectory === false) {
            if (count(self::$instances) > 0) {
                return current(self::$instances);
            } else {
                $logDirectory = \Config::get(['logs', 'path'], '/var/log/mhlog/');
            }
        }

        if (in_array($logDirectory, self::$instances)) {
            return self::$instances[$logDirectory];
        }

        $instance = new self($logDirectory, $severity);
        $instance->_guid = uniqid();
        self::$instances[$logDirectory] = $instance;

        return $instance;
    }

    /**
     * Class constructor.
     *
     * @param string $logDirectory File path to the logging directory
     * @param int    $severity     One of the pre-defined severity constants
     *
     * @return void
     */
    public function __construct($logDirectory, $severity)
    {
        $logDirectory = rtrim($logDirectory, '\\/');

        if ($severity === self::OFF) {
            return;
        }

        $this->_logFilePath = $logDirectory
            .DIRECTORY_SEPARATOR
            .'log_'
            .date('Y-m-d')
            .'.log';

        $this->_severityThreshold = $severity;
        if (!file_exists($logDirectory)) {
            mkdir($logDirectory, self::$_defaultPermissions, true);
        }
        if (file_exists($this->_logFilePath) && !is_writable($this->_logFilePath)) {
            $this->_logStatus = self::STATUS_OPEN_FAILED;
            $this->_messageQueue[] = $this->_messages['writefail'];

            return;
        }

        if (($this->_fileHandle = fopen($this->_logFilePath, 'a'))) {
            $this->_logStatus = self::STATUS_LOG_OPEN;
            $this->_messageQueue[] = $this->_messages['opensuccess'];
        } else {
            $this->_logStatus = self::STATUS_OPEN_FAILED;
            $this->_messageQueue[] = $this->_messages['openfail'];
        }
    }

    /**
     * Class destructor.
     */
    public function __destruct()
    {
        if ($this->_fileHandle) {
            fclose($this->_fileHandle);
        }
    }

    /**
     * Writes a $line to the log with a severity level of DEBUG.
     *
     * @param string $line Information to log
     *
     * @return void
     */
    public function logDebug($line, $args = self::NO_ARGUMENTS)
    {
        $this->log($line, self::DEBUG);
    }

    /**
     * Returns (and removes) the last message from the queue.
     *
     * @return string
     */
    public function getMessage()
    {
        return array_pop($this->_messageQueue);
    }

    /**
     * Returns the entire message queue (leaving it intact).
     *
     * @return array
     */
    public function getMessages()
    {
        return $this->_messageQueue;
    }

    /**
     * Empties the message queue.
     *
     * @return void
     */
    public function clearMessages()
    {
        $this->_messageQueue = [];
    }

    /**
     * Sets the date format used by all instances of KLogger.
     *
     * @param string $dateFormat Valid format string for date()
     */
    public static function setDateFormat($dateFormat)
    {
        self::$_dateFormat = $dateFormat;
    }

    /**
     * Writes a $line to the log with a severity level of INFO. Any information
     * can be used here, or it could be used with E_STRICT errors.
     *
     * @param string $line Information to log
     *
     * @return void
     */
    public function logInfo($line, $args = self::NO_ARGUMENTS)
    {
        $this->log($line, self::INFO, $args);
    }

    /**
     * Writes a $line to the log with a severity level of NOTICE. Generally
     * corresponds to E_STRICT, E_NOTICE, or E_USER_NOTICE errors.
     *
     * @param string $line Information to log
     *
     * @return void
     */
    public function logNotice($line, $args = self::NO_ARGUMENTS)
    {
        $this->log($line, self::NOTICE, $args);
    }

    /**
     * Writes a $line to the log with a severity level of WARN. Generally
     * corresponds to E_WARNING, E_USER_WARNING, E_CORE_WARNING, or
     * E_COMPILE_WARNING.
     *
     * @param string $line Information to log
     *
     * @return void
     */
    public function logWarn($line, $args = self::NO_ARGUMENTS)
    {
        $this->log($line, self::WARN, $args);
    }

    /**
     * Writes a $line to the log with a severity level of ERR. Most likely used
     * with E_RECOVERABLE_ERROR.
     *
     * @param string $line Information to log
     *
     * @return void
     */
    public function logError($line, $args = self::NO_ARGUMENTS)
    {
        $this->log($line, self::ERR, $args);
    }

    /**
     * Writes a $line to the log with a severity level of FATAL. Generally
     * corresponds to E_ERROR, E_USER_ERROR, E_CORE_ERROR, or E_COMPILE_ERROR.
     *
     * @param string $line Information to log
     *
     * @return void
     *
     * @deprecated Use logCrit
     */
    public function logFatal($line, $args = self::NO_ARGUMENTS)
    {
        $this->log($line, self::FATAL, $args);
    }

    /**
     * Writes a $line to the log with a severity level of ALERT.
     *
     * @param string $line Information to log
     *
     * @return void
     */
    public function logAlert($line, $args = self::NO_ARGUMENTS)
    {
        $this->log($line, self::ALERT, $args);
    }

    /**
     * Writes a $line to the log with a severity level of CRIT.
     *
     * @param string $line Information to log
     *
     * @return void
     */
    public function logCrit($line, $args = self::NO_ARGUMENTS)
    {
        $this->log($line, self::CRIT, $args);
    }

    /**
     * Writes a $line to the log with a severity level of EMERG.
     *
     * @param string $line Information to log
     *
     * @return void
     */
    public function logEmerg($line, $args = self::NO_ARGUMENTS)
    {
        $this->log($line, self::EMERG, $args);
    }

    /**
     * Writes a $line to the log with the given severity.
     *
     * @param string $line     Text to add to the log
     * @param int    $severity Severity level of log message (use constants)
     */
    public function log($msg, $severity, $args = self::NO_ARGUMENTS, $get_user = false)
    {
        if ($this->_severityThreshold >= $severity) {
            $severity = $this->_getSeverity($severity);
            $sessionID = session_id();
            if ($get_user) {
                $userID = \UserSingleton::canInstance()
                    ? \UserSingleton::getInstance()->getId()
                    : 0;
            } else {
                $userID = 0;
            }
            $script = 'event='.((isset($_SERVER['SCRIPT_NAME'])
                && !strpos($_SERVER['SCRIPT_NAME'], 'phpunit'))
                ? $_SERVER['SCRIPT_NAME'] : 'TEST');

            $res = [
                '@timestamp' => date('Y-m-d\TH:i:s'),
                '@message'   => $msg,
                '@fields'    => [
                    'severity' => $severity,
                    'group'    => "$this->_guid",
                    'script'   => $script,
                    'user'     => "$userID",
                    'session'  => "$sessionID",
                    'args'     => $args,
                ],
            ];

            $this->writeFreeFormLine(json_encode($res).PHP_EOL);
        }
    }

    /**
     * Writes a line to the log without prepending a status or timestamp.
     *
     * @param string $line Line to write to the log
     *
     * @return void
     */
    public function writeFreeFormLine($line)
    {
        if ($this->_logStatus == self::STATUS_LOG_OPEN
            && $this->_severityThreshold != self::OFF) {
            if (fwrite($this->_fileHandle, $line) === false) {
                $this->_messageQueue[] = $this->_messages['writefail'];
            }
        }
    }

    private function _getSeverity($level)
    {
        switch ($level) {
            case self::EMERG:
                return 'EMERG';
            case self::ALERT:
                return 'ALERT';
            case self::CRIT:
                return 'CRIT';
            case self::NOTICE:
                return 'NOTICE';
            case self::INFO:
                return 'INFO';
            case self::WARN:
                return 'WARN';
            case self::DEBUG:
                return 'DEBUG';
            case self::ERR:
                return 'ERROR';
            default:
                return 'LOG';
        }
    }
}
