<?php
/**
 * General functions
 */
final class Common {
	private static $tidy = null;
	private static $allowed_tags = [
		'blockquote', 'small', 'a', 'span',
		'br', 'b', 'i', 'p', 'img',
		'ol', 'ul', 'li', 'strong',
		'abbr', 'dt', 'dl', 'dfn', 'em'
	];

	/**
	 * Clear event attributes (possibly malicious code)
	 *
	 * @param $s
	 * @param $allowed_tags
	 * @return string
	 */
	public static function strip_tags_clean($s, $allowed_tags) {
		if (self::$tidy == null) self::$tidy = new tidy();
		$s = self::$tidy->repairString($s, [], 'utf8');
		// taken from CodeIgniter
		$s = preg_replace("#<([^><]+?)([^a-z_\-]on\w*|xmlns)(\s*=\s*[^><]*)([><]*)#i",
			"<\\1\\4", $s);
		return self::strip_tags_smart($s, $allowed_tags);
	}

	/**
	 * Строка имеет префикс
	 *
	 * @param $haystack строка
	 * @param $needle   префикс
	 * @return bool
	 */
	public static function startsWith($haystack, $needle) {
		$length = mb_strlen($needle);
		return (mb_substr($haystack, 0, $length) === $needle);
	}

	/**
	 * разница между массивами с учетом вложенности
	 */
	public static function array_diff_assoc_recursive($array1, $array2) {
		$diff = array();
		foreach ($array1 as $k => $v) {
			if (!isset($array2[$k]))
				$diff[$k] = $v;
			else if ($array2[$k] != $v) {
				if (is_array($v) && is_array($array2[$k])) {
					$x = self::array_diff_assoc_recursive($v, $array2[$k]);
					if (count($x) > 0)
						$diff[$k] = $x;
				} else {
					$diff[$k] = $v;
				}
			}
		}
		return $diff;
	}

	/**
	 * Строка имеет суффикс
	 *
	 * @param $haystack строка
	 * @param $needle   суффикс
	 * @return bool
	 */
	public static function endsWith($haystack, $needle) {
		$length = strlen($needle);
		if ($length == 0)
			return true;
		return (substr($haystack, -$length) === $needle);
	}

