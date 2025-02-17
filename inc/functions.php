<?php
	if($_SERVER['SCRIPT_FILENAME'] == str_replace('\\', '/', __FILE__)) {
		// You cannot request this file directly.
		header('Location: ../', true, 302);
		exit;
	}
	
	require 'contrib/gettext/gettext.inc';
	
	register_shutdown_function('fatal_error_handler');
	mb_internal_encoding('UTF-8');
	loadConfig();
	
	function loadConfig() {
		global $board, $config, $__ip, $debug, $__version;
		
		if(!isset($_SERVER['REMOTE_ADDR']))
			$_SERVER['REMOTE_ADDR'] = '0.0.0.0';
		
		require 'inc/config.php';
		if (file_exists('inc/instance-config.php')) {
			require 'inc/instance-config.php';
		}
		if(isset($board['dir']) && file_exists($board['dir'] . '/config.php')) {
			require $board['dir'] . '/config.php';
		}
		
		if(!isset($__version))
			$__version = file_exists('.installed') ? trim(file_get_contents('.installed')) : false;
		$config['version'] = $__version;
		
		if($config['debug']) {
			if(!isset($debug)) {
				$debug = Array('sql' => Array(), 'purge' => Array(), 'cached' => Array());
				$debug['start'] = microtime(true);
			}
		}
		
		date_default_timezone_set($config['timezone']);
		
		if(!isset($config['post_url']))
			$config['post_url'] = $config['root'] . $config['file_post'];
		
		if(!isset($config['referer_match']))
			$config['referer_match'] = '/^' .
				(preg_match($config['url_regex'], $config['root']) ? '' :
					'https?:\/\/' . $_SERVER['HTTP_HOST']) .
					preg_quote($config['root'], '/') .
				'(' .
						str_replace('%s', '\w+', preg_quote($config['board_path'], '/')) .
						'(' .
							preg_quote($config['file_index'], '/') . '|' .
							str_replace('%d', '\d+', preg_quote($config['file_page'])) .
						')?' .
					'|' .
						str_replace('%s', '\w+', preg_quote($config['board_path'], '/')) .
						preg_quote($config['dir']['res'], '/') .
						str_replace('%d', '\d+', preg_quote($config['file_page'], '/')) .
					'|' .
						preg_quote($config['file_mod'], '/') . '\?\/.+' .
				')([#?].+)?$/i';
		
		if(!isset($config['cookies']['path']))
			$config['cookies']['path'] = &$config['root'];
			
		if(!isset($config['dir']['static']))
			$config['dir']['static'] = $config['root'] . 'static/';
		
		if(!isset($config['image_sticky']))
			$config['image_sticky'] = $config['dir']['static'] . 'sticky.gif';
		if(!isset($config['image_locked']))
			$config['image_locked'] = $config['dir']['static'] . 'locked.gif';
		if(!isset($config['image_bumplocked']))
			$config['image_bumplocked'] = $config['dir']['static'] . 'sage.gif';
		if(!isset($config['image_deleted']))
			$config['image_deleted'] = $config['dir']['static'] . 'deleted.png';
		if(!isset($config['image_zip']))
			$config['image_zip'] = $config['dir']['static'] . 'zip.png';
		
		if(!isset($config['uri_thumb']))
			$config['uri_thumb'] = $config['root'] . $board['dir'] . $config['dir']['thumb'];
		elseif(isset($board['dir']))
			$config['uri_thumb'] = sprintf($config['uri_thumb'], $board['dir']);
			
		if(!isset($config['uri_img']))
			$config['uri_img'] = $config['root'] . $board['dir'] . $config['dir']['img'];
		elseif(isset($board['dir']))
			$config['uri_img'] = sprintf($config['uri_img'], $board['dir']);
		
		if(!isset($config['uri_stylesheets']))
			$config['uri_stylesheets'] = $config['root'] . 'stylesheets/';
		
		if(!isset($config['url_stylesheet']))
			$config['url_stylesheet'] = $config['uri_stylesheets'] . 'style.css';
		if(!isset($config['url_javascript']))
			$config['url_javascript'] = $config['root'] . 'main.js';
		
		if($config['root_file']) {
			chdir($config['root_file']);
		}

		if($config['verbose_errors']) {
			error_reporting(E_ALL);
			ini_set('display_errors', 1);
		}
		
		// Keep the original address to properly comply with other board configurations
		if(!isset($__ip))
			$__ip = $_SERVER['REMOTE_ADDR'];
		
		// ::ffff:0.0.0.0
		if(preg_match('/^\:\:(ffff\:)?(\d+\.\d+\.\d+\.\d+)$/', $__ip, $m))
			$_SERVER['REMOTE_ADDR'] = $m[2];
		
		if(_setlocale(LC_ALL, $config['locale']) === false) {
			$error = function_exists('error') ? 'error' : 'basic_error_function_because_the_other_isnt_loaded_yet';
			$error('The specified locale (' . $config['locale'] . ') does not exist on your platform!');
		}
		
		if(extension_loaded('gettext')) {
			bindtextdomain('tinyboard', './inc/locale');
			bind_textdomain_codeset('tinyboard', 'UTF-8');
			textdomain('tinyboard');
		} else {
			_bindtextdomain('tinyboard', './inc/locale');
			_bind_textdomain_codeset('tinyboard', 'UTF-8');
			_textdomain('tinyboard');
		}
		
		
		if($config['syslog'])
			openlog('tinyboard', LOG_ODELAY, LOG_SYSLOG); // open a connection to sysem logger
		
		if($config['recaptcha'])
			require_once 'inc/contrib/recaptcha/recaptchalib.php';
		if($config['cache']['enabled'])
			require_once 'inc/cache.php';
	}
	
	function basic_error_function_because_the_other_isnt_loaded_yet($message, $priority = true) {
		global $config;
		
		if($config['syslog'] && $priority !== false) {
			// Use LOG_NOTICE instead of LOG_ERR or LOG_WARNING because most error message are not significant.
			_syslog($priority !== true ? $priority : LOG_NOTICE, $message);
		}
		
		// Yes, this is horrible.
		die('<!DOCTYPE html><html><head><title>Error</title>' .
			'<style type="text/css">' .
				'body{text-align:center;font-family:arial, helvetica, sans-serif;font-size:10pt;}' .
				'p{padding:0;margin:20px 0;}' .
				'p.c{font-size:11px;}' .
			'</style></head>' .
			'<body><h2>Error</h2>' . $message . '<hr/>' .
			'<p class="c">This alternative error page is being displayed because the other couldn\'t be found or hasn\'t loaded yet.</p></body></html>');
	}
	
	function fatal_error_handler() { 
		if($error = error_get_last()) {
			if($error['type'] == E_ERROR) {
				if(function_exists('error')) {
					error('Caught fatal error: ' . $error['message'] . ' in <strong>' . $error['file'] . '</strong> on line ' . $error['line'], LOG_ERR);
				} else {
					basic_error_function_because_the_other_isnt_loaded_yet('Caught fatal error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'], LOG_ERR);
				}
			}
		}
	}
	
	function _syslog($priority, $message) {
		if(	isset($_SERVER['REMOTE_ADDR']) &&
			isset($_SERVER['REQUEST_METHOD']) &&
			isset($_SERVER['REQUEST_URI'])) {
			// CGI
			syslog($priority, $message . ' - client: ' . $_SERVER['REMOTE_ADDR'] . ', request: "' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . '"');
		} else {
			syslog($priority, $message);
		}
	}
	
	function loadThemeConfig($_theme) {
		global $config;
		
		if(!file_exists($config['dir']['themes'] . '/' . $_theme . '/info.php'))
			return false;
		
		// Load theme information into $theme
		include $config['dir']['themes'] . '/' . $_theme . '/info.php';
		return $theme;
	}
	
	function rebuildTheme($theme, $action) {
		global $config, $_theme;
		$_theme = $theme;
		
		$theme = loadThemeConfig($_theme);
		
		if(file_exists($config['dir']['themes'] . '/' . $_theme . '/theme.php')) {
			require_once $config['dir']['themes'] . '/' . $_theme . '/theme.php';
			$theme['build_function']($action, themeSettings($_theme));
		}
	}
	
	function rebuildThemes($action) {
		global $config, $_theme;
		
		// List themes
		$query = query("SELECT `theme` FROM `theme_settings` WHERE `name` IS NULL AND `value` IS NULL") or error(db_error());
		while($theme = $query->fetch()) {
			rebuildTheme($theme['theme'], $action);
		}
	}
	
	function themeSettings($theme) {
		$query = prepare("SELECT `name`, `value` FROM `theme_settings` WHERE `theme` = :theme AND `name` IS NOT NULL");
		$query->bindValue(':theme', $theme);
		$query->execute() or error(db_error($query));
		
		$settings = Array();
		while($s = $query->fetch()) {
			$settings[$s['name']] = $s['value'];
		}
		
		return $settings;
	}
	
	function sprintf3($str, $vars, $delim = '%') {
		$replaces = array();
		foreach($vars as $k => $v) {
			$replaces[$delim . $k . $delim] = $v;
		}
		return str_replace(array_keys($replaces),
		                   array_values($replaces), $str);
	}
	
	function setupBoard($array) {
		global $board, $config;
		
		$board = Array(
		'id' => $array['id'],
		'uri' => $array['uri'],
		'name' => $array['title'],
		'title' => $array['subtitle']);
		
		$board['dir'] = sprintf($config['board_path'], $board['uri']);
		$board['url'] = sprintf($config['board_abbreviation'], $board['uri']);
		
		loadConfig();
		
		if(!file_exists($board['dir']))
			mkdir($board['dir'], 0777) or error("Couldn't create " . $board['dir'] . ". Check permissions.", true);
		if(!file_exists($board['dir'] . $config['dir']['img']))
			@mkdir($board['dir'] . $config['dir']['img'], 0777) or error("Couldn't create " . $board['dir'] . $config['dir']['img'] . ". Check permissions.", true);
		if(!file_exists($board['dir'] . $config['dir']['thumb']))
			@mkdir($board['dir'] . $config['dir']['thumb'], 0777) or error("Couldn't create " . $board['dir'] . $config['dir']['img'] . ". Check permissions.", true);
		if(!file_exists($board['dir'] . $config['dir']['res']))
			@mkdir($board['dir'] . $config['dir']['res'], 0777) or error("Couldn't create " . $board['dir'] . $config['dir']['img'] . ". Check permissions.", true);
	}
	
	function openBoard($uri) {
		global $config;
		if($config['cache']['enabled'] && ($board = cache::get('board_' . $uri))) {
			setupBoard($board);
			return true;
		}
		
		$query = prepare("SELECT * FROM `boards` WHERE `uri` = :uri LIMIT 1");
		$query->bindValue(':uri', $uri);
		$query->execute() or error(db_error($query));
		
		if($board = $query->fetch()) {
			if($config['cache']['enabled'])
				cache::set('board_' . $uri, $board);
			setupBoard($board);
			return true;
		} else return false;
	}
	
	function boardTitle($uri) {
		global $config;
		if($config['cache']['enabled'] && ($board = cache::get('board_' . $uri))) {
			return $board['title'];
		}
		
		$query = prepare("SELECT `title` FROM `boards` WHERE `uri` = :uri LIMIT 1");
		$query->bindValue(':uri', $uri);
		$query->execute() or error(db_error($query));
		
		if($title = $query->fetch()) {
			return $title['title'];
		} else return false;
	}
	
	function purge($uri) {
		global $config, $debug;
		if(preg_match($config['referer_match'], $config['root'])) {
			$uri = (str_replace('\\', '/', dirname($_SERVER['REQUEST_URI'])) == '/' ? '/' : str_replace('\\', '/', dirname($_SERVER['REQUEST_URI'])) . '/') . $uri;
		} else {
			$uri = $config['root'] . $uri;
		}
		
		if($config['debug']) {
			$debug['purge'][] = $uri;
		}
				
		foreach($config['purge'] as &$purge) {
			$host = &$purge[0];
			$port = &$purge[1];
			$http_host = isset($purge[2]) ? $purge[2] : $_SERVER['HTTP_HOST'];
			$request = "PURGE {$uri} HTTP/1.0\r\nHost: {$http_host}\r\nUser-Agent: Tinyboard\r\nConnection: Close\r\n\r\n";
			if($fp = fsockopen($host, $port, $errno, $errstr, $config['purge_timeout'])) {
				fwrite($fp, $request);
				fclose($fp);
			} else {
				// Cannot connect?
				error('Could not PURGE for ' . $host);
			}
		}
	}
	
	function file_write($path, $data, $simple = false, $skip_purge = false) {
		global $config;
		
		if(preg_match('/^remote:\/\/(.+)\:(.+)$/', $path, $m)) {
			if(isset($config['remote'][$m[1]])) {
				require_once 'inc/remote.php';
				
				$remote = new Remote($config['remote'][$m[1]]);
				$remote->write($data, $m[2]);
				return;
			} else {
				error('Invalid remote server: ' . $m[1]);
			}
		}
		
		if(!$fp = fopen($path, $simple ? 'w' : 'c'))
			error('Unable to open file for writing: ' . $path);
		
		// File locking
		if(!$simple && !flock($fp, LOCK_EX)) {
			error('Unable to lock file: ' . $path);
		}
		
		// Truncate file
		if(!$simple && !ftruncate($fp, 0))
			error('Unable to truncate file: ' . $path);
			
		// Write data
		if(fwrite($fp, $data) === false)
			error('Unable to write to file: ' . $path);
		
		// Unlock
		if(!$simple)
			flock($fp, LOCK_UN);
		
		// Close
		if(!fclose($fp))
			error('Unable to close file: ' . $path);
		
		if(!$skip_purge && isset($config['purge']) && isset($_SERVER['HTTP_HOST'])) {
			// Purge cache
			if(basename($path) == $config['file_index']) {
				// Index file (/index.html); purge "/" as well
				$uri = dirname($path);
				// root
				if($uri == '.')
					$uri = '';
				else
					$uri .= '/';
				purge($uri);
			}
			purge($path);
		}
	}
	
	function file_unlink($path) {
		global $config, $debug;
		
		if($config['debug']) {
			if(!isset($debug['unlink']))
				$debug['unlink'] = Array();
			$debug['unlink'][] = $path;
		}
		
		$ret = @unlink($path);
		if(isset($config['purge']) && $path[0] != '/' && isset($_SERVER['HTTP_HOST'])) {
			// Purge cache
			if(basename($path) == $config['file_index']) {
				// Index file (/index.html); purge "/" as well
				$uri = dirname($path);
				// root
				if($uri == '.')
					$uri = '';
				else
					$uri .= '/';
				purge($uri);
			}
			purge($path);
		}
		return $ret;
	}
	
	function listBoards() {
		global $config;
		
		if($config['cache']['enabled'] && ($boards = cache::get('all_boards')))
			return $boards;
		
		$query = query("SELECT * FROM `boards` ORDER BY `uri`") or error(db_error());
		$boards = $query->fetchAll();
		
		if($config['cache']['enabled'])
			cache::set('all_boards', $boards);
		
		return $boards;
	}
	
	function checkFlood($post) {
		global $board, $config;
		
		$query = prepare(sprintf("SELECT * FROM `posts_%s` WHERE (`ip` = :ip AND `time` >= :floodtime) OR (`ip` = :ip AND `body` != '' AND `body` = :body AND `time` >= :floodsameiptime) OR (`body` != ''  AND `body` = :body AND `time` >= :floodsametime) LIMIT 1", $board['uri']));
		$query->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
		$query->bindValue(':body', $post['body'], PDO::PARAM_INT);
		$query->bindValue(':floodtime', time()-$config['flood_time'], PDO::PARAM_INT);
		$query->bindValue(':floodsameiptime', time()-$config['flood_time_ip'], PDO::PARAM_INT);
		$query->bindValue(':floodsametime', time()-$config['flood_time_same'], PDO::PARAM_INT);
		$query->execute() or error(db_error($query));
		
		return (bool)$query->fetch();
	}
	
	function until($timestamp) {
		$difference = $timestamp - time();
		if($difference < 60) {
			return $difference . ' second' . ($difference != 1 ? 's' : '');
		} elseif($difference < 60*60) {
			return ($num = round($difference/(60))) . ' minute' . ($num != 1 ? 's' : '');
		} elseif($difference < 60*60*24) {
			return ($num = round($difference/(60*60))) . ' hour' . ($num != 1 ? 's' : '');
		} elseif($difference < 60*60*24*7) {
			return ($num = round($difference/(60*60*24))) . ' day' . ($num != 1 ? 's' : '');
		} elseif($difference < 60*60*24*365) {
			return ($num = round($difference/(60*60*24*7))) . ' week' . ($num != 1 ? 's' : '');
		} else {
			return ($num = round($difference/(60*60*24*365))) . ' year' . ($num != 1 ? 's' : '');
		}
	}
	
	function ago($timestamp) {
		$difference = time() - $timestamp;
		if($difference < 60) {
			return $difference . ' second' . ($difference != 1 ? 's' : '');
		} elseif($difference < 60*60) {
			return ($num = round($difference/(60))) . ' minute' . ($num != 1 ? 's' : '');
		} elseif($difference < 60*60*24) {
			return ($num = round($difference/(60*60))) . ' hour' . ($num != 1 ? 's' : '');
		} elseif($difference < 60*60*24*7) {
			return ($num = round($difference/(60*60*24))) . ' day' . ($num != 1 ? 's' : '');
		} elseif($difference < 60*60*24*365) {
			return ($num = round($difference/(60*60*24*7))) . ' week' . ($num != 1 ? 's' : '');
		} else {
			return ($num = round($difference/(60*60*24*365))) . ' year' . ($num != 1 ? 's' : '');
		}
	}
	
	function displayBan($ban) {
		global $config;
		
		$ban['ip'] = $_SERVER['REMOTE_ADDR'];
		
		// Show banned page and exit
		die(
			Element('page.html', Array(
				'title' => 'Banned!',
				'config' => $config,
				'body' => Element('banned.html', Array(
					'config' => $config,
					'ban' => $ban
				)
			))
		));
	}
	
	function checkBan($board = 0) {
		global $config;
		
		if(!isset($_SERVER['REMOTE_ADDR'])) {
			// Server misconfiguration
			return;
		}		
		
		$query = prepare("SELECT `set`, `expires`, `reason`, `board`, `uri`, `bans`.`id` FROM `bans` LEFT JOIN `boards` ON `boards`.`id` = `board` WHERE (`board` IS NULL OR `uri` = :board) AND `ip` = :ip ORDER BY `expires` IS NULL DESC, `expires` DESC, `expires` DESC LIMIT 1");
		$query->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
		$query->bindValue(':board', $board);
		$query->execute() or error(db_error($query));
		if($query->rowCount() < 1 && $config['ban_range']) {
			$query = prepare("SELECT `set`, `expires`, `reason`, `board`, `uri`, `bans`.`id` FROM `bans` LEFT JOIN `boards` ON `boards`.`id` = `board` WHERE (`board` IS NULL OR `uri` = :board) AND :ip LIKE REPLACE(REPLACE(`ip`, '%', '!%'), '*', '%') ESCAPE '!' ORDER BY `expires` IS NULL DESC, `expires` DESC LIMIT 1");
			$query->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
			$query->bindValue(':board', $board);
			$query->execute() or error(db_error($query));
		}
		
		if($query->rowCount() < 1 && $config['ban_cidr'] && !isIPv6()) {
			// my most insane SQL query yet
			$query = prepare("SELECT `set`, `expires`, `reason`, `board`, `uri`, `bans`.`id` FROM `bans` LEFT JOIN `boards` ON `boards`.`id` = `board` WHERE (`board` IS NULL OR `uri` = :board)
				AND (					
					`ip` REGEXP '^(\[0-9]+\.\[0-9]+\.\[0-9]+\.\[0-9]+\)\/(\[0-9]+)$'
						AND
					:ip >= INET_ATON(SUBSTRING_INDEX(`ip`, '/', 1))
						AND
					:ip < INET_ATON(SUBSTRING_INDEX(`ip`, '/', 1)) + POW(2, 32 - SUBSTRING_INDEX(`ip`, '/', -1))
				)
				ORDER BY `expires` IS NULL DESC, `expires` DESC LIMIT 1");
			$query->bindValue(':ip', ip2long($_SERVER['REMOTE_ADDR']));
			$query->bindValue(':board', $board);
			$query->execute() or error(db_error($query));
		}
		
		if($ban = $query->fetch()) {
			if($ban['expires'] && $ban['expires'] < time()) {
				// Ban expired
				$query = prepare("DELETE FROM `bans` WHERE `id` = :id LIMIT 1");
				$query->bindValue(':id', $ban['id'], PDO::PARAM_INT);
				$query->execute() or error(db_error($query));
				
				return;
			}
			
			displayBan($ban);
		}
	}
	
	function threadLocked($id) {
		global $board;
		
		$query = prepare(sprintf("SELECT `locked` FROM `posts_%s` WHERE `id` = :id AND `thread` IS NULL LIMIT 1", $board['uri']));
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error());
		
		if(!$post = $query->fetch()) {
			// Non-existant, so it can't be locked...
			return false;
		}
		
		return (bool) $post['locked'];
	}
	
	function threadSageLocked($id) {
		global $board;
		
		$query = prepare(sprintf("SELECT `sage` FROM `posts_%s` WHERE `id` = :id AND `thread` IS NULL LIMIT 1", $board['uri']));
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error());
		
		if(!$post = $query->fetch()) {
			// Non-existant, so it can't be locked...
			return false;
		}
		
		return (bool) $post['sage'];
	}
	
	function threadExists($id) {
		global $board;
		
		$query = prepare(sprintf("SELECT 1 FROM `posts_%s` WHERE `id` = :id AND `thread` IS NULL LIMIT 1", $board['uri']));
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error());
		
		if($query->rowCount()) {
			return true;
		} else return false;
	}
	
	function post($post, $OP) {
		global $pdo, $board;
		$query = prepare(sprintf("INSERT INTO `posts_%s` VALUES ( NULL, :thread, :subject, :email, :name, :trip, :capcode, :body, :body_nomarkup, :time, :time, :thumb, :thumbwidth, :thumbheight, :file, :width, :height, :filesize, :filename, :filehash, :password, :ip, :sticky, :locked, 0, :embed)", $board['uri']));
		
		// Basic stuff
		if(!empty($post['subject'])) {
			$query->bindValue(':subject', $post['subject']);
		} else {
			$query->bindValue(':subject', NULL, PDO::PARAM_NULL);
		}
		
		if(!empty($post['email'])) {
			$query->bindValue(':email', $post['email']);
		} else {
			$query->bindValue(':email', NULL, PDO::PARAM_NULL);
		}
		
		if(!empty($post['trip'])) {
			$query->bindValue(':trip', $post['trip']);
		} else {
			$query->bindValue(':trip', NULL, PDO::PARAM_NULL);
		}
		
		$query->bindValue(':name', $post['name']);
		$query->bindValue(':body', $post['body']);
		$query->bindValue(':body_nomarkup', $post['body_nomarkup']);
		$query->bindValue(':time', isset($post['time']) ? $post['time'] : time(), PDO::PARAM_INT);
		$query->bindValue(':password', $post['password']);		
		$query->bindValue(':ip', isset($post['ip']) ? $post['ip'] : $_SERVER['REMOTE_ADDR']);
		
		if($post['mod'] && $post['sticky']) {
			$query->bindValue(':sticky', 1, PDO::PARAM_INT);
		} else {
			$query->bindValue(':sticky', 0, PDO::PARAM_INT);
		}
		
		if($post['mod'] && $post['locked']) {
			$query->bindValue(':locked', 1, PDO::PARAM_INT);
		} else {
			$query->bindValue(':locked', 0, PDO::PARAM_INT);
		}
		
		if($post['mod'] && isset($post['capcode']) && $post['capcode']) {
			$query->bindValue(':capcode', $post['capcode'], PDO::PARAM_INT);
		} else {
			$query->bindValue(':capcode', NULL, PDO::PARAM_NULL);
		}
		
		if(!empty($post['embed'])) {
			$query->bindValue(':embed', $post['embed']);
		} else {
			$query->bindValue(':embed', NULL, PDO::PARAM_NULL);
		}
		
		if($OP) {
			// No parent thread, image
			$query->bindValue(':thread', null, PDO::PARAM_NULL);
		} else {
			$query->bindValue(':thread', $post['thread'], PDO::PARAM_INT);
		}
		
		if($post['has_file']) {
			$query->bindValue(':thumb', $post['thumb']);
			$query->bindValue(':thumbwidth', $post['thumbwidth'], PDO::PARAM_INT);
			$query->bindValue(':thumbheight', $post['thumbheight'], PDO::PARAM_INT);
			$query->bindValue(':file', $post['file']);
			$query->bindValue(':width', $post['width'], PDO::PARAM_INT);
			$query->bindValue(':height', $post['height'], PDO::PARAM_INT);
			$query->bindValue(':filesize', $post['filesize'], PDO::PARAM_INT);
			$query->bindValue(':filename', $post['filename']);
			$query->bindValue(':filehash', $post['filehash']);
		} else {
			$query->bindValue(':thumb', null, PDO::PARAM_NULL);
			$query->bindValue(':thumbwidth', null, PDO::PARAM_NULL);
			$query->bindValue(':thumbheight', null, PDO::PARAM_NULL);
			$query->bindValue(':file', null, PDO::PARAM_NULL);
			$query->bindValue(':width', null, PDO::PARAM_NULL);
			$query->bindValue(':height', null, PDO::PARAM_NULL);
			$query->bindValue(':filesize', null, PDO::PARAM_NULL);
			$query->bindValue(':filename', null, PDO::PARAM_NULL);
			$query->bindValue(':filehash', null, PDO::PARAM_NULL);
		}
		
		$query->execute() or error(db_error($query));
		
		return $pdo->lastInsertId();
	}
	
	function bumpThread($id) {
		global $board;
		$query = prepare(sprintf("UPDATE `posts_%s` SET `bump` = :time WHERE `id` = :id AND `thread` IS NULL", $board['uri']));
		$query->bindValue(':time', time(), PDO::PARAM_INT);
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));
	}
	
	// Remove file from post
	function deleteFile($id, $remove_entirely_if_already=true) {
		global $board, $config;
		
		$query = prepare(sprintf("SELECT `thread`,`thumb`,`file` FROM `posts_%s` WHERE `id` = :id LIMIT 1", $board['uri']));
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));
		
		if($query->rowCount() < 1) {
			error($config['error']['invalidpost']);
		}
		
		$post = $query->fetch();
		
		if($post['file'] == 'deleted' && !$post['thread'])
			return; // Can't delete OP's image completely.
		
		$query = prepare(sprintf("UPDATE `posts_%s` SET `thumb` = NULL, `thumbwidth` = NULL, `thumbheight` = NULL, `filewidth` = NULL, `fileheight` = NULL, `filesize` = NULL, `filename` = NULL, `filehash` = NULL, `file` = :file WHERE `id` = :id", $board['uri']));
		if($post['file'] == 'deleted' && $remove_entirely_if_already) {
			// Already deleted; remove file fully
			$query->bindValue(':file', null, PDO::PARAM_NULL);
		} else {
			// Delete thumbnail
			file_unlink($board['dir'] . $config['dir']['thumb'] . $post['thumb']);
			
			// Delete file
			file_unlink($board['dir'] . $config['dir']['img'] . $post['file']);
			
			// Set file to 'deleted'
			$query->bindValue(':file', 'deleted', PDO::PARAM_INT);
		}
		// Update database
		
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));
		
		if($post['thread'])
			buildThread($post['thread']);
	}
	
	// rebuild post (markup)
	function rebuildPost($id) {
		global $board;
		
		$query = prepare(sprintf("SELECT `body_nomarkup`, `thread` FROM `posts_%s` WHERE `id` = :id", $board['uri']));
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));
		
		if(!$post = $query->fetch())
			return false;
		
		if(!$post['body_nomarkup'])
			return false;
		
		markup($body = &$post['body_nomarkup']);
		
		$query = prepare(sprintf("UPDATE `posts_%s` SET `body` = :body WHERE `id` = :id", $board['uri']));
		$query->bindValue(':body', $body);
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));
		
		buildThread($post['thread'] ? $post['thread'] : $id);
		
		return true;
	}
	
	// Delete a post (reply or thread)
	function deletePost($id, $error_if_doesnt_exist=true, $rebuild_after=true) {
		global $board, $config;
		
		// Select post and replies (if thread) in one query
		$query = prepare(sprintf("SELECT `id`,`thread`,`thumb`,`file` FROM `posts_%s` WHERE `id` = :id OR `thread` = :id", $board['uri']));
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));
		
		if($query->rowCount() < 1) {
			if($error_if_doesnt_exist)
				error($config['error']['invalidpost']);
			else return false;
		}
		
		// Delete posts and maybe replies
		while($post = $query->fetch()) {
			if(!$post['thread']) {
				// Delete thread HTML page
				file_unlink($board['dir'] . $config['dir']['res'] . sprintf($config['file_page'], $post['id']));
			} elseif($query->rowCount() == 1) {
				// Rebuild thread
				$rebuild = &$post['thread'];
			}
			if($post['thumb']) {
				// Delete thumbnail
				file_unlink($board['dir'] . $config['dir']['thumb'] . $post['thumb']);
			}
			if($post['file']) {
				// Delete file
				file_unlink($board['dir'] . $config['dir']['img'] . $post['file']);
			}
		}
		
		$query = prepare(sprintf("DELETE FROM `posts_%s` WHERE `id` = :id OR `thread` = :id", $board['uri']));
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));
		
		$query = prepare("SELECT `board`, `post` FROM `cites` WHERE `target_board` = :board AND `target` = :id");
		$query->bindValue(':board', $board['uri']);
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));
		while($cite = $query->fetch()) {
			if($board['uri'] != $cite['board']) {
				if(!isset($tmp_board))
					$tmp_board = $board['uri'];
				openBoard($cite['board']);
			}
			rebuildPost($cite['post']);
		}
		
		if(isset($tmp_board))
			openBoard($tmp_board);
		
		$query = prepare("DELETE FROM `cites` WHERE (`target_board` = :board AND `target` = :id) OR (`board` = :board AND `post` = :id)");
		$query->bindValue(':board', $board['uri']);
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));
	
		if(isset($rebuild) && $rebuild_after) {
			buildThread($rebuild);
		}
		
		return true;
	}
	
	function clean() {
		global $board, $config;
		$offset = round($config['max_pages']*$config['threads_per_page']);
		
		// I too wish there was an easier way of doing this...
		$query = prepare(sprintf("SELECT `id` FROM `posts_%s` WHERE `thread` IS NULL ORDER BY `sticky` DESC, `bump` DESC LIMIT :offset, 9001", $board['uri']));
		$query->bindValue(':offset', $offset, PDO::PARAM_INT);
		
		$query->execute() or error(db_error($query));
		while($post = $query->fetch()) {
			deletePost($post['id']);
		}
	}
	
	function index($page, $mod=false) {
		global $board, $config, $debug;

		$body = '';
		$offset = round($page*$config['threads_per_page']-$config['threads_per_page']);
		
		$query = prepare(sprintf("SELECT * FROM `posts_%s` WHERE `thread` IS NULL ORDER BY `sticky` DESC, `bump` DESC LIMIT :offset,:threads_per_page", $board['uri']));
		$query->bindValue(':offset', $offset, PDO::PARAM_INT);
		$query->bindValue(':threads_per_page', $config['threads_per_page'], PDO::PARAM_INT);
		$query->execute() or error(db_error($query));
		
		if($query->rowcount() < 1 && $page > 1) return false;
		while($th = $query->fetch()) {
			if(!$mod && $config['cache']['enabled']) {
				if($built = cache::get("thread_index_{$board['uri']}_{$th['id']}")) {					
					$body .= $built;
					continue;
				}
			}
			
			$thread = new Thread($th['id'], $th['subject'], $th['email'], $th['name'], $th['trip'], $th['capcode'], $th['body'], $th['time'], $th['thumb'], $th['thumbwidth'], $th['thumbheight'], $th['file'], $th['filewidth'], $th['fileheight'], $th['filesize'], $th['filename'], $th['ip'], $th['sticky'], $th['locked'], $th['sage'], $th['embed'], $mod ? '?/' : $config['root'], $mod);
			
			$posts = prepare(sprintf("SELECT * FROM `posts_%s` WHERE `thread` = :id ORDER BY `id` DESC LIMIT :limit", $board['uri']));
			$posts->bindValue(':id', $th['id']);
			$posts->bindValue(':limit', ($th['sticky'] ? $config['threads_preview_sticky'] : $config['threads_preview']), PDO::PARAM_INT);
			$posts->execute() or error(db_error($posts));
			
			$num_images = 0;
			while($po = $posts->fetch()) {
				if($po['file'])
					$num_images++;
				
				$thread->add(new Post($po['id'], $th['id'], $po['subject'], $po['email'], $po['name'], $po['trip'], $po['capcode'], $po['body'], $po['time'], $po['thumb'], $po['thumbwidth'], $po['thumbheight'], $po['file'], $po['filewidth'], $po['fileheight'], $po['filesize'], $po['filename'], $po['ip'], $po['embed'], $mod ? '?/' : $config['root'], $mod));
			}
			
			if($posts->rowCount() == ($th['sticky'] ? $config['threads_preview_sticky'] : $config['threads_preview'])) {
				$count = prepare(sprintf("SELECT COUNT(`id`) as `num` FROM `posts_%s` WHERE `thread` = :thread UNION ALL SELECT COUNT(`id`) FROM `posts_%s` WHERE `file` IS NOT NULL AND `thread` = :thread", $board['uri'], $board['uri']));
				$count->bindValue(':thread', $th['id'], PDO::PARAM_INT);
				$count->execute() or error(db_error($count));
				
				$c = $count->fetch();
				$thread->omitted = $c['num'] - ($th['sticky'] ? $config['threads_preview_sticky'] : $config['threads_preview']);
				
				$c = $count->fetch();
				$thread->omitted_images = $c['num'] - $num_images;
			}
			
			$thread->posts = array_reverse($thread->posts);
			
			$body .= '<div id="thread_' . $thread->id . '">' . $thread->build(true) . '</div>';
		}
		
		return Array(
			'board'=>$board,
			'body'=>$body,
			'post_url' => $config['post_url'],
			'config' => $config,
			'boardlist' => createBoardlist($mod)
		);
	}
	
	function getPageButtons($pages, $mod=false) {
		global $config, $board;
		
		$btn = Array();
		$root = ($mod ? '?/' : $config['root']) . $board['dir'];
		
		foreach($pages as $num => $page) {
			if(isset($page['selected'])) {
				// Previous button
				if($num == 0) {
					// There is no previous page.
					$btn['prev'] = _('Previous');
				} else {
					$loc = ($mod ? '?/' . $board['uri'] . '/' : '') .
						($num == 1 ?
							$config['file_index']
						:
							sprintf($config['file_page'], $num)
						);
					
					$btn['prev'] = '<form action="' . ($mod ? '' : $root . $loc) . '" method="get">' .
						($mod ?
							'<input type="hidden" name="status" value="301" />' .
							'<input type="hidden" name="r" value="' . htmlentities($loc) . '" />'
						:'') .
					'<input type="submit" value="' . _('Previous') . '" /></form>';
				}
				
				if($num == count($pages) - 1) {
					// There is no next page.
					$btn['next'] = _('Next');
				} else {
					$loc = ($mod ? '?/' . $board['uri'] . '/' : '') . sprintf($config['file_page'], $num + 2);
					
					$btn['next'] = '<form action="' . ($mod ? '' : $root . $loc) . '" method="get">' .
						($mod ?
							'<input type="hidden" name="status" value="301" />' .
							'<input type="hidden" name="r" value="' . htmlentities($loc) . '" />'
						:'') .
					'<input type="submit" value="' . _('Next') . '" /></form>';
				}
			}
		}
		
		return $btn;
	}
	
	function getPages($mod=false) {
		global $board, $config;
		
		// Count threads
		$query = query(sprintf("SELECT COUNT(`id`) as `num` FROM `posts_%s` WHERE `thread` IS NULL", $board['uri'])) or error(db_error());
		
		$count = current($query->fetch());
		$count = floor(($config['threads_per_page'] + $count - 1) / $config['threads_per_page']);
		
		if($count < 1) $count = 1;
		
		$pages = Array();
		for($x=0;$x<$count && $x<$config['max_pages'];$x++) {
			$pages[] = Array(
				'num' => $x+1,
				'link' => $x==0 ? ($mod ? '?/' : $config['root']) . $board['dir'] . $config['file_index'] : ($mod ? '?/' : $config['root']) . $board['dir'] . sprintf($config['file_page'], $x+1)
			);
		}
		
		return $pages;
	}
	
	function makerobot($body) {
		global $config;
		$body = strtolower($body);
		
		// Leave only letters
		$body = preg_replace('/[^a-z]/i', '', $body);
		// Remove repeating characters
		if($config['robot_strip_repeating'])
			$body = preg_replace('/(.)\\1+/', '$1', $body);
		
		return sha1($body);
	}
	
	function checkRobot($body) {
		if(empty($body))
			return true;
		
		$body = makerobot($body);
		$query = prepare("SELECT 1 FROM `robot` WHERE `hash` = :hash LIMIT 1");
		$query->bindValue(':hash', $body);
		$query->execute() or error(db_error($query));
		
		if($query->fetch()) {
			return true;
		} else {
			// Insert new hash
			
			$query = prepare("INSERT INTO `robot` VALUES (:hash)");
			$query->bindValue(':hash', $body);
			$query->execute() or error(db_error($query));
			return false;
		}
	}
	
	function numPosts($id) {
		global $board;
		$query = prepare(sprintf("SELECT COUNT(*) as `count` FROM `posts_%s` WHERE `thread` = :thread", $board['uri']));
		$query->bindValue(':thread', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));
		
		$result = $query->fetch();
		return $result['count'];
	}
	
	function muteTime() {
		global $config;
		// Find number of mutes in the past X hours
		$query = prepare("SELECT COUNT(*) as `count` FROM `mutes` WHERE `time` >= :time AND `ip` = :ip");
		$query->bindValue(':time', time()-($config['robot_mute_hour']*3600), PDO::PARAM_INT);
		$query->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
		$query->execute() or error(db_error($query));
		
		$result = $query->fetch();
		if($result['count'] == 0) return 0;
		return pow($config['robot_mute_multiplier'], $result['count']);
	}
	
	function mute() {
		// Insert mute
		$query = prepare("INSERT INTO `mutes` VALUES (:ip, :time)");
		$query->bindValue(':time', time(), PDO::PARAM_INT);
		$query->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
		$query->execute() or error(db_error($query));
		
		return muteTime();
	}
	
	function checkMute() {
		global $config, $debug;
		
		if($config['cache']['enabled']) {
			// Cached mute?
			if(($mute = cache::get("mute_${_SERVER['REMOTE_ADDR']}")) && ($mutetime = cache::get("mutetime_${_SERVER['REMOTE_ADDR']}"))) {
				error(sprintf($config['error']['youaremuted'], $mute['time'] + $mutetime - time()));
			}
		}
		
		$mutetime = muteTime();
		if($mutetime > 0) {
			// Find last mute time
			$query = prepare("SELECT `time` FROM `mutes` WHERE `ip` = :ip ORDER BY `time` DESC LIMIT 1");
			$query->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
			$query->execute() or error(db_error($query));
			
			if(!$mute = $query->fetch()) {
				// What!? He's muted but he's not muted...
				return;
			}
			
			if($mute['time'] + $mutetime > time()) {
				if($config['cache']['enabled']) {
					cache::set("mute_${_SERVER['REMOTE_ADDR']}", $mute, $mute['time'] + $mutetime - time());
					cache::set("mutetime_${_SERVER['REMOTE_ADDR']}", $mutetime, $mute['time'] + $mutetime - time());
				}
				// Not expired yet
				error(sprintf($config['error']['youaremuted'], $mute['time'] + $mutetime - time()));
			} else {
				// Already expired	
				return;
			}
		}
	}
	
	function createHiddenInputs() {
		global $config;
		
		$inputs = Array();
		
		shuffle($config['spam']['hidden_input_names']);
		$hidden_input_names_x = 0;
		
		$input_count = rand($config['spam']['hidden_inputs_min'], $config['spam']['hidden_inputs_max']);
		for($x=0;$x<$input_count;$x++) {
			if(rand(0, 2) == 0 || $hidden_input_names_x < 0) {
				// Use an obscure name
				$name = strtolower(substr(base64_encode(sha1(rand(), true)), 0, rand(2, 20)));
				
			} else {
				// Use a pre-defined confusing name
				$name = $config['spam']['hidden_input_names'][$hidden_input_names_x++];
				if($hidden_input_names_x >= count($config['spam']['hidden_input_names']))
					$hidden_input_names_x = -1;
			}
			
			if(rand(0, 2) == 0) {
				// Value must be null
				$inputs[$name] = '';
			} elseif(rand(0, 4) == 0) {
				// Numeric value
				$inputs[$name] = rand(0, 100);
			} else {
				// Obscure value
				$inputs[$name] = substr(base64_encode(sha1(rand(), true) . sha1(rand(), true)), 0, rand(2, 54));
			}
		}
		
		$content = '';
		foreach($inputs as $name => $value) {
			$display_type = rand(0, 8);
			
			switch($display_type) {
				case 0:
					$content .= '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '" />';
					break;
				case 1:
					$content .= '<input style="display:none" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '" />';
					break;
				case 2:
					$content .= '<input type="hidden" value="' . htmlspecialchars($value) . '" name="' . htmlspecialchars($name) . '" />';
					break;
				case 3:
					$content .= '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '" />';
					break;
				case 4:
					$content .= '<span style="display:none"><input type="text" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '" /></span>';
					break;
				case 5:
					$content .= '<div style="display:none"><input type="text" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '" /></div>';
					break;
				case 6:
					if(!empty($value))
						$content .= '<textarea style="display:none" name="' . htmlspecialchars($name) . '">' . htmlspecialchars($value) . '</textarea>';
					else
						$content .= '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '" />';
					break;
				case 7:
					if(!empty($value))
						$content .= '<textarea name="' . htmlspecialchars($name) . '" style="display:none">' . htmlspecialchars($value) . '</textarea>';
					else
						$content .= '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '" />';
					break;
				case 8:
					$content .= '<div style="display:none"><textarea name="' . htmlspecialchars($name) . '" style="display:none">' . htmlspecialchars($value) . '</textarea></div>';
					break;
			}
		}
		
		// Create a hash to validate it after
		// This is the tricky part.
		
		// First, sort the keys in alphabetical order (A-Z)
		ksort($inputs);
		
		$hash = '';
		
		// Iterate through each input
		foreach($inputs as $name => $value) {
			$hash .= $name . '=' . $value;
		}
		
		// Add a salt to the hash
		$hash .= $config['cookies']['salt'];
		
		// Use SHA1 for the hash
		$hash = sha1($hash);
		
		// Append it to the HTML
		$content .= '<input type="hidden" name="hash" value="' . $hash . '" />';
		
		return $content;
	}
	
	function checkSpam() {
		global $config;
		
		if(!isset($_POST['hash']))
			return true;
		
		$hash = $_POST['hash'];
		
		// Reconsturct the $inputs array
		$inputs = Array();
		
		foreach($_POST as $name => $value) {
			if(in_array($name, $config['spam']['valid_inputs']))
				continue;
			
			$inputs[$name] = $value;
		}
		
		// Sort the inputs in alphabetical order (A-Z)
		ksort($inputs);
		
		$_hash = '';
		
		// Iterate through each input
		foreach($inputs as $name => $value) {
			$_hash .= $name . '=' . $value;
		}
		
		// Add a salt to the hash
		$_hash .= $config['cookies']['salt'];
		
		// Use SHA1 for the hash
		$_hash = sha1($_hash);
		
		return $hash != $_hash;
	}
	
	function buildIndex() {
		global $board, $config;
		
		$pages = getPages();

		$page = 1;
		while($page <= $config['max_pages'] && $content = index($page)) {
			$filename = $board['dir'] . ($page==1 ? $config['file_index'] : sprintf($config['file_page'], $page));
			if(file_exists($filename)) $md5 = md5_file($filename);
			
			$content['pages'] = $pages;
			$content['pages'][$page-1]['selected'] = true;
			$content['btn'] = getPageButtons($content['pages']);
			$content['hidden_inputs'] = createHiddenInputs();
			file_write($filename, Element('index.html', $content));
			
			if(isset($md5) && $md5 == md5_file($filename)) {
				break;
			}
			$page++;
		}
		if($page < $config['max_pages']) {
			for(;$page<=$config['max_pages'];$page++) {
				$filename = $board['dir'] . ($page==1 ? $config['file_index'] : sprintf($config['file_page'], $page));
				file_unlink($filename);
			}
		}
	}
	
	function buildJavascript() {
		global $config;
		
		$stylesheets = Array();
		foreach($config['stylesheets'] as $name => $uri) {
			$stylesheets[] = Array(
				'name' => addslashes($name),
				'uri' => addslashes((!empty($uri) ? $config['uri_stylesheets'] : '') . $uri));
		}
		
		file_write($config['file_script'], Element('main.js', Array(
			'config' => $config,
			'stylesheets' => $stylesheets
		)));
	}
	
	function checkDNSBL() {
		global $config;
		
		if(isIPv6())
			return; // No IPv6 support yet.
		
		if(!isset($_SERVER['REMOTE_ADDR']))
			return; // Fix your web server configuration
		
		if(in_array($_SERVER['REMOTE_ADDR'], $config['dnsbl_exceptions']))
			return;
		
		$ip = ReverseIPOctets($_SERVER['REMOTE_ADDR']);
		
		foreach($config['dnsbl'] as &$blacklist) {
			$lookup = $ip . '.' . $blacklist;
			$host = gethostbyname($lookup);
			if($host != $lookup) {
				// On NXDOMAIN (meaning it's not in the blacklist), gethostbyname() returns the host unchanged.
				if(preg_match('/^127\.0\.0\./', $host) && $host != '127.0.0.10')
					error(sprintf($config['error']['dnsbl'], $blacklist));
			}
		}
	}
	
	function isIPv6() {
		return strstr($_SERVER['REMOTE_ADDR'], ':') !== false;
	}
	
	function ReverseIPOctets($ip) {
		$ipoc = explode('.', $ip);
		return $ipoc[3] . '.' . $ipoc[2] . '.' . $ipoc[1] . '.' . $ipoc[0];
	}
	
	function wordfilters(&$body) {
		global $config;
		
		foreach($config['wordfilters'] as $filter) {
			if(isset($filter[2]) && $filter[2]) {
				$body = preg_replace($filter[0], $filter[1], $body);
			} else {
				$body = str_replace($filter[0], $filter[1], $body);
			}
		}
	}
	
	function quote($body, $quote=true) {
		global $config;
		
		$body = str_replace('<br/>', "\n", $body);
		
		$body = strip_tags($body);
		
		$body = preg_replace("/(^|\n)/", '$1&gt;', $body);
		
		$body .= "\n";
		
		if($config['minify_html'])
			$body = str_replace("\n", '&#010;', $body);
		
		return $body;
	}
	
	function markup_url($matches) {
		$strip_from_end = Array('.', ',', ')');
		
		$url = $matches[0];
		$after = '';
		
		$last = $url[strlen($url)-1];
		if(in_array($last, $strip_from_end)) {
			$after = $last;
			$url = substr($url, 0, -1);
		}
		
		return '<a target="_blank" rel="nofollow" href="' . $url . '">' . $url . '</a>' . $after;
	}
	
	function markup(&$body, $track_cites = false) {
		global $board, $config;
		
		$body = utf8tohtml($body);
		
		if($config['wiki_markup']) {
			$body = preg_replace("/(^|\n)==(.+?)==\n?/m", "<span class=\"heading\">$2</span>", $body);
			$body = preg_replace("/'''(.+?)'''/m", "<strong>$1</strong>", $body);
			$body = preg_replace("/''(.+?)''/m", "<em>$1</em>", $body);
			$body = preg_replace("/\*\*(.+?)\*\*/m", "<span class=\"spoiler\">$1</span>", $body);
		}
		
		if($config['markup_urls']) {
			$body = preg_replace_callback($config['url_regex'], 'markup_url', $body, -1, $num_links);
			if($num_links > $config['max_links'])
				error($config['error']['toomanylinks']);
		}
			
		if($config['auto_unicode']) {
			$body = str_replace('...', '&hellip;', $body);
			$body = str_replace('&lt;--', '&larr;', $body);
			$body = str_replace('--&gt;', '&rarr;', $body);
			
			// En and em- dashes are rendered exactly the same in
			// most monospace fonts (they look the same in code
			// editors).
			$body = str_replace('---', '&ndash;', $body); // em dash
			$body = str_replace('--', '&mdash;', $body); // en dash
		}
		
		// replace tabs with 8 spaces
		$body = str_replace("\t", '        ', $body);
		
		$tracked_cites = Array();
		
		// Cites
		if(isset($board) && preg_match_all('/(^|\s)&gt;&gt;(\d+?)([\s,.)?]|$)/', $body, $cites)) {			
			if(count($cites[0]) > $config['max_cites']) {
				error($config['error']['toomanycites']);
			}
			
			for($index=0;$index<count($cites[0]);$index++) {
				$cite = $cites[2][$index];
				$query = prepare(sprintf("SELECT `thread`,`id` FROM `posts_%s` WHERE `id` = :id LIMIT 1", $board['uri']));
				$query->bindValue(':id', $cite);
				$query->execute() or error(db_error($query));
				
				if($post = $query->fetch()) {
					$replacement = '<a onclick="highlightReply(\''.$cite.'\');" href="' .
						$config['root'] . $board['dir'] . $config['dir']['res'] . ($post['thread']?$post['thread']:$post['id']) . '.html#' . $cite . '">' .
							'&gt;&gt;' . $cite .
							'</a>';
					$body = str_replace($cites[0][$index], $cites[1][$index] . $replacement . $cites[3][$index], $body);
					
					if($track_cites && $config['track_cites'])
						$tracked_cites[] = Array($board['uri'], $post['id']);
				}
			}
		}
		
		// Cross-board linking
		if(preg_match_all('/(^|\s)&gt;&gt;&gt;\/(\w+?)\/(\d+)?([\s,.)?]|$)/', $body, $cites)) {
			if(count($cites[0]) > $config['max_cites']) {
				error($config['error']['toomanycross']);
			}
			
			for($index=0;$index<count($cites[0]);$index++) {
				$_board = $cites[2][$index];
				$cite = @$cites[3][$index];
				
				// Temporarily store board information because it will be overwritten
				$tmp_board = $board['uri'];
				
				// Check if the board exists, and load settings
				if(openBoard($_board)) {
					if($cite) {
						$query = prepare(sprintf("SELECT `thread`,`id` FROM `posts_%s` WHERE `id` = :id LIMIT 1", $board['uri']));
						$query->bindValue(':id', $cite);
						$query->execute() or error(db_error($query));
						
						if($post = $query->fetch()) {
							$replacement = '<a onclick="highlightReply(\''.$cite.'\');" href="' .
								$config['root'] . $board['dir'] . $config['dir']['res'] . ($post['thread']?$post['thread']:$post['id']) . '.html#' . $cite . '">' .
									'&gt;&gt;&gt;/' . $_board . '/' . $cite .
									'</a>';
							$body = str_replace($cites[0][$index], $cites[1][$index] . $replacement . $cites[4][$index], $body);
							
							if($track_cites && $config['track_cites'])
								$tracked_cites[] = Array($board['uri'], $post['id']);
						}
					} else {
						$replacement = '<a href="' .
							$config['root'] . $board['dir'] . $config['file_index'] . '">' .
								'&gt;&gt;&gt;/' . $_board . '/' .
								'</a>';
						$body = str_replace($cites[0][$index], $cites[1][$index] . $replacement . $cites[4][$index], $body);
					}
				}
				
				// Restore main board settings
				openBoard($tmp_board);
			}
		}
		

		$body = str_replace("\r", '', $body);
		
		$body = preg_replace("/(^|\n)([\s]+)?(&gt;)([^\n]+)?($|\n)/m", '$1$2<span class="quote">$3$4</span>$5', $body);
		
		if($config['strip_superfluous_returns'])
			$body = preg_replace('/\s+$/', '', $body);
		
		$body = preg_replace("/\n/", '<br/>', $body);
		
		return $tracked_cites;
	}

	function utf8tohtml($utf8) {
		return mb_encode_numericentity(htmlspecialchars($utf8, ENT_NOQUOTES, 'UTF-8'), Array(0xff, 0xffff, 0, 0xffff), 'UTF-8');
	}

	function buildThread($id, $return=false, $mod=false) {
		global $board, $config;
		$id = round($id);
		
		if($config['cache']['enabled'] && !$mod) {
			// Clear cache
			cache::delete("thread_index_{$board['uri']}_{$id}");
			cache::delete("thread_{$board['uri']}_{$id}");
		}
		
		$query = prepare(sprintf("SELECT * FROM `posts_%s` WHERE (`thread` IS NULL AND `id` = :id) OR `thread` = :id ORDER BY `thread`,`id`", $board['uri']));
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));
		
		while($post = $query->fetch()) {
			if(!isset($thread)) {
				$thread = new Thread($post['id'], $post['subject'], $post['email'], $post['name'], $post['trip'], $post['capcode'], $post['body'], $post['time'], $post['thumb'], $post['thumbwidth'], $post['thumbheight'], $post['file'], $post['filewidth'], $post['fileheight'], $post['filesize'], $post['filename'], $post['ip'], $post['sticky'], $post['locked'], $post['sage'], $post['embed'], $mod ? '?/' : $config['root'], $mod);
			} else {
				$thread->add(new Post($post['id'], $thread->id, $post['subject'], $post['email'], $post['name'], $post['trip'], $post['capcode'], $post['body'], $post['time'], $post['thumb'], $post['thumbwidth'], $post['thumbheight'], $post['file'], $post['filewidth'], $post['fileheight'], $post['filesize'], $post['filename'], $post['ip'], $post['embed'], $mod ? '?/' : $config['root'], $mod));
			}
		}
		
		// Check if any posts were found
		if(!isset($thread)) error($config['error']['nonexistant']);
		
		$body = Element('thread.html', Array(
			'board'=>$board, 
			'body'=>$thread->build(),
			'config' => $config,
			'id' => $id,
			'mod' => $mod,
			'boardlist' => createBoardlist($mod),
			'hidden_inputs' => $content['hidden_inputs'] = createHiddenInputs(),
			'return' => ($mod ? '?' . $board['url'] . $config['file_index'] : $config['root'] . $board['uri'] . '/' . $config['file_index'])
		));
		
		if($return)
			return $body;
		else
			file_write($board['dir'] . $config['dir']['res'] . sprintf($config['file_page'], $id), $body);
	}
	
	 function rrmdir($dir) {
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != "." && $object != "..") {
					if (filetype($dir."/".$object) == "dir")
						rrmdir($dir."/".$object);
					else
						file_unlink($dir."/".$object);
				}
			}
			reset($objects);
			rmdir($dir);
		}
	}
	
	function poster_id($ip, $thread) {
		global $config;
		
		// Confusing, hard to brute-force, but simple algorithm
		return substr(sha1(sha1($ip . $config['secure_trip_salt'] . $thread) . $config['secure_trip_salt']), 0, $config['poster_id_length']);
	}
 
	function generate_tripcode($name) {
		global $config;
		
		if(!preg_match('/^([^#]+)?(##|#)(.+)$/', $name, $match))
			return Array($name);
		
		$name = $match[1];
		$secure = $match[2] == '##';
		$trip = $match[3];
		
		// convert to SHIT_JIS encoding
		$trip = mb_convert_encoding($trip, 'Shift_JIS', 'UTF-8');
		
		// generate salt
		$salt = substr($trip . 'H..', 1, 2);
		$salt = preg_replace('/[^\.-z]/', '.', $salt);
		$salt = strtr($salt, ':;<=>?@[\]^_`', 'ABCDEFGabcdef');
		
		if($secure) {
			if(isset($config['custom_tripcode']["##{$trip}"]))
				$trip = $config['custom_tripcode']["##{$trip}"];
			else
				$trip = '!!' . substr(crypt($trip, $config['secure_trip_salt']), -10);
		} else {
			if(isset($config['custom_tripcode']["#{$trip}"]))
				$trip = $config['custom_tripcode']["#{$trip}"];
			else
				$trip = '!' . substr(crypt($trip, $salt), -10);
		}
		
		return Array($name, $trip);
	}
		
	// Highest common factor
	function hcf($a, $b){
		$gcd = 1;
		if ($a>$b) {
			$a = $a+$b;
			$b = $a-$b;
			$a = $a-$b;
		}
		if ($b==(round($b/$a))*$a) 
			$gcd=$a;
		else {
			for($i=round($a/2);$i;$i--) {
				if ($a == round($a/$i)*$i && $b == round($b/$i)*$i) {
					$gcd = $i;
					$i = false;
				}
			}
		}
		return $gcd;
	}

	function fraction($numerator, $denominator, $sep) {
		$gcf = hcf($numerator, $denominator);
		$numerator = $numerator / $gcf;
		$denominator = $denominator / $gcf;
		
		return "{$numerator}{$sep}{$denominator}";
	}

	
	
	function getPostByHash($hash) {
		global $board;
		$query = prepare(sprintf("SELECT `id`,`thread` FROM `posts_%s` WHERE `filehash` = :hash", $board['uri']));
		$query->bindValue(':hash', $hash, PDO::PARAM_STR);
		$query->execute() or error(db_error($query));
		
		if($post = $query->fetch()) {
			return $post;
		}
		
		return false;
	}
	
	function undoImage($post) {
		if($post['has_file']) {
			if(isset($post['thumb']))
				file_unlink($post['file']);
			if(isset($post['thumb']))
				file_unlink($post['thumb']);
		}
	}
	
	function rDNS($ip_addr) {
		global $config;
		
		if($config['cache']['enabled'] && ($host = cache::get('rdns_' . $ip_addr))) {
			return $host;
		}
		
		if(!$config['dns_system']) {
			$host = gethostbyaddr($ip_addr);
		} else {
			$resp = shell_exec('host -W 1 ' . $ip_addr);
			if(preg_match('/domain name pointer ([^\s]+)$/', $resp, $m))
				$host = $m[1];
			else
				$host = $ip_addr;
		}
		
		if($config['cache']['enabled'])
			cache::set('rdns_' . $ip_addr, $host, 3600);
		
		return $host;
	}
	
?>
