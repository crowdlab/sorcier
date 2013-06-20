<?php
namespace DAO;
use DAO;
/**
 * Управление изменениями схемы
 */
class PatchesDAO extends MySQLDAO {
	public function getName() {
		return 'patches';
	}
}
?>
