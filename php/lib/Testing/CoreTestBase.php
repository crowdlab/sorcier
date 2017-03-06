<?php

namespace Testing;

use Mockery as m;

include_once __DIR__.'/../../inc/config.php';

/**
 * Core test.
 */
abstract class CoreTestBase extends \PHPUnit_Framework_TestCase
{
    protected static function customSetUpBeforeClass()
    {
    }

    protected static function customSetUp()
    {
    }

    protected static function customTearDown()
    {
    }

    protected static function customTearDownAfterClass()
    {
    }

    protected static function setTestConnectors()
    {
        global $config;
        // просто ресет получается из PHPUnit'а
        // хотя и без этого выставятся правильные коннекторы
        \Connector::getInstance()->setAll($config);
    }

    /**
     * Предодготовка для тестов.
     */
    public static function setUpBeforeClass()
    {
        self::setTestConnectors();
        static::customSetUpBeforeClass();
    }

    protected function setUp()
    {
        static::customSetUp();
    }

    protected function tearDown()
    {
        m::close();
        static::customTearDown();
    }

    public static function tearDownAfterClass()
    {
        static::customTearDownAfterClass();
    }
}
