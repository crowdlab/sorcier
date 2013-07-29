<?php
/**
 * Класс по обработке загрузки файлов
 * @class UploadHandler
 */
namespace Files;
use Files;

class UploadHandler {
	use \Singleton;

	public $options;

	// TODO (max): find better way to call with args
	protected function init($options = null) {
		global $config;
		$this->options = [
			'script_url'              => $this->getFullUrl() . '/',
			'upload_dir'              => $config["files"]["path"],
			'upload_url'              => $config["files"]["url"]
				? $config["files"]["url"]
				: $this->getFullUrl() . '/files/',
			'param_name'              => 'files',
			// Set the following option to 'POST', if your server does not support
			// DELETE requests. This is a parameter sent to the client:
			'delete_type'             => 'DELETE',
			// The php.ini settings upload_max_filesize and post_max_size
			// take precedence over the following max_file_size setting:
			'max_file_size'           => null,
			'min_file_size'           => 1,
			'accept_file_types'       => '/.+$/i',
			// The maximum number of files for the upload directory:
			'max_number_of_files'     => null,
			// Image resolution restrictions:
			'max_width'               => null,
			'max_height'              => null,
			'min_width'               => 1,
			'min_height'              => 1,
			// Set the following option to false to enable resumable uploads:
			'discard_aborted_uploads' => false,
			// Set to true to rotate images based on EXIF meta data, if available:
			'orient_image'            => false,
			'image_versions'          => array(
				// Uncomment the following version to restrict the size of
				// uploaded images. You can also add additional versions with
				// their own upload directories:

				'100x100'   => array(
					'upload_dir'   => $config["files"]["path"] . "100x100_",
					'upload_url'   => $config["files"]["url"] . "100x100_",
					'width'    => 100,
					'height'   => 100,
					'jpeg_quality' => 95,
					'png_quality'  => 9
				),

				'128x128' => array(
					'upload_dir' => $config["files"]["path"] . "128x128_",
					'upload_url' => $config["files"]["url"] . "128x128_",
					'width'  => 128,
					'height' => 128,
					'cut'        => true
				),

				'90x90'   => array(
					'upload_dir' => $config["files"]["path"] . "90x90_",
					'upload_url' => $config["files"]["url"] . "90x90_",
					'width'  => 90,
					'height' => 90,
					'cut'        => true
				),

				'64x64'   => array(
					'upload_dir' => $config["files"]["path"] . "64x64_",
					'upload_url' => $config["files"]["url"] . "64x64_",
					'width'  => 64,
					'height' => 64,
					'cut'        => true
				),

				'40x40'   => array(
					'upload_dir' => $config["files"]["path"] . "40x40_",
					'upload_url' => $config["files"]["url"] . "40x40_",
					'width'  => 40,
					'height' => 40,
					'cut'        => true
				),

				'20x20'   => array(
					'upload_dir' => $config["files"]["path"] . "20x20_",
					'upload_url' => $config["files"]["url"] . "20x20_",
					'width'  => 20,
					'height' => 20,
					'cut'        => true
				)
			)
		];
		if ($options) {
			$this->options = array_replace_recursive($this->options, $options);
		}
	}

	protected function getFullUrl() {
		$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
		return
			($https ? 'https://' : 'http://') . (!empty($_SERVER['REMOTE_USER'])
				? $_SERVER['REMOTE_USER'] . '@'
				: '') . (isset($_SERVER['HTTP_HOST'])
				? $_SERVER['HTTP_HOST']
				: ($_SERVER['SERVER_NAME']
				. ($https && $_SERVER['SERVER_PORT'] === 443 || $_SERVER['SERVER_PORT'] === 80
					? ''
					: ':' . $_SERVER['SERVER_PORT']))) .
			substr($_SERVER['SCRIPT_NAME'], 0,
				strrpos($_SERVER['SCRIPT_NAME'], '/'));
	}

	public function getURL($name) {
		return $this->options['upload_url'] . $name;
	}

	protected function set_file_delete_url($file) {
		$file->delete_url = $this->options['script_url']
			. '?file=' . rawurlencode($file->name);
		$file->delete_type = $this->options['delete_type'];
		if ($file->delete_type !== 'DELETE') {
			$file->delete_url .= '&_method=DELETE';
		}
	}

	protected function get_file_object($file_name) {
		$file_path = $this->options['upload_dir'] . $file_name;
		if (is_file($file_path) && $file_name[0] !== '.') {
			$file = new \stdClass();
			$file->name = $file_name;
			$file->size = filesize($file_path);
			$file->url = $this->options['upload_url'] . rawurlencode($file->name);
			foreach ($this->options['image_versions'] as $version => $options) {
				if (is_file($options['upload_dir'] . $file_name)) {
					$file->{$version . '_url'} = $options['upload_url']
						. rawurlencode($file->name);
				}
			}
			$this->set_file_delete_url($file);
			return $file;
		}
		return null;
	}

