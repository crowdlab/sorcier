<?php
class Config {
	/** get config value */
	public static function get($v, $default = null, $config = null) {
		if ($config == null)
			global $config;
		if (is_array($v)) {
			$co = $config;
			foreach ($v as $key) {
				if (isset($co[$key]) && is_array($co))
					$co = $co[$key];
				else
					return null;
			}
			return $co;
		}
		return (isset($config[$v])) ? $v : $default;
	}
}
?>