	/**
	 * Более продвинутый аналог strip_tags() для корректного вырезания тагов из html кода.
	 * Функция strip_tags(), в зависимости от контекста, может работать не корректно.
	 * Возможности:
	 *   - корректно обрабатываются вхождения типа "a < b > c"
	 *   - корректно обрабатывается "грязный" html, когда в значениях атрибутов тагов могут встречаться символы < >
	 *   - корректно обрабатывается разбитый html
	 *   - вырезаются комментарии, скрипты, стили, PHP, Perl, ASP код, MS Word таги, CDATA
	 *   - автоматически форматируется текст, если он содержит html код
	 *   - защита от подделок типа: "<<fake>script>alert('hi')</</fake>script>"
	 *
	 * @param   string  $s
	 * @param   array   $allowable_tags     Массив тагов, которые не будут вырезаны
	 *                                      Пример: 'b' -- таг останется с атрибутами, '<b>' -- таг останется без атрибутов
	 * @param   bool    $is_format_spaces   Форматировать пробелы и переносы строк?
	 *                                      Вид текста на выходе (plain) максимально приближеется виду текста в браузере на входе.
	 *                                      Другими словами, грамотно преобразует text/html в text/plain.
	 *                                      Текст форматируется только в том случае, если были вырезаны какие-либо таги.
	 * @param   array   $pair_tags          массив имён парных тагов, которые будут удалены вместе с содержимым
	 *                               см. значения по умолчанию
	 * @param   array   $para_tags          массив имён парных тагов, которые будут восприниматься как параграфы (если $is_format_spaces = true)
	 *                               см. значения по умолчанию
	 * @return  string
	 *
	 * @license  http://creativecommons.org/licenses/by-sa/3.0/
	 * @author   Nasibullin Rinat, http://orangetie.ru/
	 * @charset  ANSI
	 * @version  4.0.14
	 */
	public static function strip_tags_smart(
		/*string*/
		$s,
		array $allowable_tags = ['blockquote', 'small', 'a', 'span',
			'img'],
		/*boolean*/
		$is_format_spaces = true,
		array $pair_tags = ['script', 'style', 'map', 'iframe', 'frameset',
			'object', 'applet', 'comment', 'button', 'textarea', 'select'],
		array $para_tags = ['p', 'td', 'th', 'li', 'h1', 'h2', 'h3', 'h4',
			'h5', 'h6', 'div', 'form', 'title', 'pre']
	) {
		static $_callback_type = false;
		static $_allowable_tags = array('blockquote');
		static $_para_tags = array();
		#regular expression for tag attributes
		#correct processes dirty and broken HTML in a singlebyte or multibyte UTF-8 charset!
		static $re_attrs_fast_safe =
		'(?![a-zA-Z\d])  #statement, which follows after a tag
	   #correct attributes
	   (?>
		   [^>"\']+
		 | (?<=[\=\x20\r\n\t]|\xc2\xa0) "[^"]*"
		 | (?<=[\=\x20\r\n\t]|\xc2\xa0) \'[^\']*\'
	   )*
	   #incorrect attributes
	   [^>]*+';

		if (is_array($s)) {
			if ($_callback_type === 'strip_tags') {
				$tag = strtolower($s[1]);
				if ($_allowable_tags) {
					#tag with attributes
					if (array_key_exists($tag, $_allowable_tags)) return $s[0];

					#tag without attributes
					if (array_key_exists('<' . $tag . '>', $_allowable_tags)) {
						if (substr($s[0], 0, 2) === '</'
						) return '</' . $tag . '>';
						if (substr($s[0], -2) === '/>'
						) return '<' . $tag . ' />';
						return '<' . $tag . '>';
					}
				}
				if ($tag === 'br') return "\r\n";
				if ($_para_tags && array_key_exists($tag, $_para_tags)
				) return "\r\n\r\n";
				return '';
			}
			trigger_error('Unknown callback type "' . $_callback_type . '"!',
				E_USER_ERROR);
		}

		if (($pos = strpos($s, '<')) === false || strpos($s, '>',
			$pos) === false
		) { #speed improve
			#tags are not found
			return $s;
		}

		$length = strlen($s);

		#unpaired tags (opening, closing, !DOCTYPE, MS Word namespace)
		$re_tags = '~  <[/!]?+
					   (
						   [a-zA-Z][a-zA-Z\d]*+
						   (?>:[a-zA-Z][a-zA-Z\d]*+)?
					   ) #1
					   ' . $re_attrs_fast_safe . '
					   >
					~sxSX';

		$patterns = array(
			'/<([\?\%]) .*? \\1>/sxSX', #встроенный PHP, Perl, ASP код
			'/<\!\[CDATA\[ .*? \]\]>/sxSX', #блоки CDATA

			'/<\!--.*?-->/sSX', #комментарии

			#MS Word таги типа "<![if! vml]>...<![endif]>",
			#условное выполнение кода для IE типа "<!--[if expression]> HTML <![endif]-->"
			#условное выполнение кода для IE типа "<![if expression]> HTML <![endif]>"
			#см. http://www.tigir.com/comments.htm
			'/ <\! (?:--)?+
				   \[
				   (?> [^\]"\']+ | "[^"]*" | \'[^\']*\' )*
				   \]
				   (?:--)?+
			   >
			 /sxSX',
		);
		if ($pair_tags) {
			#парные таги вместе с содержимым:
			foreach ($pair_tags as $k => $v) {
				$pair_tags[$k] = preg_quote($v,
					'/');
			}
			$patterns[] = '/ <((?i:' . implode('|',
				$pair_tags) . '))' . $re_attrs_fast_safe . '(?<!\/)>
							 .*?
							 <\/(?i:\\1)' . $re_attrs_fast_safe . '>
						   /sxSX';
		}
		#d($patterns);

		$i = 0; #защита от зацикливания
		$max = 99;
		while ($i < $max) {
			$s2 = preg_replace($patterns, '', $s);
			if (preg_last_error() !== PREG_NO_ERROR) {
				$i = 999;
				break;
			}

			if ($i == 0) {
				$is_html = ($s2 != $s || preg_match($re_tags, $s2));
				if (preg_last_error() !== PREG_NO_ERROR) {
					$i = 999;
					break;
				}
				if ($is_html) {
					if ($is_format_spaces && false) {
						/*
						В библиотеке PCRE для PHP \s - это любой пробельный символ, а именно класс символов [\x09\x0a\x0c\x0d\x20\xa0] или, по другому, [\t\n\f\r \xa0]
						Если \s используется с модификатором /u, то \s трактуется как [\x09\x0a\x0c\x0d\x20]
						Браузер не делает различия между пробельными символами, друг за другом подряд идущие символы воспринимаются как один
						*/
						#$s2 = str_replace(array("\r", "\n", "\t"), ' ', $s2);
						#$s2 = strtr($s2, "\x09\x0a\x0c\x0d", '	');
						$s2 = preg_replace('/  [\x09\x0a\x0c\x0d]++
											 | <((?i:pre|textarea))' . $re_attrs_fast_safe . '(?<!\/)>
											   .+?
											   <\/(?i:\\1)' . $re_attrs_fast_safe . '>
											   \K
											/sxSX', ' ', $s2);
						if (preg_last_error() !== PREG_NO_ERROR) {
							$i = 999;
							break;
						}
					}

					#массив тагов, которые не будут вырезаны
					if ($allowable_tags) $_allowable_tags = array_flip($allowable_tags);

					#парные таги, которые будут восприниматься как параграфы
					if ($para_tags) $_para_tags = array_flip($para_tags);
				}
			}
			#if

			#tags processing
			if ($is_html) {
				$_callback_type = 'strip_tags';
				$s2 = preg_replace_callback($re_tags, __METHOD__, $s2);
				$_callback_type = false;
				if (preg_last_error() !== PREG_NO_ERROR) {
					$i = 999;
					break;
				}
			}

			if ($s === $s2) break;
			$s = $s2;
			$i++;
		}
		#while
		if ($i >= $max) $s = strip_tags($s); #too many cycles for replace...

		if ($is_format_spaces && strlen($s) !== $length) {
			#remove a duplicate spaces
			$s = preg_replace('/\x20\x20++/sSX', ' ', trim($s));
			#remove a spaces before and after new lines
			$s = str_replace(array("\r\n\x20", "\x20\r\n"), "\r\n", $s);
			#replace 3 and more new lines to 2 new lines
			$s = preg_replace('/[\r\n]{3,}+/sSX', "\r\n\r\n", $s);
		}
		return $s;
	}

	/**
	 * Сделать ссылки из адресов
	 */
	public static function fix_urls($text) {
		return preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\-\.]*(\?\S+)?)?)?(\#[\w-\d_\/]+)?)@',
			'<a href="$1">$1</a>', $text);
	}

