<?php
namespace DAO;
use DAO;
/**
 * Schema version
 */
class VersionDAO extends MySQLDAO {
	public function getName() {
		return 'version';
	}

	/**
	 * Check if db has version table
	 */
	public function hasVersion() {
		$r = $this->perform_query("SHOW TABLES LIKE \"".$this->getName()."\"");
		return $r && ($this->num_rows($r)) > 0;
	}

	/**
	 * Получить текущую версию (timestamp)
	 */
	public function getVersion() {
		$r = $this->select([new Sql\Expr('MAX(UNIX_TIMESTAMP(`version`)) AS ver')], []);
		$v = $this->fetch_assoc($r);
		return $v['ver'];
	}
}
?>
