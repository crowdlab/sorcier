<?php

namespace DAO;

use DAO;

/**
 * DAO for entity translations.
 *
 * To make your entity translatable, please use Translatable trait
 * instead of using this class directly.
 */
class TranslatableDAO extends MongoDAO
{
    public function getName()
    {
        return 'translatable';
    }

    const EID = 'EID';
    const EntKey = '_entity';
    const LangKey = '_lang';
    const RowId = 'id';

    /**
     * Translate row to specified language using Google Translate API.
     *
     * @param $row row
     * @param $fields translatable fields
     * @param $lang language
     */
    public static function serviceTranslate($row, $fields, $lang = 'en')
    {
        $tr = \Google\TranslateApi::getInstance();
        foreach ($row as $k => &$v) {
            if (!in_array($k, $fields, true)) {
                continue;
            }
            if (!$v) {
                continue;
            } // empty value
            $res = $tr->translate($v, $lang);
            if ($res && isset($res['translatedText'])) {
                $v = $res['translatedText'];
            } elseif (isset($res['error']) || !$res) {
                // TODO (vissi): try bing
            }
        }

        return $row;
    }

    /**
     * Clear translations.
     *
     * @param $id entity id
     * @param $entity entity
     */
    public function clearTranslations($id, $entity)
    {
        $cond = [
            static::EntKey => $entity,
            static::EID    => $id,
        ];

        return $this->delete($cond);
    }

    /**
     * Set translation for object.
     *
     * @param $row      translated object (id field required)
     * @param $entity   entity type
     * @param $fields   fields to filter
     * @param $lang     translation language (optional, default 'en')
     */
    public function setTranslation($row, $entity, $fields = [], $lang = 'en')
    {
        if ($fields) {
            foreach ($row as $k => $v) {
                if (in_array($k, $fields, true)) {
                    continue;
                }
                if ($k == static::RowId) {
                    continue;
                }
                unset($row[$k]);
            }
        }
        if (!$row) {
            return true;
        }
        $cond = [static::EID => $row[static::RowId]];
        $row[static::EntKey] = $cond[static::EntKey] = $entity;
        $row[static::LangKey] = $cond[static::LangKey] = $lang;

        return $this->update($row, $cond, ['upsert' => true, 'multi' => true]);
    }

    /**
     * Get translated object
     * if not exists, gets translated by external services and cached.
     *
     * @param $row      translated object (id field required)
     * @param $entity   entity type
     * @param $fields   fields to filter
     * @param $lang     translation language (optional, default 'en')
     */
    public function get($row, $entity, $fields = null, $lang = 'en')
    {
        $cond = [
            static::EID     => $row[static::RowId],
            static::EntKey  => $entity,
            static::LangKey => $lang,
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
        unset($tr[static::EntKey]);
        unset($tr[static::LangKey]);

        return array_merge($row, $tr);
    }
}