	/**
	 * Подготовить сообщение к отправке
	 * @param $text         текст
	 * @param $allowed_tags допустимые теги
	 * @param $replace_br   заменять переводы строк
	 * @return mixed
	 */
	public static function prepare_message($text, $allowed_tags = null, $replace_br = false) {
		if (!isset($allowed_tags))
			$allowed_tags = self::$allowed_tags;
		if ($replace_br)
			$text = str_replace("\n", "<br />",
				str_replace("\r\n", "<br />", $text));
		$text = \Common::strip_tags_clean($text, $allowed_tags);
		return $text;
	}

	const IP_REGEX = "/^([1-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(\.([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}$/";

	/**
	 * Get client IP address
	 */
	public static function getUserIp() {
		if (getenv('REMOTE_ADDR'))               $user_ip = getenv('REMOTE_ADDR');
		elseif (getenv('HTTP_FORWARDED_FOR'))    $user_ip = getenv('HTTP_FORWARDED_FOR');
		elseif (getenv('HTTP_X_FORWARDED_FOR'))  $user_ip = getenv('HTTP_X_FORWARDED_FOR');
		elseif (getenv('HTTP_X_COMING_FROM'))    $user_ip = getenv('HTTP_X_COMING_FROM');
		elseif (getenv('HTTP_VIA'))              $user_ip = getenv('HTTP_VIA');
		elseif (getenv('HTTP_XROXY_CONNECTION')) $user_ip = getenv('HTTP_XROXY_CONNECTION');
		elseif (getenv('HTTP_CLIENT_IP'))        $user_ip = getenv('HTTP_CLIENT_IP');
		$user_ip = isset($user_ip) ? trim($user_ip) : '';
		if (empty($user_ip)) return false;
		if (!preg_match(static::IP_REGEX, $user_ip)) return false;
		return $user_ip;
	}

