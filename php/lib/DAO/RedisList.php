<?php
namespace DAO;
use DAO;
trait RedisList {
	/**
	 * Лист реализован по принципу FIFO. 
	 * Если данная модель поведения не охватывает всего того,
	 * что вам надо - допишите!
	 */
	
	/**
	 * Произвести выборку элементов с удалением
	 * @param integer $num - количество извлекаемых объектов
	 * @return array of string
	 */
	public function popList($num = null) {
		$res = [];
		while($num--) {
			$res[] = $this->redis()->lPop($this->getName());
		}
		return $res;
	}

	/**
	 * Обновление хеша
	 * @param mixed $str массив строк/строка
	 * @return int количество вставленных строк
	 */
	public function pushList($str) {
		$res = 0;
		switch (gettype($str)) {
		case 'array':
			foreach ($str as $s) {
				$this->redis()->rPush($this->getName(), $s) ? ++$res : 0;
			}
			break;
		case 'string':
			$this->redis()->rPush($this->getName(), $str) ? ++$res : 0;
			break;
		default:
			break;
		}
		$this->redis()->setTimeout($this->getName(), $this->getTimeout());
		return $res;
	}

	/**
	 * Подсчет количества элементов в списке
	 * @return integer
	 */
	public function countList() {
		return $this->redis()->lSize($this->getName());
	}
}
?>