	protected function get_file_objects() {
		return array_values(array_filter(array_map(
			array($this, 'get_file_object'),
			scandir($this->options['upload_dir'])
		)));
	}

	protected function create_scaled_image($size, $options) {
		$file_path = $this->options['upload_dir'] . $size['hash'];
		$new_file_path = $options['upload_dir'] . $size['hash'];
		// Get image attributes
		$attr = @getimagesize($file_path);
		list($img_width, $img_height) = $attr;
		if (!$img_width || !$img_height) {
			return false;
		}
		$scale = $img_width / $size['bw'];

		$new_x = $size['x'] * $scale;
		$new_y = $size['y'] * $scale;
		$new_w = $size['w'] * $scale;

		$new_width = $options['width'];
		$new_img = imagecreatetruecolor($new_width, $new_width);

		// Get image type
		$type = strtolower(substr(strrchr($attr['mime'], '/'), 1));

		// Resize image
		switch ($type) {
			case 'jpg':
			case 'jpeg':
				$src_img = \imagecreatefromjpeg($file_path);
				$write_image = 'imagejpeg';
				$image_quality = isset($options['jpeg_quality']) ?
					$options['jpeg_quality'] : 75;
				break;
			case 'gif':
				imagecolortransparent($new_img,
					imagecolorallocate($new_img, 0, 0, 0));
				$src_img = imagecreatefromgif($file_path);
				$write_image = 'imagepng';
				if (isset($_REQUEST['avatar'])) {
					$new_file_path = self::renameGif($new_file_path);
				}
				$image_quality = null;
				break;
			case 'png':
				imagecolortransparent($new_img,
					imagecolorallocate($new_img, 0, 0, 0));
				imagealphablending($new_img, false);
				imagesavealpha($new_img, true);
				$src_img = imagecreatefrompng($file_path);
				$write_image = 'imagepng';
				$image_quality = isset($options['png_quality']) ?
					$options['png_quality'] : 9;
				break;
			default:
				$src_img = null;
		}

		$success = $src_img && imagecopyresampled(
			$new_img,
			$src_img,
			0, 0, $new_x, $new_y,
			$new_width,
			$new_width,
			$new_w,
			$new_w
		) && $write_image($new_img, $new_file_path, $image_quality);
		// Free up memory (imagedestroy does not delete files):
		imagedestroy($src_img);
		imagedestroy($new_img);
		return $success;
	}

	protected function validate($uploaded_file, $file, $error, $index) {
		if ($error) {
			$file->error = $error;
			return false;
		}
		if (!$file->name) {
			$file->error = 'missingFileName';
			return false;
		}
		if (!preg_match($this->options['accept_file_types'], $file->name)) {
			$file->error = 'acceptFileTypes';
			return false;
		}
		$file_size = ($uploaded_file && is_uploaded_file($uploaded_file))
			? filesize($uploaded_file)
			: $_SERVER['CONTENT_LENGTH'];
		if ($this->options['max_file_size'] && (
			$file_size > $this->options['max_file_size'] ||
				$file->size > $this->options['max_file_size'])
		) {
			$file->error = 'maxFileSize';
			return false;
		}
		if ($this->options['min_file_size'] &&
			$file_size < $this->options['min_file_size']
		) {
			$file->error = 'minFileSize';
			return false;
		}
		if (is_int($this->options['max_number_of_files']) && (
			count($this->get_file_objects()) >= $this->options['max_number_of_files'])
		) {
			$file->error = 'maxNumberOfFiles';
			return false;
		}
		list($img_width, $img_height) = @getimagesize($uploaded_file);
		if (is_int($img_width)) {
			if ($this->options['max_width'] && $img_width > $this->options['max_width'] ||
				$this->options['max_height'] && $img_height > $this->options['max_height']
			) {
				$file->error = 'maxResolution';
				return false;
			}
			if ($this->options['min_width'] && $img_width < $this->options['min_width'] ||
				$this->options['min_height'] && $img_height < $this->options['min_height']
			) {
				$file->error = 'minResolution';
				return false;
			}
		}
		return true;
	}

	protected function upcount_name_callback($matches) {
		$index = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
		$ext = isset($matches[2]) ? $matches[2] : '';
		return ' (' . $index . ')' . $ext;
	}

