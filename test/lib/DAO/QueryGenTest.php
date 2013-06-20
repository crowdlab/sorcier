<?php
require_once __DIR__ . '/../../../php/lib/autoload.php.inc';

/*
 * Query generator test
 * @outputBuffering disabled
 * @backupGlobals disabled
 * @backupStaticAttributes disabled
 */
class QueryGenTest extends Testing\CoreTestBase {
	protected $backupGlobals = false;
	public function testMakeSetKv() {
		$fixture = ['a' => 'b'];
		$this->assertEquals(["`a`='b'"], DAO\QueryGen::make_set_kv($fixture));
	}

	public function testMakeFields() {
		$fixture = ['a', 'b'];
		$this->assertEquals("`a`,`b`", DAO\QueryGen::make_fields($fixture));
		$fixture = [];
		$this->assertEquals('1', DAO\QueryGen::make_fields($fixture));
	}
	
	public function testMakeInsert() {
		$fixture = ['a','b'];
		$this->assertEquals(("('a','b')"), DAO\QueryGen::make_insert($fixture));
		$fixture = ['a', new DAO\Sql\Alias('a', 'b')];
		$this->assertEquals(("('a',a AS `b`)"), DAO\QueryGen::make_insert($fixture));
		$fixture = ['a', new DAO\Sql\Alias(new DAO\Sql\Expr('`b`+0'), 'b')];
		$this->assertEquals(("('a',`b`+0 AS `b`)"), DAO\QueryGen::make_insert($fixture));
		$fixture = ['a', new DAO\Sql\Expr('`b`+0')];
		$this->assertEquals(("('a',`b`+0)"), DAO\QueryGen::make_insert($fixture));
		$fixture = [['a','b'],['c','d']];
		$this->assertEquals(("(('a','b'),('c','d'))"), DAO\QueryGen::make_insert($fixture));
	}
	
	public function testMakeCond() {
		$fixture = array();
		$this->assertEquals(("1"), DAO\QueryGen::make_cond($fixture));
		$fixture = ['a' => 'b'];
		$this->assertEquals(("(`a`='b')"), DAO\QueryGen::make_cond($fixture));
		$fixture = ['a' => null];
		$this->assertEquals("(`a` IS NULL)", DAO\QueryGen::make_cond($fixture));
		$fixture = ['<>' => ['a' => null]];
		$this->assertEquals("(`a` IS NOT NULL)", DAO\QueryGen::make_cond($fixture));
		$fixture = ['!=' => ['a' =>'b']];
		$this->assertEquals(("(`a`<>'b')"), DAO\QueryGen::make_cond($fixture));
		$fixture = ['>' => ['a' => 5]];
		$this->assertEquals(("((`a`>5))"), DAO\QueryGen::make_cond($fixture));

		// or
		$fixture = ['$or' => ['a' => 5, 'b' => 10]];
		$this->assertEquals(("(`a`=5 or `b`=10)"), DAO\QueryGen::make_cond($fixture));

		/* normal order */
		$fixture = ['a' => ['>' => 5]];
		$this->assertEquals(("(((`a`>5)))"), DAO\QueryGen::make_cond($fixture));
		// TODO: get rid of multiple ((

		$f = ['company_id' => 1, '>' => ['last_update' => 5]];
		$this->assertEquals(("(`company_id`=1 AND (`last_update`>5))"), DAO\QueryGen::make_cond($f));
		
		$fixture = ['$in' => ['a' => [1, 2]]];
		$this->assertEquals(("(`a` IN (1,2))"), DAO\QueryGen::make_cond($fixture));
	}

	public function testSqlExpr() {
		$sql = 'UNIX_TIMESTAMP(`date`)';
		$num = 12345;
		$exp = new DAO\Sql\Expr($sql);
		$key = $exp->make_key();
		$fixture = [$key => $num];
		$this->assertEquals("($sql=$num)", DAO\QueryGen::make_cond($fixture));
	}

	public function testFunc() {
		$fixture = ['a' => new DAO\Func('PASSWORD', 'b')];
		$this->assertEquals(("(`a`=PASSWORD('b'))"), DAO\QueryGen::make_cond($fixture));
		$this->assertEquals(["`a`=PASSWORD('b')"], DAO\QueryGen::make_set_kv($fixture));
		$fixture = [new DAO\Func('PASSWORD', 'b')];
		$this->assertEquals(("(PASSWORD('b'))"), DAO\QueryGen::make_insert($fixture));

		$this->assertEquals('NOW()', (string)(new DAO\FuncNow()));
	}
}
?>
