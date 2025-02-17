<?php
	require 'info.php';
	
	function recentposts_build($action, $settings) {
		// Possible values for $action:
		//	- all (rebuild everything, initialization)
		//	- news (news has been updated)
		//	- boards (board list changed)
		//	- post (a post has been made)
		
		$b = new RecentPosts();
		$b->build($action, $settings);
	}
	
	// Wrap functions in a class so they don't interfere with normal Tinyboard operations
	class RecentPosts {
		public function build($action, $settings) {
			global $config, $_theme;
			
			if($action == 'all') {
				copy($config['dir']['themes'] . '/' . $_theme . '/recent.css', $config['dir']['home'] . 'recent.css');
			}
			
			$this->excluded = explode(' ', $settings['exclude']);
			
			if($action == 'all' || $action == 'post')
			//	file_put_contents($config['dir']['home'] . $config['file_index'], $this->homepage($settings));
				file_write($config['dir']['home'] . 'recent.html', $this->homepage($settings));
		}
		
		// Build news page
		public function homepage($settings) {
			global $config, $board;
			
			// HTML5
			$body = '<!DOCTYPE html><html>'
			. '<head>'
				. '<link rel="stylesheet" media="screen" href="' . $config['url_stylesheet'] . '"/>'
				. '<link rel="stylesheet" media="screen" href="' . $config['root'] . 'recent.css"/>'
				. '<title>' . $settings['title'] . '</title>'
			. '</head><body>';
			
			$boardlist = createBoardlist();
			$body .= '<div class="boardlist">' . $boardlist['top'] . '</div>';
			
			$body .= '<h1>' . $settings['title'] . '</h1>';
			
			
			$boards = listBoards();
			
			// Wrap
			$body .= '<div class="box-wrap">';
			
			// Recent images
			$body .= '<div class="box left"><h2>Recent Images</h2><ul>';
				$query = '';
				foreach($boards as &$_board) {
					if(in_array($_board['uri'], $this->excluded))
						continue;
					$query .= sprintf("SELECT *, '%s' AS `board` FROM `posts_%s` WHERE `file` IS NOT NULL UNION ALL ", $_board['uri'], $_board['uri']);
				}
				$query = preg_replace('/UNION ALL $/', 'ORDER BY `time` DESC LIMIT 3', $query);
				$query = query($query) or error(db_error());
				
				while($post = $query->fetch()) {
					openBoard($post['board']);
					
					$body .= '<li><a href="' . 
						$config['root'] . $board['dir'] . $config['dir']['res'] . ($post['thread']?$post['thread']:$post['id']) . '.html#' . $post['id'] .
					'"><img src="' . $config['uri_thumb'] . $post['thumb'] . '" style="width:' . $post['thumbwidth'] . 'px;height:' . $post['thumbheight'] . 'px;" /></a></li>';
				}
			$body .= '</ul></div>';
			
			// Latest posts
			$body .= '<div class="box right"><h2>Latest Posts</h2><ul>';
				$query = '';
				foreach($boards as &$_board) {
					if(in_array($_board['uri'], $this->excluded))
						continue;
					$query .= sprintf("SELECT *, '%s' AS `board` FROM `posts_%s` UNION ALL ", $_board['uri'], $_board['uri']);
				}
				$query = preg_replace('/UNION ALL $/', 'ORDER BY `time` DESC LIMIT 30', $query);
				$query = query($query) or error(db_error());
				
				while($post = $query->fetch()) {
					openBoard($post['board']);
					
					$body .= '<li><strong>' . $board['name'] . '</strong>: <a href="' . 
						$config['root'] . $board['dir'] . $config['dir']['res'] . ($post['thread']?$post['thread']:$post['id']) . '.html#' . $post['id'] .
					'">' . pm_snippet($post['body'], 30) . '</a></li>';
				}
				
			$body .= '</ul></div>';
			
			
			// Stats
			$body .= '<div class="box right"><h2>Stats</h2><ul>';
			
				// Total posts
				$query = 'SELECT SUM(`top`) AS `count` FROM (';
				foreach($boards as &$_board) {
					if(in_array($_board['uri'], $this->excluded))
						continue;
					$query .= sprintf("SELECT MAX(`id`) AS `top` FROM `posts_%s` UNION ALL ", $_board['uri']);
				}
				$query = preg_replace('/UNION ALL $/', ') AS `posts_all`', $query);
				$query = query($query) or error(db_error());
				$res = $query->fetch();
				$body .= '<li>Total posts: ' . number_format($res['count']) . '</li>';
				
				// Unique IPs
				$query = 'SELECT COUNT(DISTINCT(`ip`)) AS `count` FROM (';
				foreach($boards as &$_board) {
					if(in_array($_board['uri'], $this->excluded))
						continue;
					$query .= sprintf("SELECT `ip` FROM `posts_%s` UNION ALL ", $_board['uri']);
				}
				$query = preg_replace('/UNION ALL $/', ') AS `posts_all`', $query);
				$query = query($query) or error(db_error());
				$res = $query->fetch();
				$body .= '<li>Unique posters: ' . number_format($res['count']) . '</li>';
				
				// Active content
				$query = 'SELECT SUM(`filesize`) AS `count` FROM (';
				foreach($boards as &$_board) {
					if(in_array($_board['uri'], $this->excluded))
						continue;
					$query .= sprintf("SELECT `filesize` FROM `posts_%s` UNION ALL ", $_board['uri']);
				}
				$query = preg_replace('/UNION ALL $/', ') AS `posts_all`', $query);
				$query = query($query) or error(db_error());
				$res = $query->fetch();
				$body .= '<li>Active content: ' . format_bytes($res['count']) . '</li>';
				
			$body .= '</ul></div>';
			
			// End wrap
			$body .= '</div>';
			
			// Finish page
			$body .= '<hr/><p class="unimportant" style="margin-top:20px;text-align:center;">Powered by <a href="http://tinyboard.org/">Tinyboard</a></body></html>';
			
			return $body;
		}
	};
	
?>