	/**
	 *  Если файл с таким именем уже существует, добавляем номер
	 */
	protected function upcount_name($name) {
		return preg_replace_callback(
			'/(?:(?: \(([\d]+)\))?(\.[^.]+))?$/',
			[$this, 'upcount_name_callback'],
			$name,
			1
		);
	}

	/**
	 *  Remove path information and dots around the filename, to prevent uploading
	 *  into different directories or replacing hidden system files.
	 *    Also remove control characters and spaces (\x00..\x20) around the filename:
	 */
	protected function trim_file_name($name, $type, $index) {

		$file_name = trim(basename(stripslashes($name)), ".\x00..\x20");
		// Add missing file extension for known image types:
		if (strpos($file_name, '.') === false &&
			preg_match('/^image\/(gif|jpe?g|png)/', $type, $matches)
		)
			$file_name .= '.' . $matches[1];
		if ($this->options['discard_aborted_uploads']) {
			while (is_file($this->options['upload_dir'] . $file_name)) {
				$file_name = $this->upcount_name($file_name);
			}
		}
		return $file_name;
	}

	/**
	 *  Generate file hash name with type
	 *
	 * @param $name    file name
	 * @return hash name
	 */
	private function getHashName($type = '') {
		if (isset($_REQUEST['avatar'])) {
			if (strtolower($type) == 'gif')
				$type = 'png';
		}
		return uniqid("file_") . '.' . $type;
	}

	protected static function renameGif($name) {
		$name = str_replace('.gif', '.png', $name);
		$name = str_replace('.GIF', '.png', $name);
		return $name;
	}

	/*
	 * Crop image
	 * @param $size - {x: offset by x,
	 *                 y: offset by y,
	 *                 w: cropping area's size in client scale,
	 *                 bw: image width in client scale}
	 */
	public function crop($size) {
		foreach ($this->options['image_versions'] as $version => $options) {
			if ($this->create_scaled_image($size, $options)) {
				\logger\Log::instance()->logInfo('Crop file:', [$size['hash'], $version]);
			}
		}
		return true;
	}

	/**
	 * Обработка загружаемого файла
	 * @param $uploaded_file - путь к временному файлу
	 * @param $index         - индекс в массиве передаваемых файлов (если несколько файлов передается в одном запросе)
	 * @param $hash          - hash имя файла, если он передается частями
	 */
	protected function handle_file_upload($uploaded_file, $name, $size, $type,
		$hash, $error, $index = null, $set_author_id = false) {
		\logger\Log::instance()->logInfo('Start uploading file:', $name);
		$file = new \stdClass();
		$file->type = pathinfo($name, PATHINFO_EXTENSION);
		$file->name = $this->trim_file_name($name, $type, $index);
		if (isset($_REQUEST['avatar'])) {
			$file->name = self::renameGif($file->name);
		}
		$file->hash = $hash;
		if ($set_author_id)
			$file->author_id = \UserSingleton::getInstance()->getId(false);
		$file->size = intval($size);
		$file->public = isset($_REQUEST['public']) && $_REQUEST['public'] == 0 ?
			0 : 1;

		\logger\Log::instance()->logInfo('Parse file:', $file);
		if ($this->validate($uploaded_file, $file, $error, $index)) {
			// Проверяем, что $hash был сформирован на сервере
			if (isset($hash) && !is_file($this->options['upload_dir'] . $hash))
				\Common::die500("Error while uploading file's chunk. First part of file doesn't exist.");
			else
				$file->hash = isset($hash) ? $hash :
					$this->getHashName($file->type);

			$file_path = $this->options['upload_dir'] . $file->hash;
			\logger\Log::instance()->logDebug($file_path);
			$append_file = !$this->options['discard_aborted_uploads'] &&
				is_file($file_path) && $file->size > filesize($file_path);
			clearstatcache();
			if ($uploaded_file && is_uploaded_file($uploaded_file)) {
				// multipart/formdata uploads (POST method uploads)
				if ($append_file) {
					file_put_contents(
						$file_path,
						fopen($uploaded_file, 'r'),
						FILE_APPEND
					);
				} else {
					move_uploaded_file($uploaded_file, $file_path);
				}
			} else {
				// Non-multipart uploads (PUT method support)
				file_put_contents(
					$file_path,
					fopen('php://input', 'r'),
					$append_file ? FILE_APPEND : 0
				);
			}
			$file_size = filesize($file_path);
			if ($file_size === $file->size) {
				// Регистрируем файл
				$fDAO = \DAO\FilesDAO::getInstance();
				$file->id = $fDAO->create($file);
				// URL для доступа к файлу
				$file->url = $this->getURL(rawurlencode($file->hash));
				// Для картинок.
				if (isset($_SERVER['HTTP_X_FILE_AVATAR']) && false)
					foreach ($this->options['image_versions'] as $version => $options) {
						if ($this->create_scaled_image($file->hash,
							$options)
						) {
							if ($this->options['upload_dir'] !== $options['upload_dir']) {
								$file->{$version . '_url'} = $options['upload_url']
									. rawurlencode($file->hash);
							} else {
								clearstatcache();
								$file_size = filesize($file_path);
							}
						}
					}
			} else if ($this->options['discard_aborted_uploads']) {
				unlink($file_path);
				$file->error = 'abort';
			}
			$file->size = $file_size;
			$this->set_file_delete_url($file);
		}
		return $file;
	}

