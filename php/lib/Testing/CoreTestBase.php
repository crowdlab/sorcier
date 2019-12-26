<?php

namespace Testing;

use Connector;
use Mockery as m;
use PHPUnit\Framework\TestCase;

include_once __DIR__.'/../../inc/config.php';

/**
 * Core tests.
 */
abstract class CoreTestBase extends TestCase
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

    /**
     * Предодготовка для тестов.
     */
    public static function setUpBeforeClass(): void
    {
        static::customSetUpBeforeClass();
    }

    protected function setUp(): void
    {
        static::customSetUp();
    }

    protected function tearDown(): void
    {
        m::close();
        static::customTearDown();
    }

    public static function tearDownAfterClass(): void
    {
        static::customTearDownAfterClass();
    }
}