	/**
	 * Функция проверяет наличие массива входных параметров, id пользователя,
	 * id компании пользователя. Если они отсутствуют, то задаёт их.
	 *
	 * @param  $rqst - массив входных параметров
	 * @param  $uid  - id пользователя
	 */
	public static function checkRqst(&$rqst, &$uid) {
		if ($rqst == null) $rqst = $_REQUEST;
		$uid = \UidHelper::get() != null
			? \UidHelper::get()
			: \UserSingleton::getInstance()->getId();
	}

	const PossibleChars = "2346789bcdfghjkmnpqrtvwxyzBCDFGHJKLMNPQRTVWXYZ";
	/**
	 * Длина ключа для восстановления пароля
	 */
	const KeyLength = 20;

	/**
	 * Сгенерировать простую схему (все поля одного типа)
	 */
	public static function genSchema($fields, $type) {
		return array_combine($fields, array_fill(0, count($fields), $type));
	}

	/**
	 * Сгенерить строку из произвольных символов заданной длины
	 * @param $length  длина
	 * @param $possible произвольные символы
	 */
	public static function generateRandomString($length = 8,
			$possible = self::PossibleChars) {
		$password = "";
		$maxlength = strlen($possible);
		if ($length > $maxlength) {
			$length = $maxlength;
		}
		$i = 0;
		while ($i < $length) {
			$char = substr($possible, mt_rand(0, $maxlength - 1), 1);
			if (!strstr($password, $char)) {
				$password .= $char;
				$i++;
			}
		}
		return $password;
	}

	const EmailRegex = "/^[A-Za-z0-9_\-\.]+([.][A-Za-z0-9\-_]+)*[@][A-Za-z0-9_\-]+([.][A-Za-z0-9_\-]+)*[.][A-Za-z]{2,6}$/";
	/**
	 * Validate email
	 *
	 * @param  string $email
	 * @return bool
	 */
	public static function valid_email($email) {
		return (preg_match(self::EmailRegex, $email));
	}

	/**
	 * Shorten string (add ... if needed)
	 */
	public static function Shorten($s, $limit = 40) {
		$len = mb_strlen($s);
		return $len < $limit
			? $s
			: mb_substr($s, 0, $limit - 3, 'utf-8') . '...';
	}

	/**
	 * Validate phone
	 */
	public static function valid_phone($phone) {
		return preg_match('/\d+/', $phone) && strlen($phone) == 11
			&& $phone[0] == '7' && $phone[1] == '9';
	}

	/**
	 * Validate password
	 *
	 * @param  string  $password
	 * @param  integer $min_pass_length (6 by default)
	 * @return bool
	 */
	public static function valid_password($password, $min_pass_length = 3) {
		return (strlen($password) >= $min_pass_length);
	}

	const Error401Header = "HTTP/1.0 401 Unauthorized";
	const Error403Header = "HTTP/1.0 403 Forbidden";
	const Error404Header = "HTTP/1.0 404 Not Found";
	const Error405Header = "HTTP/1.0 405 Method Not Allowed";
	const Error429Header = "HTTP/1.1 429 Too Many Requests";
	const Error500Header = "HTTP/1.0 500 Internal Server Error";