	public function get() {
		$file_name = isset($_REQUEST['file']) ?
			basename(stripslashes($_REQUEST['file'])) : null;
		if ($file_name) {
			$info = $this->get_file_object($file_name);
		} else {
			$info = array(); // do not give out full file list
		}
		header('Content-type: application/json');
		echo json_encode($info);
	}

	/**
	 *  Обработать POST запрос с файлом
	 */
	public function post($set_author_id = false) {
		if (isset($_REQUEST['_method']) && $_REQUEST['_method'] === 'DELETE') {
			return $this->delete();
		}
		$upload = isset($_FILES[$this->options['param_name']]) ?
			$_FILES[$this->options['param_name']] : null;
		$info = array();
		if ($upload && is_array($upload['tmp_name'])) {
			// param_name is an array identifier like "files[]",
			// $_FILES is a multi-dimensional array:
			foreach ($upload['tmp_name'] as $index => $value) {
				$info[] = $this->handle_file_upload(
					$upload['tmp_name'][$index],
					isset($_SERVER['HTTP_X_FILE_NAME']) ?
						$_SERVER['HTTP_X_FILE_NAME'] :
						$upload['name'][$index],
					isset($_SERVER['HTTP_X_FILE_SIZE']) ?
						$_SERVER['HTTP_X_FILE_SIZE'] :
						$upload['size'][$index],
					isset($_SERVER['HTTP_X_FILE_TYPE']) ?
						$_SERVER['HTTP_X_FILE_TYPE'] :
						$upload['type'][$index],
					isset($_SERVER['HTTP_X_FILE_HASH_NAME']) ?
						$_SERVER['HTTP_X_FILE_HASH_NAME'] : null,
					$upload['error'][$index],
					$index,
					$set_author_id
				);
			}
		} elseif ($upload || isset($_SERVER['HTTP_X_FILE_NAME'])) {
			// param_name is a single object identifier like "file",
			// $_FILES is a one-dimensional array:
			$info[] = $this->handle_file_upload(
				isset($upload['tmp_name']) ? $upload['tmp_name'] : null,
				isset($_SERVER['HTTP_X_FILE_NAME']) ?
					$_SERVER['HTTP_X_FILE_NAME'] : (isset($upload['name']) ?
					$upload['name'] : null),
				isset($_SERVER['HTTP_X_FILE_SIZE']) ?
					$_SERVER['HTTP_X_FILE_SIZE'] : (isset($upload['size']) ?
					$upload['size'] : null),
				isset($_SERVER['HTTP_X_FILE_TYPE']) ?
					$_SERVER['HTTP_X_FILE_TYPE'] : (isset($upload['type']) ?
					$upload['type'] : null),
				isset($_SERVER['HTTP_X_FILE_HASH_NAME']) ?
					$_SERVER['HTTP_X_FILE_HASH_NAME'] : uniqid("file_"),
				isset($upload['error']) ? $upload['error'] : null,
				null,
				$set_author_id
			);
		}
		header('Vary: Accept');
		$json = json_encode($info);
		$redirect = isset($_REQUEST['redirect']) ?
			stripslashes($_REQUEST['redirect']) : null;
		if ($redirect) {
			header('Location: ' . sprintf($redirect, rawurlencode($json)));
			return;
		}
		if (isset($_SERVER['HTTP_ACCEPT']) &&
			(strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
		) {
			header('Content-type: application/json');
		} else {
			header('Content-type: text/plain');
		}
		echo $json;
	}

	public function delete() {
		$file_name = isset($_REQUEST['file']) ?
			basename(stripslashes($_REQUEST['file'])) : null;
		$file_path = $this->options['upload_dir'] . $file_name;
		$success = is_file($file_path) && $file_name[0] !== '.' && unlink($file_path);
		if ($success) {
			foreach ($this->options['image_versions'] as $version => $options) {
				$file = $options['upload_dir'] . $file_name;
				if (is_file($file)) {
					unlink($file);
				}
			}
		}
		header('Content-type: application/json');
		echo json_encode($success);
	}

}

?>
