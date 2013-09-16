<?php
namespace DAO;
use DAO;
/**
 * This DAO helps MongoOperator
 */
class ManagedMongoDAO extends MongoDAO {
	protected $name;

	public function getName() {
		return $this->name;
	}

	public function setName($name) {
		$this->name = $name;
	}
}

?>
