<?php
/**
 * Тестирование функций MongoDAO
 * @class MongoDAOTest
 */
require_once __DIR__ . '/../../../php/lib/autoload.php.inc';
use \DAO\MongoDAO;
/*
 * @backupStaticAttributes disabled
 * @backupGlobals disabled
 */
class MongoDAOTest extends Testing\CoreTestBase {
	public function test_remapDates1() {
		$data = new \MongoDate(1325379661);
		$result = MongoDAO::remapDates($data);
		$expected = 1325379661;
		$this::assertEquals($expected, $result);
	}

	/**
	 *  Тестирование функции remapDates
	 */
	public function test_remapDates2() {
		$data = [
			'name' => "name",
			'date' => new \MongoDate(13253796612)
		];
		$result = MongoDAO::remapDates($data);
		$expected = [
			'name' => "name",
			'date' => 13253796612
		];
		$this::assertEquals($expected, $result);
	}

	public function test_remapDates3() {
		$data = [
			'name' => "name",
			'date' => new \MongoDate(13253796612),
			'wrap' => [
				'name2' => 'name2',
				'date2' => new \MongoDate(23253796612)
			]
		];
		$result = MongoDAO::remapDates($data);
		$expected = [
			'name' => "name",
			'date' => 13253796612,
			'wrap' => [
				'name2' => 'name2',
				'date2' => 23253796612
			]
		];
		$this::assertEquals($expected, $result);
	}

	public function test_remapDates4() {
		$data = [
			'name' => "name",
			'date' => new \MongoDate(23253796612),
			'wrap' => [
				[
					'name2' => 'name2',
					'date2' => new \MongoDate(2325379661)
				], [
					'name3' => 'name3',
					'date3' => new \MongoDate(232537966)
				]
			]
		];
		$result = MongoDAO::remapDates($data);
		$expected = [
			'name' => "name",
			'date' => 23253796612,
			'wrap' => [
				[
					'name2' => 'name2',
					'date2' => 2325379661
				], [
					'name3' => 'name3',
					'date3' => 232537966
				]
			]
		];
		$this::assertEquals($expected, $result);
	}
}
