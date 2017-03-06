<?php

namespace CoreWorker;

require_once __DIR__.'/../../inc/config.php';

/**
 * Gearman service.
 */
final class BaseWorker
{
    protected static $worker_host = null;
    protected static $host = null;
    protected static $port = null;

    protected static function initHost()
    {
        if (static::$host != null) {
            return;
        }
        static::$host = \Config::get('gearman_host', '127.0.0.1');
        static::$port = \Config::get('gearman_port', 4730);
        static::$worker_host = \Config::get('worker_host', \Config::get('host'));
    }

    /**
     * Регистрация рабочего потока.
     */
    public static function registerWorker($function_name, $script_name)
    {
        echo "Registering Gearman worker: $script_name\n";
        static::initHost();

        $gmworker = new \GearmanWorker();
        $gmworker->addServer(static::$host, static::$port);
        // Регистрация функции "sendMailNotification" на сервере
        $gmworker->addFunction($function_name.'_'.static::$worker_host, $function_name);

        // Main loop of Gearman Worker
        try {
            while ($gmworker->work()) {
                echo "$script_name: ";
                if ($gmworker->returnCode() != GEARMAN_SUCCESS) {
                    //failed, do something here
                    echo "FAIL\n";
                }
                echo "OK\n";
            }
        } catch (\Exception $ex) {
            echo "$script_name: ".$ex->getMessage()."\n";
            echo "Possibly server is not running\n";
        }
    }

    /**
     * @param string $fn   function name registred in gearman query
     * @param mixed  $data All data that need $fn
     *
     * @return nothing as it runs task async
     */
    public static function sendToWorker($fn, $data)
    {
        static::initHost();
        $gmclient = new \GearmanClient();
        $gmclient->addServer(static::$host, static::$port);

        // run reverse client in the background
        try {
            $gmclient->doBackground($fn.'_'.static::$worker_host, json_encode($data));
        } catch (\Exception $ex) {
            \logger\Log::instance()->logError('Gearman: ', $ex->getMessage());
        }
    }
}
