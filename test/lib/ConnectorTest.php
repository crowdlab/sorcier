<?php

require_once __DIR__.'/../../php/lib/autoload.php.inc';

/**
 * Тест операций.
 *
 * @outputBuffering disabled
 * @backupGlobals disabled
 * @backupStaticAttributes disabled
 */
class ConnectorTest extends Testing\CoreTestBase
{
    public static function customSetUpBeforeClass()
    {
    }

    public function testGetMongo()
    {
        $mongo = Connector::getInstance()->getMongo();
        $this->assertEquals('MongoClient', get_class($mongo));
    }

    public function testGetMySQL()
    {
        $mysql = Connector::getInstance()->getMySQL();
        $this->assertEquals('mysqli', get_class($mysql));
    }

    public function testGetRedis()
    {
        $redis = Connector::getInstance()->getRedis();
        $this->assertEquals('Redis', get_class($redis));
    }
}
