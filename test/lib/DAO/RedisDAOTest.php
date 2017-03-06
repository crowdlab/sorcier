<?php

require_once __DIR__.'/../../../php/lib/autoload.php.inc';

/*
 * @outputBuffering disabled
 * @backupGlobals disabled
 * @backupStaticAttributes disabled
 */
class TesterRedisDAO extends DAO\RedisDAO
{
    public function getName()
    {
        return 'testRedis';
    }

    public function getTimeout()
    {
        return 10;
    }
}

class RedisDAOTest extends Testing\CoreTestBase
{
    protected $backupGlobals = false;

    public function testHashOperations()
    {
        /*Тестовые данные*/
        $keys = ['name', 'surname'];
        $myname = 'myname';
        $mysurname = 'mysurname';
        $values = [$myname, $mysurname];
        $kv = array_combine($keys, $values);

        /*Проверка получения синглтона*/
        $res = $rdao = TesterRedisDAO::getInstance();
        $this->assertNotNull($res);

        /* Очистка ключа, все тесты должны подтирать
         * за собой, должен быть ноль
         */
        $res = $rdao->deleteAll();
        $this->assertEquals(0, $res);

        /*Проверка вставки*/
        $res = $rdao->insertHash($kv);
        $this->assertTrue($res);

        /*Проверка количества ключей*/
        $res = $rdao->countHash();
        $this->assertEquals(2, $res);

        /*Проверка выборки с массивом*/
        $res = $rdao->selectHash(['name']);
        $this->assertEquals(['name' => $myname], $res);

        /*Проверка выборки со строкой*/
        $res = $rdao->selectHash('name');
        $this->assertEquals($myname, $res);

        /*Проверка выборки без параvетра*/
        $res = $rdao->selectHash();
        $this->assertEquals($kv, $res);

        /*Проверка удаления поля*/
        $res = $rdao->deleteHash('name');

        /*Проверка удаления*/
        $res = $rdao->deleteAll();
        $this->assertEquals(1, $res);
    }

    public function testListOperations()
    {
        /*Тестовые данные*/
        $list = ['name', 'surname', 'call', 'me', 'maybe'];

        /*Проверка получения синглтона*/
        $res = $rdao = TesterRedisDAO::getInstance();
        $this->assertNotNull($res);

        /* Очистка ключа, все тесты должны подтирать
         * за собой, должен быть ноль
         */
        $res = $rdao->deleteAll();
        $this->assertEquals(0, $res);

        /* Вставка списка*/
        $res = $rdao->pushList($list);
        $this->assertEquals(count($list), $res);

        /*Подсчет элементов*/
        $res = $rdao->countList();
        $this->assertEquals(count($list), $res);

        /* Чтение списка*/
        $res = $rdao->popList(count($list));
        $this->assertEquals($list, $res);

        $res = $rdao->deleteAll();
        $this->assertEquals(0, $res);
    }

    public function testStringOperations()
    {
        /*Тестовые данные*/
        $string = 'call me maybe';

        /*Проверка получения синглтона*/
        $res = $rdao = TesterRedisDAO::getInstance();
        $this->assertNotNull($res);

        /* Очистка ключа, все тесты должны подтирать
         * за собой, должен быть ноль
         */
        $res = $rdao->deleteAll();
        $this->assertEquals(0, $res);

        /* Вставка строки*/
        $res = $rdao->insertString($string);
        $this->assertTrue($res);

        /*Чтение строки*/
        $res = $rdao->selectString();
        $this->assertEquals($string, $res);

        $res = $rdao->deleteAll();
        $this->assertEquals(1, $res);
    }
}
