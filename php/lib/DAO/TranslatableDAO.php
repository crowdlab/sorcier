<?php
namespace DAO;
use DAO;
/**
 * DAO for entity translations
 *
 * To make your entity translatable, please use Translatable trait
 * instead of using this class directly.
 */
class TranslatableDAO extends MongoDAO {
	public function getName() {
		return 'translatable';
	}

	/**
	 * Translate row to specified language using Google Translate API
	 * @param $row row
	 * @param $fields translatable fields
	 * @param $lang language
	 */
	public static function serviceTranslate($row, $fields, $lang = 'en') {
		$tr = \Google\TranslateApi::getInstance();
		foreach ($row as $k => &$v) {
			if (!in_array($k, $fields, true)) continue;
			if (!$v) continue; // empty value
			$res = $tr->translate($v, $lang);
			if ($res && isset($res['translatedText']))
				$v = $res['translatedText'];
			else if (isset($res['error']) || !$res) {
				// TODO (vissi): try bing
			}
		}
		return $row;
	}

	/**
	 * Clear translations
	 * @param $id entity id
	 * @param $entity entity
	 */
	public function clearTranslations($id, $entity) {
		$cond = [
			'_entity' => $entity,
			'id'      => $id
		];
		return $this->delete($cond);
	}

	/**
	 * Set translation for object
	 *
	 * @param $row      translated object (id field required)
	 * @param $entity   entity type
	 * @param $fields   fields to filter
	 * @param $lang     translation language (optional, default 'en')
	 */
	public function setTranslation($row, $entity, $fields = null, $lang = 'en') {
		if ($fields) {
			foreach($row as $k => $v) {
				if (in_array($k, $fields, true)) continue;
				if ($k == 'id') continue;
				unset($row[$k]);
			}
		}
		$cond = ['id' => $row['id']];
		$row['_entity'] = $cond['_entity'] = $entity;
		$row['_lang']   = $cond['_lang']   = $lang;
		return $this->update($row, $cond, ['upsert' => true, 'multi' => true]);
	}

	/**
	 * Get translated object
	 * if not exists, gets translated by external services and cached
	 *
	 * @param $row      translated object (id field required)
	 * @param $entity   entity type
	 * @param $fields   fields to filter
	 * @param $lang     translation language (optional, default 'en')
	 */
	public function get($row, $entity, $fields = null, $lang = 'en') {
		$cond = [
			'id'      => $row['id'],
			'_entity' => $entity,
			'_lang'   => $lang,
		];
		$r = $this->select([], $cond);
		$tr = $this->fetch_assoc($r);
		if (!$tr) {
			$tr = static::serviceTranslate($row, $fields, $lang);
			// cache result
			if (is_array($tr)) {
				$this->setTranslation($tr, $entity, $fields, $lang);
				return $tr;
			}
			return $row;
		}
		unset($tr['_entity']);
		unset($tr['_lang']);
		return array_merge($row, $tr);
	}
}
?>
