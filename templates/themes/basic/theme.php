<?php
	require 'info.php';
	
	function basic_build($action, $settings) {
		// Possible values for $action:
		//	- all (rebuild everything, initialization)
		//	- news (news has been updated)
		//	- boards (board list changed)
		
		Basic::build($action, $settings);
	}
	
	// Wrap functions in a class so they don't interfere with normal Tinyboard operations
	class Basic {
		public static function build($action, $settings) {
			global $config;
			
			if($action == 'all' || $action == 'news')
				file_write($config['dir']['home'] . $config['file_index'], Basic::homepage($settings));
		}
		
		// Build news page
		public static function homepage($settings) {
			global $config;
			
			// HTML5
			$body = '<!DOCTYPE html><html>'
			. '<head>'
				. '<link rel="stylesheet" media="screen" href="' . $config['url_stylesheet'] . '"/>'
				. '<title>' . $settings['title'] . '</title>'
			. '</head><body>';
			
			$boardlist = createBoardlist();
			$body .= '<div class="boardlist">' . $boardlist['top'] . '</div>';
			
			$body .= '<h1>' . $settings['title'] . '</h1>'
				. '<div class="title">' . ($settings['subtitle'] ? utf8tohtml($settings['subtitle']) : '') . '</div>';
			
			$body .= '<div class="ban">';
			
			$query = query("SELECT * FROM `news` ORDER BY `time` DESC") or error(db_error());
			if($query->rowCount() == 0) {
				$body .= '<p style="text-align:center" class="unimportant">(No news to show.)</p>';
			} else {
				// List news
				while($news = $query->fetch()) {
					$body .= '<h2 id="' . $news['id'] . '">' .
						($news['subject'] ?
							$news['subject']
						:
							'<em>no subject</em>'
						) .
					'<span class="unimportant"> &mdash; by ' .
						$news['name'] .
					' at ' .
						strftime($config['post_date'], $news['time']) .
					'</span></h2><p>' . $news['body'] . '</p>';
				}
			}
			
			$body .= '</div>';
			
			// Finish page
			$body .= '<hr/><p class="unimportant" style="margin-top:20px;text-align:center;">Powered by <a href="http://tinyboard.org/">Tinyboard</a></body></html>';
			
			return $body;
		}
	};
	
?>