	/** @readonly */
	public static $codeToMsg = [
		401 => self::Error401Header,
		403 => self::Error403Header,
		404 => self::Error404Header,
		405 => self::Error405Header,
		429 => self::Error429Header,
		500 => self::Error500Header,
	];

	public static function is_valid_mongoId($id) {
		$regex = '/^[0-9a-z]{24}$/';
		if (!class_exists('MongoId'))
			return preg_match($regex, $id);
		$tmp = new \MongoId($id);
		return ($tmp->{'$id'} == $id);
	}

	/**
	 * End script execution and return error (500 by default)
	 *
	 * @param string $msg  error message or array
	 * @param array  $args args to log (null by default)
	 */
	public static function die500($msg, $args = null) {
		$err = self::Error500Header;
		if (is_array($msg) && (isset($msg['code']))
				&& isset(static::$codeToMsg[$msg['code']])) {
			$err = static::$codeToMsg[$msg['code']];
		}
		self::dieError($msg, $args, $err);
	}

	/**
	 * Return json and end script execution
	 * @param $res result
	 */
	public static function die200($res = ['message' => 'ok']) {
		die(json_encode($res, JSON_UNESCAPED_UNICODE));
	}

	/**
	 * Finish script execution, positively or negatively
	 */
	public static function finish($res) {
		if (is_array($res) && isset($res['error']))
			static::die500($res);
		static::die200($res);
	}

	/**
	 * End script execution and return error
	 *
	 * @param string|array $msg  error message (включая параметры)
	 * @param array        $args  параметры идущие только в лог (null by default)
	 * @param string       $error  заголовок ошибки (Error500Header by default)
	 */
	public static function dieError($msg, $args = null,
			$error = self::Error500Header) {
		$msg_log = $msg;
		if (!is_string($msg))
			$msg_log = json_encode($msg, JSON_UNESCAPED_UNICODE);
		\logger\Log::instance()->logError($msg_log, $args);
		if ((isset($GLOBALS['flag_test']) && ($GLOBALS['flag_test']))
			|| class_exists('PHPUnit_Runner_Version'))
			\Testing\CoreTestBase::fail(is_array($msg)
				? "die500: ".var_export($msg, true)
				: "die500: $msg"
			);
		if (!headers_sent())
			header($error);
		if (is_string($msg))
			$msg = ['error' => $msg];
		die(json_encode($msg));
	}

	/**
	 * Returns array consisting of the numerical values of the input associative array
	 *
	 * @param  array $items  associative array
	 * @return array $ret    numerical array
	 */
	public static function intArray($items) {
		$ret = [];
		if (!is_array($items)) return $items;
		foreach ($items as $k => $v) {
			if (is_numeric($v))
				$ret [] = (int) $v;
		}
		return $ret;
	}

	/**
	 * Получить дату последнего обновления для вставки в базу
	 */
	public static function getLastUpdate() {
		return new \DAO\Sql\Expr('UNIX_TIMESTAMP(NOW())');
	}

	/**
	 * Проверка параметров
	 * @param $in  входной массив
	 * @param $req обязательные параметры
	 * @param $opt необязательные параметры
	 * @return array обязательные + необязательные
	 */
	public static function getVars(&$in, $req, $opt = array()) {
		$res = array();
		//processing requested parameters
		foreach ($req as $var) {
			if (!isset($in[$var]))
				self::die500('empty ' . $var);
			$res[$var] = $in[$var];
		}
		//processing optional parameters
		foreach ($opt as $var) {
			if (!isset($in[$var])) continue;
			$res[$var] = $in[$var];
		}
		return $res;
	}

	/**
	 * Remove unnecessary information from row if marked as deleted
	 * @param $row row
	 */
	public static function fixDeleted($row) {
		if (!isset($row['deleted']) || !$row['deleted'])
			return $row;
		$fields = [];
		if (isset($row['id']))
			$fields['id'] = $row['id'];
		else
			$fields = $row; // save all info
		$fields['deleted'] = 1;
		return $fields;
	}
}

?>
