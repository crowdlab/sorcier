<?php
namespace DAO;
use DAO;
/**
 * Translatable items
 *
 * this assumes you extend some DAO and set static::$translationFields
 */
trait Translatable {
	public static $allowedLanguages = ['ru', 'en', 'de'];

	/**
	 * Return translated item
	 *
	 * @param $row  row
	 * @param $lang language
	 * @param $fields fields to translate (static::$translationFields by default)
	 */
	public function translate($row, $lang = 'en', $fields = null) {
		if (!isset($fields))
			$fields = static::$translationFields;
		$dao = \DAO\TranslatableDAO::getInstance();
		$entity = $this->getName();
		if (!in_array($lang, static::$allowedLanguages, true)) return $row;
		return $dao->get($row, $entity, $fields, $lang);
	}

	public function clearTranslations($id) {
		$dao = \DAO\TranslatableDAO::getInstance();
		$entity = $this->getName();
		return $dao->clearTranslations($id, $entity);
	}

	/**
	 * Translate many items
	 * @param $rows items
	 * @param $lang language
	 * @param $fields fields to translate (static::$translationFields by default)
	 */
	public function translateAll($rows, $lang = 'en', $fields = null) {
		foreach($rows as $k => &$v)
			$v = $this->translate($v, $lang, $fields);
		return $rows;
	}
}
?>
