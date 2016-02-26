<?php
//======================================================================\\
// phpDolphin - Social Network Platform			                        \\
// Copyright © Pricop Alexandru - Mihai. All rights reserved.			\\
//----------------------------------------------------------------------\\
// http://www.phpDolphin.com/               	http://www.pricop.info/ \\
//======================================================================\\
function getSettings() {
	$querySettings = "SELECT * from `settings`";
	return $querySettings;
}
function menu($user) {
	global $TMPL, $LNG, $CONF, $db, $settings;

	$admin_url = ((isset($_SESSION['usernameAdmin']) && isset($_SESSION['passwordAdmin'])) ? '<a href="'.$CONF['url'].'/index.php?a=admin" rel="loadpage"><div class="menu_btn" id="admin_btn" title="'.$LNG['admin_panel'].'"><img src="'.$CONF['url'].'/'.$CONF['theme_url'].'/images/icons/admin.png"></div></a>' : '');
	
	if($user !== false) {
		$skin = new skin('shared/menu'); $menu = '';
		
		$TMPL_old = $TMPL; $TMPL = array();

		$TMPL['realname'] = realName($user['username'], $user['first_name'], $user['last_name']);
		$TMPL['avatar'] = $user['image'];
		$TMPL['username'] = $user['username'];
		$TMPL['url'] = $CONF['url'];
		$TMPL['theme_url'] = $CONF['theme_url'];
		$TMPL['intervaln'] = $settings['intervaln'];
		$TMPL['intervalm'] = $settings['intervalm'];
		$TMPL['chatr'] = ($settings['chatr'] * 1000);
		$TMPL['smiles'] = $settings['smiles'] ? chatSmiles() : '';
		
	   /**
		* Array Map
		* array => { url, name, dynamic load, class type}
		*/
		$links = array(	array('profile&u='.$user['username'], realName($user['username'], $user['first_name'], $user['last_name']), 1, 0),
						array('feed', $LNG['title_feed'], 1, 0),
						array('notifications', $LNG['title_notifications'], 1, 0),
						array('settings', $LNG['title_settings'], 1, 0),
						array('feed&logout=1&token_id='.$_SESSION['token_id'], $LNG['log_out'], 0, 0));
		
		foreach($links as $element => $value) {
			if($value) {
				$TMPL['links'] .= $divider.'<a href="'.$CONF['url'].'/index.php?a='.$value[0].'" '.($value[2] ? ' rel="loadpage"' : '').'><div class="menu-dd-row'.(($value[3] == 1) ? ' menu-dd-extra' : '').(($value[3] == 2) ? ' menu-dd-mobile' : '').'">'.$value[1].'</div></a>';
				$divider = '<div class="menu-divider '.(($value[3] == 2) ? ' menu-dd-mobile' : '').'"></div>';
			}
		}
		
		$TMPL['admin_url'] = $admin_url;
		$TMPL['audio_container'] = audioContainer('Notification', $user['sound_new_notification']).audioContainer('Chat', $user['sound_new_chat']);
		
		$menu = $skin->make();
		
		$TMPL = $TMPL_old; unset($TMPL_old);
		return $menu;
	} else {
		// Else show the LogIn Register button
		return '<a href="'.$CONF['url'].'/index.php?a=welcome" rel="loadpage" title="'.$LNG['connect'].'"><div class="topbar-button">'.$LNG['connect'].'</div></a>'.$admin_url;
	}
}
function chatSmiles() {
	global $CONF;
	$smiles = smiles();
	foreach($smiles as $code => $smile) {
		$output .= '<a onclick="addSmile(\''.addslashes($code).'\')" title="'.$code.'"><img src="'.$CONF['url'].'/'.$CONF['theme_url'].'/images/icons/emoticons/'.$smile.'" height="14" width="14"></a>';
	}
	return $output;
}
function notificationBox($type, $message, $extra = null) {
	// Extra 1: Add the -modal class name
	if($extra == 1) {
		$extra = ' notification-box-extra';
	}
	return '<div class="notification-box'.$extra.' notification-box-'.$type.'">
			<p>'.$message.'</p>
			<div class="notification-close notification-close-'.$type.'"></div>
			</div>';
}
class register {
	public $db; 					// Database Property
	public $url; 					// Installation URL Property
	public $username;				// The inserted username
	public $password;				// The inserted password
	public $email;					// The inserted email
	public $captcha;				// The inserted captcha
	public $captcha_on;				// Store the Admin Captcha settings
	public $message_privacy;		// Store the Admin User's Message Privacy settings (Predefined, changeable)
	public $verified;				// Store the Admin Verified settings
	public $like_notification;		// Store the Admin Like Notification Settings  (Predefined, changeable)
	public $comment_notification;	// Store the Admin Comment Notification Settings (Predefined, changeable)
	public $shared_notification;	// Store the Admin Shared Message Notification Settings  (Predefined, changeable)
	public $chat_notification;		// Store the Admin Chat Notification Settings  (Predefined, changeable)
	public $friend_notification;	// Store the Admin Friend Notification Settings  (Predefined, changeable)
	public $email_like;				// The general e-mail like setting [if allowed, it will turn on emails on likes]
	public $email_comment;			// The general e-mail like setting [if allowed, it will turn on emails on comments]
	public $email_new_friend;		// The general e-mail new friend setting [if allowed, it will turn on emails on new friendships]
	public $sound_new_notification;	// The general sound settings for general notifications (top bar)
	public $sound_new_chat;			// The general sound settings for new chat messages (messages page)
	
	function facebook() {
		if($this->fbapp) {
			$getToken = $this->getFbToken($this->fbappid, $this->fbappsecret, $this->url.'/index.php?facebook=true', $this->fbcode);
			$user = $this->parseFbInfo($getToken['access_token']);

			if($getToken == null || $_SESSION['state'] == null || ($_SESSION['state'] != $this->fbstate) || empty($user->email)) {
				header("Location: ".$this->url);
			}
			if(!empty($user->email)) {
				$this->email = $user->email;
				
				$this->first_name = $user->first_name;
				$this->last_name = $user->last_name;
				$checkEmail = $this->verify_if_email_exists(1);

				// If user already exist
				if($checkEmail) {
					// Set sessions and log-in
					$_SESSION['username'] = $checkEmail['username'];
					$_SESSION['password'] = $checkEmail['password'];

					// Redirect user
					header("Location: ".$this->url);
				} else {
					$this->profile_image = $this->parseFbPicture($getToken['access_token']);
					$this->generateUsername();
					$this->password = $this->generatePassword(8);
					$this->query();
					
					$_SESSION['username'] = $this->username;
					$_SESSION['password'] = md5($this->password);
					
					return 1;
				}
			}
		}
	}
	
	function generateUsername($type = null) {
		// If type is set, generate a random username
		if($type) {
			$this->username = $this->parseUsername().rand(0, 999);
		} else {
			$this->username = $this->parseUsername();
		}
		
		// Replace the '.' sign with '_' (allows @user_mention)
		$this->username = str_replace('.', '_', $this->username);
		
		// Check if the username exists
		$checkUser = $this->verify_if_user_exist();
		
		if($checkUser) {
			$this->generateUsername(1);
		}
	}
	
	function parseUsername() {
		if(ctype_alnum($this->first_name) && ctype_alnum($this->last_name)) {
			return $this->username = $this->first_name.'.'.$this->last_name;
		} elseif(ctype_alnum($this->first_name)) {
			return $this->first_name;
		} elseif(ctype_alnum($this->last_name)) {
			return $this->last_name;
		} else {
			// Parse email address
			$email = explode('@', $this->email);
			$email = preg_replace("/[^a-z0-9]+/i", "", $email[0]);
			if(ctype_alnum($email)) {
				return $email;
			} else {
				return rand(0, 9999);
			}
		}
	}
	
	function generatePassword($length) {
		// Allowed characters
		$chars = str_split("abcdefghijklmnopqrstuvwxyz0123456789");
		
		// Generate password
		for($i = 1; $i <= $length; $i++) {
			// Get a random character
			$n = array_rand($chars, 1);
			
			// Store random char
			$password .= $chars[$n];
		}
		return $password;
	}
	
	function getFbToken($app_id, $app_secret, $redirect_url, $code) {
		// Build the token URL
		$url = 'https://graph.facebook.com/oauth/access_token?client_id='.$app_id.'&redirect_uri='.urlencode($redirect_url).'&client_secret='.$app_secret.'&code='.$code;
		
		// Get the file
		$response = fetch($url);
		
		// Parse the response
		parse_str($response, $params);

		// Return parameters
		return $params;
	}

	function parseFbInfo($access_token) {
		// Build the Graph URL
		$url = "https://graph.facebook.com/me?fields=id,email,first_name,gender,last_name,link,locale,name,timezone,updated_time,verified&access_token=".$access_token;
		
		// Get the file
		$user = json_decode(fetch($url));
		
		// Return user
		if($user != null && isset($user->name)) {
			return $user;
		}
		return null;
	}
	
	function parseFbPicture($access_token) {
		// Build the Graph URL
		$url = "https://graph.facebook.com/me/picture?width=500&height=500&access_token=".$access_token;
		
		// Get the image
		$image = fetch($url);
		
		// Generate the file name
		$file_name = mt_rand().'_'.mt_rand().'_'.mt_rand().'.jpg';
		$file_path = __DIR__ .'/../uploads/avatars/';
		
		// Create the file
		$fp = fopen($file_path.$file_name, 'wb');
		
		// If the file can't be written
		if(!file_exists($file_path.$file_name)) {
			// Return the file name
			return false;
		}
		
		// Write the image
		fwrite($fp, $image);
		
		// Close
		fclose($fp);
		
		// Return the filename
		return $file_name;
	}
	
	function process() {
		global $LNG;

		$arr = $this->validate_values(); // Must be stored in a variable before executing an empty condition
		if(empty($arr)) { // If there is no error message then execute the query;
			$this->query();
			
			// Set a session and log-in the user
			$_SESSION['username'] = $this->username;
			$_SESSION['password'] = md5($this->password);
			
			//Redirect the user to his personal profile
			//header("Location: ".$this->url."/something");
			
			// Return (int) 1 if everything was validated
			$x = 1;
			
			// return $LNG['user_success'];
		} else { // If there is an error message
			foreach($arr as $err) {
				return notificationBox('error', $LNG["$err"], 1); // Return the error value for translation file
			}
		}
		return $x;		
	}
	
	function verify_if_user_exist() {
		$query = sprintf("SELECT `username` FROM `users` WHERE `username` = '%s'", $this->db->real_escape_string(strtolower($this->username)));
		$result = $this->db->query($query);
		
		return ($result->num_rows == 0) ? 0 : 1;
	}
	
	function verify_accounts_per_ip() {
		if($this->accounts_per_ip) {
			$query = $this->db->query(sprintf("SELECT COUNT(`ip`) FROM `users` WHERE `ip` = '%s'", $this->db->real_escape_string(getUserIP())));

			$result = $query->fetch_row();
			if($result[0] < $this->accounts_per_ip) {
				return true;
			} else {
				return false;
			}
		} else {
			return true;
		}
	}
	
	function verify_if_email_exists($type = null) {
		// Type 0: Normal check
		// Type 1: Facebook check & return type
		if($type) {
			$query = sprintf("SELECT `username`, `password` FROM `users` WHERE `email` = '%s'", $this->db->real_escape_string(strtolower($this->email)));
		} else {
			$query = sprintf("SELECT `email` FROM `users` WHERE `email` = '%s'", $this->db->real_escape_string(strtolower($this->email)));
		}
		$result = $this->db->query($query);
		
		if($type) {
			return ($result->num_rows == 0) ? 0 : $result->fetch_assoc();
		} else {
			return ($result->num_rows == 0) ? 0 : 1;
		}
	}
	
	function verify_captcha() {
		if($this->captcha_on) {
			if($this->captcha == "{$_SESSION['captcha']}" && !empty($this->captcha)) {
				return true;
			} else {
				return false;
			}
		} else {
			return true;
		}
	}
	
	function validate_values() {
		// Create the array which contains the Language variable
		$error = array();
		
		// Define the Language variable for each type of error
		if($this->verify_accounts_per_ip() == false) {
			$error[] = 'user_limit';
		}
		if($this->verify_if_user_exist() !== 0) {
			$error[] .= 'user_exists';
		}
		if($this->verify_if_email_exists() !== 0) {
			$error[] .= 'email_exists';
		}
		if(empty($this->username) && empty($this->password) && empty($email)) {
			$error[] .= 'all_fields';
		}
		if(strlen($this->password) < 6) {
			$error[] .= 'password_too_short';
		}
		if(!ctype_alnum($this->username)) {
			$error[] .= 'user_alnum';
		}
		if(strlen($this->username) <= 2 || strlen($this->username) >= 33) {
			$error[] .= 'user_too_short';
		}
		if(!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
			$error[] .= 'invalid_email';
		}
		if($this->verify_captcha() == false) {
			$error[] .= 'invalid_captcha';
		}
		
		return $error;
	}
	
	function query() {
		$query = sprintf("INSERT INTO `users` (`username`, `password`, `first_name`, `last_name`, `email`, `date`, `image`, `privacy`, `cover`, `verified`, `online`, `ip`, `notificationl`, `notificationc`, `notifications`, `notificationd`, `notificationf`, `notificationg`, `email_comment`, `email_like`, `email_new_friend`, `email_group_invite`, `sound_new_notification`, `sound_new_chat`) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', 'default.png', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');", $this->db->real_escape_string(strtolower($this->username)), md5($this->db->real_escape_string($this->password)), $this->db->real_escape_string($this->first_name), $this->db->real_escape_string($this->last_name), $this->db->real_escape_string($this->email), date("Y-m-d H:i:s"), ($this->profile_image ? $this->profile_image : 'default.png'), $this->message_privacy, $this->verified, time(), $this->db->real_escape_string(getUserIp()), $this->like_notification, $this->comment_notification, $this->shared_notification, $this->chat_notification, $this->friend_notification, $this->group_notification, $this->email_comment, $this->email_like, $this->email_new_friend, $this->email_group_invite, $this->sound_new_notification, $this->sound_new_chat);
		$this->db->query($query);
		// return ($this->db->query($query)) ? 0 : 1;
	}
}
class logIn {
	public $db; 		// Database Property
	public $url; 		// Installation URL Property
	public $username;	// Username Property
	public $password;	// Password Property
	public $remember;	// Option to remember the usr / pwd (_COOKIE) Property
	
	function in() {
		global $LNG;
		
		// If an user is found
		if($this->queryLogIn() == 1) {
			if($this->remember == 1) { // If checkbox, then set cookie
				setcookie("username", $this->username, time() + 30 * 24 * 60 * 60); // Expire in one month
				setcookie("password", md5($this->password), time() + 30 * 24 * 60 * 60); // Expire in one month
			} else { // Else set session
				$_SESSION['username'] = $this->username;
				$_SESSION['password'] = md5($this->password);
			}
			
			// Redirect the user to his personal profile
			header("Location: ".$this->url."/index.php?a=feed");
		} else {
			// If wrong credentials are entered, unset everything
			$this->logOut();
			
			return $LNG['invalid_user_pw'];
		}
	}
	
	function queryLogIn() {
		// If the username input string is an e-mail, switch the query
		if(filter_var($this->db->real_escape_string($this->username), FILTER_VALIDATE_EMAIL)) {
			$query = sprintf("SELECT * FROM `users` WHERE `email` = '%s' AND `password` = '%s' AND `suspended` = 0", $this->db->real_escape_string($this->username), md5($this->db->real_escape_string($this->password)));
		} else {
			$query = sprintf("SELECT * FROM `users` WHERE `username` = '%s' AND `password` = '%s' AND `suspended` = 0", $this->db->real_escape_string($this->username), md5($this->db->real_escape_string($this->password)));
		}
		$result = $this->db->query($query);
		
		return ($result->num_rows == 0) ? 0 : 1;
	}
	
	function logOut() {
		unset($_SESSION['username']);
		unset($_SESSION['password']);
		unset($_SESSION['token_id']);
		setcookie("username", '', 1);
		setcookie("password", '', 1);
	}
}

class loggedIn {
	public $db; 		// Database Property
	public $url; 		// Installation URL Property
	public $username;	// Username Property
	public $password;	// Password Property
	
	function verify() {
		// Set the query result into $query variable;
		$query = $this->query();		
		
		if(!is_int($query)) {
			// If the $query variable is not 0 (int)
			// Fetch associative array into $result variable
			$result = $query->fetch_assoc();
			return $result;
		}
	}
	
	function query() {
		// If the username input string is an e-mail, switch the query
		if(filter_var($this->db->real_escape_string($this->username), FILTER_VALIDATE_EMAIL)) {
			$query = sprintf("SELECT * FROM `users` WHERE `email` = '%s' AND `password` = '%s' AND `suspended` = 0", $this->db->real_escape_string($this->username), $this->db->real_escape_string($this->password));
		} else {
			$query = sprintf("SELECT * FROM `users` WHERE `username` = '%s' AND `password` = '%s' AND `suspended` = 0", $this->db->real_escape_string($this->username), $this->db->real_escape_string($this->password));
		}
		$result = $this->db->query($query);
		return ($result->num_rows == 0) ? 0 : $result;
	}

	function logOut() {
		unset($_SESSION['username']);
		unset($_SESSION['password']);
		unset($_SESSION['token_id']);
		setcookie("username", '', 1);
		setcookie("password", '', 1);
	}
}

class logInAdmin {
	public $db; 		// Database Property
	public $url; 		// Installation URL Property
	public $username;	// Username Property
	public $password;	// Password Property
	
	function in() {
		global $LNG;
		
		// If an user is found
		if($this->queryLogIn() == 1) {
			// Set session
			$_SESSION['usernameAdmin'] = $this->username;
			$_SESSION['passwordAdmin'] = md5($this->password);
			
			// Redirect the user to his personal profile
			// header("Location: ".$this->url."/index.php?a=feed");
		} else {
			// If wrong credentials are entered, unset everything
			$this->logOut();
			
			return notificationBox('error', $LNG['invalid_user_pw']);
		}
	}
	
	function queryLogIn() {
		$query = sprintf("SELECT * FROM `admin` WHERE `username` = '%s' AND `password` = '%s'", $this->db->real_escape_string($this->username), md5($this->db->real_escape_string($this->password)));
		$result = $this->db->query($query);
		
		return ($result->num_rows == 0) ? 0 : 1;
	}
	
	function logOut() {
		unset($_SESSION['usernameAdmin']);
		unset($_SESSION['passwordAdmin']);
		unset($_SESSION['token_id']);
	}
}

class loggedInAdmin {
	public $db;			// Database Property
	public $url;		// Installation URL Property
	public $username; 	// Username Property
	public $password; 	// Password Property
	
	function verify() {
		// Set the query result into $query variable;
		$query = $this->query();		
		if(!is_int($query)) {
			// If the $query variable is not 0 (int)
			// Fetch associative array into $result variable
			$result = $query->fetch_assoc();
			return $result;
		}
	}
	
	function query() {
		$query = sprintf("SELECT * FROM `admin` WHERE `username` = '%s' AND `password` = '%s'", $this->db->real_escape_string($this->username), $this->db->real_escape_string($this->password));

		$result = $this->db->query($query);
		return ($result->num_rows == 0) ? 0 : $result;
	}

	function logOut() {
		unset($_SESSION['usernameAdmin']);
		unset($_SESSION['passwordAdmin']);
		unset($_SESSION['token_id']);
	}
}

class updateSettings {
	public $db;		// Database Property
	public $url;	// Installation URL Property
	
	function validate_password($password) {
		$query = $this->db->query(sprintf("SELECT `password` FROM `admin` WHERE `username` = '%s' AND `password` = '%s'", $this->db->real_escape_string($_SESSION['usernameAdmin']), $this->db->real_escape_string(md5($password))));
		return $query->num_rows ? 1 : 0;
	}

	function query_array($table, $data) {
		// Verify if the user has a valid token
		if($data['token_id'] == $_SESSION['token_id']) {
			unset($data['token_id']);
			// Get the columns of the query-ed table
			$available = $this->getColumns($table);
			
			if($table == 'admin') {
				if(isset($data['password']) && !isset($data['current_password']) || isset($data['current_password']) && !$this->validate_password($data['current_password'])) {
					return 2;
				}
				
				if(isset($data['password']) && strlen($data['password']) < 6) {
					return 4;
				}
				
				if(isset($data['password']) && $data['password'] !== $data['repeat_password']) {
					return 3;
				}
				
				unset($data['repeat_password'], $data['current_password']);
			}
			
			foreach ($data as $key => $value) {
				// Check if all arrays introduced are available table fields
				if(!array_key_exists($key, $available)) {	
					$x = 1;
					return 0;
				}
			}
			
			// If all array keys are valid database columns
			if($x !== 1) {
				foreach ($data as $column => $value) {
					$columns[] = sprintf("`%s` = '%s'", $column, $this->db->real_escape_string($value));
				}
				$column_list = implode(',', $columns);
				
				// Prepare the database for specific page
				if($table == 'admin') {
					// Prepare the statement
					$stmt = $this->db->prepare("UPDATE `$table` SET `password` = md5(?) WHERE `username` = ?");
					$stmt->bind_param("ss", $data['password'], $_SESSION['usernameAdmin']);
					$_SESSION['passwordAdmin'] = md5($data['password']);
				} else {
					// Prepare the statement
					$stmt = $this->db->prepare("UPDATE `$table` SET $column_list");		
				}

				// Execute the statement
				$stmt->execute();
				
				// Save the affected rows
				$affected = $stmt->affected_rows;
				
				// Close the statement
				$stmt->close();

				// If there was anything affected return 1
				return ($affected) ? 1 : 0;
			}
		} else {
			return 0;
		}
	}
	
	function getColumns($table) {
		if($table == 'admin') {
			$query = $this->db->query("SHOW columns FROM `$table` WHERE Field NOT IN ('id', 'username')");
		} else {
			$query = $this->db->query("SHOW columns FROM `$table`");
		}
		// Define an array to store the results
		$columns = array();
		
		// Fetch the results set
		while ($row = $query->fetch_array()) {
			// Store the result into array
			$columns[] = $row[0];
		}
		
		// Return the array;
		return array_flip($columns);
	}
	
	function getThemes() {
		global $CONF, $LNG;
		if($handle = opendir('./'.$CONF['theme_path'].'/')) {
			
			$allowedThemes = array();
			// This is the correct way to loop over the directory.
			while(false !== ($theme = readdir($handle))) {
				// Exclude ., .., and check whether the info.php file of the theme exist
				if($theme != '.' && $theme != '..' && file_exists('./'.$CONF['theme_path'].'/'.$theme.'/info.php')) {
					$allowedThemes[] = $theme;
					include('./'.$CONF['theme_path'].'/'.$theme.'/info.php');
					
					if($CONF['theme_name'] == $theme) {
						$state = '<div class="users-button button-active"><a>'.$LNG['active'].'</a></div>';
					} else {
						$state = '<div class="users-button button-normal"><a href="'.$CONF['url'].'/index.php?a=admin&b=themes&theme='.$theme.'&token_id='.$_SESSION['token_id'].'">'.$LNG['activate'].'</a></div>';
					}
					
					if(file_exists('./'.$CONF['theme_path'].'/'.$theme.'/icon.png')) {
						$image = '<img src="'.$CONF['url'].'/'.$CONF['theme_path'].'/'.$theme.'/icon.png">';
					}  else {
						$image = '';
					}
					$output .= '<div class="users-container">
						<div class="message-content">
							<div class="message-inner">
								'.$state.'
								<div class="message-avatar">
									<a href="'.$url.'" target="_blank" title="'.$LNG['auhtor_title'].'">
										'.$image.'
									</a>
								</div>
								<div class="message-top">
									<div class="message-author" rel="loadpage">
										<a href="'.$url.'" target="_blank" title="'.$LNG['auhtor_title'].'">'.$name.'</a> '.$version.'
									</div>
									<div class="message-time">
										'.$LNG['by'].': <a href="'.$url.'" target="_blank" title="'.$LNG['auhtor_title'].'">'.$author.'</a>
									</div>
								</div>
							</div>
						</div>
					</div>';
				}
			}

			closedir($handle);
			return array($output, $allowedThemes);
		}
	}
	
	function getPlugins() {
		global $CONF, $LNG;
		$CONF['plugin_path'] = 'plugins';
		
		$listplugins = loadPlugins($this->db);

		foreach($listplugins as $currplugin) {
			$active[] = $currplugin['name'];
		}
		
		if($handle = opendir('./'.$CONF['plugin_path'].'/')) {
			
			$allowedPlugins = array();
			// This is the correct way to loop over the directory.
			while(false !== ($plugin = readdir($handle))) {
				// Exclude ., .., and check whether the info.php file of the plugin exist
				if($plugin != '.' && $plugin != '..' && file_exists('./'.$CONF['plugin_path'].'/'.$plugin.'/info.php')) {
					$allowedPlugins[] = $plugin;
					include('./'.$CONF['plugin_path'].'/'.$plugin.'/info.php');
					
					if(in_array($plugin, $active)) {
						$state = '<div class="users-button button-active"><a href="'.$CONF['url'].'/index.php?a=admin&b=plugins&plugin='.$plugin.'&plugin_type='.$type.'&token_id='.$_SESSION['token_id'].'">'.$LNG['deactivate'].'</a></div>';
					} else {
						$state = '<div class="users-button button-normal"><a href="'.$CONF['url'].'/index.php?a=admin&b=plugins&plugin='.$plugin.'&plugin_type='.$type.'&activated=true&token_id='.$_SESSION['token_id'].'">'.$LNG['activate'].'</a></div>';
					}
					
					if(file_exists('./'.$CONF['plugin_path'].'/'.$plugin.'/icon.png')) {
						$image = '<img src="'.$CONF['url'].'/'.$CONF['plugin_path'].'/'.$plugin.'/icon.png">';
					}  else {
						$image = '';
					}
					$output .= '<div class="users-container">
						<div class="message-content">
							<div class="message-inner">
								'.$state.'
								<div class="message-avatar">
									<a href="'.$url.'" target="_blank" title="'.$LNG['auhtor_title'].'">
										'.$image.'
									</a>
								</div>
								<div class="message-top">
									<div class="message-author" rel="loadpage">
										<a href="'.$url.'" target="_blank" title="'.$LNG['auhtor_title'].'">'.$name.'</a> '.$version.'
									</div>
									<div class="message-time">
										'.$LNG['by'].': <a href="'.$url.'" target="_blank" title="'.$LNG['auhtor_title'].'">'.$author.'</a>
									</div>
								</div>
							</div>
						</div>
					</div>';
				}
			}

			closedir($handle);
			return array($output, $allowedThemes);
		}
	}
	
	function activatePlugin($name, $type) {
		if($_GET['token_id'] == $_SESSION['token_id']) {
			// Name: Plugin name
			// Type: The plugin type
			
			$query = $this->db->query(sprintf("SELECT * FROM `plugins` WHERE `name` = '%s'", $this->db->real_escape_string($name)));
			
			$result = $query->fetch_assoc();
				
			if($result['name']) {
				if($_GET['activated']) return false;
				$this->db->query(sprintf("DELETE FROM `plugins` WHERE `name` = '%s'", $this->db->real_escape_string($name)));
			} else {
				$this->db->query(sprintf("INSERT INTO `plugins` (`name`, `type`) VALUES ('%s', '%s')", $this->db->real_escape_string($name), $this->db->real_escape_string($type)));
			}
		}
	}
}

class updateUserSettings {
	public $db;		// Database Property
	public $url;	// Installation URL Property
	public $id;		// Logged in user id
	
	function validate_password($password) {
		$query = $this->db->query(sprintf("SELECT `password` FROM `users` WHERE `idu` = '%s' AND `password` = '%s'", $this->id, $this->db->real_escape_string(md5($password))));
		return $query->num_rows ? 1 : 0;
	}
	
	function validate_inputs($data) {
		if(isset($data['password']) && !isset($data['current_password']) || isset($data['current_password']) && !$this->validate_password($data['current_password'])) {
			return array('wrong_current_password');
		}
		
		if(isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
			return array('valid_email');
		}
		
		if(!filter_var($data['website'], FILTER_VALIDATE_URL) && !empty($data['website'])) {
			return array('valid_url');
		}
		
		if(isset($data['email']) && $this->verify_if_email_exists($this->id, $data['email'])) {
			return array('email_exists');
		}
		
		if(!countries(0, $data['country'])) {
			return array('valid_country');
		}
		
		if(strlen($data['bio']) > 160) {
			return array('bio_description', 160);
		}
		
		if(isset($data['password']) && strlen($data['password']) < 6) {
			return array('password_too_short');
		}
		
		if(isset($data['password']) && $data['password'] !== $data['repeat_password']) {
			return array('password_not_match');
		}
	}

	function query_array($table, $data) {
		global $LNG;
		// Verify if the user has a valid token
		if($data['token_id'] == $_SESSION['token_id']) {
			// Validate the inputs
			$validate = $this->validate_inputs($data);
			
			if($validate) {
				return notificationBox('error', sprintf($LNG["{$validate[0]}"], $validate[1]));
			}
			
			// If the birthdate data is set
			if(isset($data['day']) && isset($data['month']) && isset($data['year'])) {
				// If the user has selected any birthdate
				if(!empty($data['day']) && !empty($data['month']) && !empty($data['year'])) {
					$data['born'] = date("Y-m-d", mktime(0, 0, 0, $data['month'], $data['day'], $data['year']));
				} else {
					$data['born'] = 0;
				}
			}
			
			// Unset unused values
			unset($data['day'], $data['month'], $data['year'], $data['repeat_password'], $data['current_password'], $data['token_id']);
			
			// Get the columns of the query-ed table
			$available = $this->getColumns($table);
			
			foreach ($data as $key => $value) {
				// Check if password array key exist and set a variable if so
				if($key == 'password') {
					$password = true;
				}
				
				// Check if all arrays introduced are available table fields
				if(!array_key_exists($key, $available)) {
					$x = 1;
					break;
				}
			}
			
			// If the password array key exists, encrypt the password
			if($password) {
				$data['password'] = md5($data['password']);
			}
			
			// If all array keys are valid database columns
			if($x !== 1) {
				foreach ($data as $column => $value) {
					$columns[] = sprintf("`%s` = '%s'", $column, $this->db->real_escape_string($value));
				}
				$column_list = implode(',', $columns);

				// Prepare the statement
				$stmt = $this->db->prepare("UPDATE `$table` SET $column_list WHERE `idu` = '{$this->id}'");		

				// Execute the statement
				$stmt->execute();
				
				// Save the affected rows
				$affected = $stmt->affected_rows;
				
				// Close the statement
				$stmt->close();
				
				// If the SQL was executed, and the password field was set, save the new password
				if($affected && $password) {
					if(isset($_COOKIE['password'])) {
						setcookie("password", $data['password'], time() + 30 * 24 * 60 * 60); // Expire in one month
					} else {
						$_SESSION['password'] = $data['password'];
					}
				}

				// If there was anything affected return 1
				if($affected) {
					return notificationBox('success', $LNG['settings_saved']);
				} else {
					return notificationBox('info', $LNG['nothing_changed']);
				}
			}
		} else {
			return notificationBox('info', $LNG['nothing_changed']);
		}
	}
	
	function getColumns($table) {
		
		$query = $this->db->query("SHOW columns FROM `$table` WHERE Field NOT IN ('idu', 'username', 'date', 'salted')");

		// Define an array to store the results
		$columns = array();
		
		// Fetch the results set
		while ($row = $query->fetch_array()) {
			// Store the result into array
			$columns[] = $row[0];
		}
		
		// Return the array;
		return array_flip($columns);
	}
	
	function verify_if_email_exists($id, $email) {
		$query = sprintf("SELECT `idu`, `email` FROM `users` WHERE `idu` <> '%s' AND `email` = '%s'", $this->db->real_escape_string($id), $this->db->real_escape_string(strtolower($email)));
		$result = $this->db->query($query);
		
		return ($result->num_rows == 0) ? 0 : 1;
	}
	
	function getSettings() {
		$result = $this->db->query(sprintf("SELECT * FROM `users` WHERE `idu` = '%s'", $this->db->real_escape_string($this->id)));
		
		return $result->fetch_assoc();
	}
}
class recover {
	public $db;			// Database Property
	public $url;		// Installation URL Property
	public $username;	// The username to recover
	
	function checkUser() {
		// Query the database and check if the username exists
		if(filter_var($this->db->real_escape_string($this->username), FILTER_VALIDATE_EMAIL)) {
			$query = sprintf("SELECT `username`,`email` FROM `users` WHERE `email` = '%s'", $this->db->real_escape_string(strtolower($this->username)));
		} else {
			$query = sprintf("SELECT `username`,`email` FROM `users` WHERE `username` = '%s'", $this->db->real_escape_string(strtolower($this->username)));
		}

		$result = $this->db->query($query);
		
		// If a valid username is found
		if ($result->num_rows > 0) {
			// Fetch Associative values
			$assoc = $result->fetch_assoc();
			
			// Generate the salt for that username
			$generateSalt = $this->generateSalt($assoc['username']);
			
			// If the salt was generated
			if($generateSalt) {
			
				// Return the username, email and salted code
				return array($assoc['username'], $assoc['email'], $generateSalt);
			}
		}
	}
	
	function generateSalt($username) {
		// Generate the salted code
		$salt = md5(mt_rand());
		
		// Prepare to update the database with the salted code
		$stmt = $this->db->prepare("UPDATE `users` SET `salted` = '{$this->db->real_escape_string($salt)}' WHERE `username` = '{$this->db->real_escape_string(strtolower($username))}'");
		
		// Execute the statement
		$stmt->execute();
		
		// Save the affected rows
		$affected = $stmt->affected_rows;
		
		// Close the query
		$stmt->close();

		// If there was anything affected return 1
		if($affected)
			return $salt;
		else 
			return false;
	}
	
	function changePassword($username, $password, $salt) {
		// Query the database and check if the username and the salted code exists
		$query = sprintf("SELECT `username` FROM `users` WHERE `username` = '%s' AND `salted` = '%s'", $this->db->real_escape_string(strtolower($username)), $this->db->real_escape_string($salt));
		$result = $this->db->query($query);
		
		// If a valid match was found
		if ($result->num_rows > 0) {
			
			// Change the password
			$stmt = $this->db->prepare("UPDATE `users` SET `password` = md5('{$password}'), `salted` = '' WHERE `username` = '{$this->db->real_escape_string(strtolower($username))}'");
		
			// Execute the statement
			$stmt->execute();
			
			// Save the affected rows
			$affected = $stmt->affected_rows;
			
			// Close the query
			$stmt->close();
			if($affected) {
				return true;
			} else {
				return false;
			}
		}
	}
}
class manageUsers {
	public $db;			// Database Property
	public $url;		// Installation URL Property
	public $per_page;	// Limit per page
	
	function getUsers($start) {
		global $LNG;
		// If the $start value is 0, empty the query;
		if($start == 0) {
			$start = '';
		} else {
			// Else, build up the query
			$start = 'WHERE `idu` < \''.$this->db->real_escape_string($start).'\'';
		}
		// Query the database and get the latest 20 users
		// If load more is true, switch the query for the live query

		$query = sprintf("SELECT * FROM `users` %s ORDER BY `idu` DESC LIMIT %s", $start, $this->db->real_escape_string($this->per_page + 1));
		
		$result = $this->db->query($query);
		while($row = $result->fetch_assoc()) {
			$rows[] = $row;
		}
		
		if(array_key_exists($this->per_page, $rows)) {
			$loadmore = 1;
			
			// Unset the last array element because it's not needed, it's used only to predict if the Load More Messages should be displayed
			array_pop($rows);
		}
		
		$users = '';	// Define the rows variable
		
		foreach($rows as $row) {
			$users .= '<div class="users-container">
						<div class="message-content">
							<div class="message-inner">
								<div class="users-button button-normal"><a href="'.$this->url.'/index.php?a=admin&b=users&e='.$row['idu'].'" rel="loadpage">'.$LNG['edit'].'</a></div>
								<div class="message-avatar" id="avatar13">
									<a href="'.$this->url.'/index.php?a=profile&u='.$row['username'].'" rel="loadpage">
										<img src="'.$this->url.'/thumb.php?src='.$row['image'].'&t=a&w=50&h=50">
									</a>
								</div>
								<div class="message-top">
									<div class="message-author" id="author13" rel="loadpage">
										<a href="'.$this->url.'/index.php?a=profile&u='.$row['username'].'" rel="loadpage">'.$row['username'].'</a>
									</div>
									<div class="message-time">
										'.realName($row['email']).'
									</div>
								</div>
							</div>
						</div>
					</div>';
			$last = $row['idu'];
		}
		if($loadmore) {
			$users .= '<div class="admin-load-more"><div class="message-container" id="more_users">
					<div class="load_more"><a onclick="manage_the('.$last.', 0)" id="load-more">'.$LNG['view_more_messages'].'</a></div>
				</div></div>';
		}
		
		// Return the array set
		return $users;
	}
	
	function getUser($id, $profile = null) {
		if($profile) {
			$query = sprintf("SELECT `idu`, `username`, `email`, `first_name`, `last_name`, `location`, `website`, `bio`, `facebook`, `twitter`, `gplus`, `born`, `verified` FROM `users` WHERE `username` = '%s'", $this->db->real_escape_string($profile));
		} else {
			$query = sprintf("SELECT `idu`, `username`, `email`, `first_name`, `last_name`, `location`, `website`, `bio`, `facebook`, `twitter`, `gplus`, `born`, `verified` FROM `users` WHERE `idu` = '%s'", $this->db->real_escape_string($id));
		}
		$result = $this->db->query($query);

		// If the user exists
		if($result->num_rows > 0) {
			
			$row = $result->fetch_assoc();

			return $row;
		} else {
			return false;
		}
	}
	
	function suspendUser($id, $type) {
		if($_GET['token_id'] == $_SESSION['token_id']) {
			// Type 0: Restore
			// Type 1: Suspend
			$user = $this->getUser($id);
			
			if($type && $user['suspended'] == 0) {
				$stmt = $this->db->prepare(sprintf("UPDATE `users` SET `suspended` = 1 WHERE `idu` = %s", $this->db->real_escape_string($id)));
			} else {
				$stmt = $this->db->prepare(sprintf("UPDATE `users` SET `suspended` = 0 WHERE `idu` = %s", $this->db->real_escape_string($id)));
			}
			$stmt->execute();
			
			$affected = $stmt->affected_rows;
			
			$stmt->close();
			
			if($affected) {
				if($type) {
					global $LNG;
					// Send suspended account email
					sendMail($user['email'], sprintf($LNG['ttl_suspended_account_mail']), sprintf($LNG['suspended_account_mail'], realName($user['username'], $user['first_name'], $user['last_name']), $this->url, $this->title), $this->email);
				}
			}
		}
	}
	
	function deleteUser($id) {
		// Prepare the statement to delete the user from the database
		$stmt = $this->db->prepare("DELETE FROM `users` WHERE `idu` = '{$this->db->real_escape_string($id)}'");

		// Execute the statement
		$stmt->execute();
		
		// Save the affected rows
		$affected = $stmt->affected_rows;
		
		// Close the statement
		$stmt->close();
		
		// If the user was returned
		if($affected) {
			$feed = new feed();
			$feed->db = $this->db;
			$feed->id = $id;
			
			// Delete the images from messages
			$feed->deleteMessagesImages($id);
			
			// Get all the messages id
			$mids = $feed->getMessagesIds();
			
			$sids = $feed->getMessagesIds(null, null, null, $mids);
			
			// If there are any messages shared
			if($sids) {
				$feed->deleteShared($sids);
			}
			
			// Delete the likes from liked messages and comments
			$this->db->query(sprintf("UPDATE `messages` SET `likes` = `likes`-1, `time` = `time` WHERE `id` IN (SELECT `post` FROM `likes` WHERE `by` = '%s' AND `type` = 0 ORDER BY `likes` ASC)", $this->db->real_escape_string($id)));
			$this->db->query(sprintf("UPDATE `comments` SET `likes` = `likes`-1, `time` = `time` WHERE `id` IN (SELECT `post` FROM `likes` WHERE `by` = '%s' AND `type` = 1 ORDER BY `likes` ASC)", $this->db->real_escape_string($id)));
			
			// Delete the shared messages by other users
			$this->db->query("DELETE FROM `messages` WHERE `type` = 'shared' AND `value` IN ({$mids})");

			// Delete all the messages
			$this->db->query("DELETE FROM `messages` WHERE `uid` = '{$this->db->real_escape_string($id)}'");
			
			// Delete all the comments
			$this->db->query("DELETE FROM `comments` WHERE `uid` = '{$this->db->real_escape_string($id)}'");
			
			// Delete the likes
			$this->db->query("DELETE FROM `likes` WHERE `by` = '{$this->db->real_escape_string($id)}'");
			
			// Delete the likes made by other users to the user's messages
			$this->db->query("DELETE FROM `likes` WHERE `post` IN ({$mids})");
			
			// Delete the reports
			$this->db->query("DELETE FROM `reports` WHERE `by` = '{$this->db->real_escape_string($id)}'");
			
			// Delete all the friendships
			$this->db->query("DELETE FROM `friendships` WHERE `user1` = '{$this->db->real_escape_string($id)}' OR `user2` = '{$this->db->real_escape_string($id)}'");
			
			// Delete all the chats
			$this->db->query("DELETE FROM `chat` WHERE `from` = '{$this->db->real_escape_string($id)}' OR `to` = '{$this->db->real_escape_string($id)}'");
			
			// Delete all the blocks
			$this->db->query("DELETE FROM `blocked` WHERE `uid` = '{$this->db->real_escape_string($id)}' OR `by` = '{$this->db->real_escape_string($id)}'");
			
			// Delete all the notifications
			$this->db->query("DELETE FROM `notifications` WHERE `from` = '{$this->db->real_escape_string($id)}' OR `to` = '{$this->db->real_escape_string($id)}'");
			
			// Delete the user from groups
			$this->db->query("DELETE FROM `groups_users` WHERE `user` = '{$this->db->real_escape_string($id)}'");
			
			// Get the current groups created by the user
			$query = $this->db->query(sprintf("SELECT `groups`.`id`, `groups`.`cover` FROM `groups_users`, `groups` WHERE `groups_users`.`user` = '%s' AND `groups_users`.`group` = `groups`.`id` AND `permissions` = 2 ORDER BY `groups`.`id` ASC", $this->db->real_escape_string($feed->id)));
			
			while($rows = $query->fetch_assoc()) {
				// Delete the Groups cover
				deleteImages($rows['cover'], 0);
				
				// Delete group related things (group, group users, group messages)
				$feed->deleteGroup($rows['id'], 1);
			}
			
			return 1;
		} else {
			return 0;
		}
	}
}
class manageReports {
	public $db;			// Database Property
	public $url;		// Installation URL Property
	public $per_page;	// Limit per page
	
	function getReports($start) {
		global $LNG;
		// If the $start value is 0, empty the query;
		if($start == 0) {
			$start = '';
		} else {
			// Else, build up the query
			$start = 'AND `reports`.`id` < \''.$this->db->real_escape_string($start).'\'';
		}
		// Query the database and get the latest 20 users
		// If load more is true, switch the query for the live query

		$query = sprintf("SELECT * FROM `reports`,`users` WHERE `reports`.`by` = `users`.`idu` AND `state` = 0 %s ORDER BY `reports`.`id` DESC LIMIT %s", $start, $this->db->real_escape_string($this->per_page + 1));

		$result = $this->db->query($query);
		
		while($row = $result->fetch_assoc()) {
			$rows[] = $row;
		}
		
		if(array_key_exists($this->per_page, $rows)) {
			$loadmore = 1;
			
			// Unset the last array element because it's not needed, it's used only to predict if the Load More Messages should be displayed
			array_pop($rows);
		}
		
		$users = '';	// Define the rows variable

		foreach($rows as $row) {
			if($row['type'] == 0) {
				$post = $row['parent'].'#comment'.$row['post'];
				$type = $LNG['rep_comment'];
			} else {
				$post = $row['post'].'#message'.$row['post'];
				$type = $LNG['message'];
			}
			
			$users .= '<div class="users-container" id="report'.$row['id'].'">
						<div class="message-content">
							<div class="message-inner">
								<div class="users-button button-normal"><a onclick="manage_report('.$row['id'].', '.$row['type'].', '.$row['post'].', 1)" title="'.$LNG['admin_reports_delete'].'">'.$LNG['delete'].'</a></div>
								<div class="users-button button-normal"><a onclick="manage_report('.$row['id'].', '.$row['type'].', '.$row['post'].', 0)" title="'.$LNG['admin_reports_ignore'].'">'.$LNG['ignore'].'</a></div>
								<div class="users-button button-normal"><a href="'.$this->url.'/index.php?a=post&m='.$post.'" title="'.$LNG['admin_reports_view'].'" target="_blank">'.$LNG['view'].'</a></div>
								<div class="message-avatar" id="avatar13">
									<a href="'.$this->url.'/index.php?a=profile&u='.$row['username'].'" rel="loadpage">
										<img src="'.$this->url.'/thumb.php?src='.$row['image'].'&t=a&w=50&h=50">
									</a>
								</div>
								<div class="message-top">
									<div class="message-author" id="author13" rel="loadpage">
										<a href="'.$this->url.'/index.php?a=profile&u='.$row['username'].'" rel="loadpage">'.$row['username'].'</a>
									</div>
									<div class="message-time">
										'.$type.'
									</div>
								</div>
							</div>
						</div>
					</div>';
			$last = $row['id'];
		}
		if($loadmore) {
			$users .= '<div class="admin-load-more"><div class="message-container" id="more_reports">
					<div class="load_more"><a onclick="manage_the('.$last.', 1)" id="load-more">'.$LNG['view_more_messages'].'</a></div>
				</div></div>';
		}
		
		// Return the array set
		return $users;
	}
	
	function manageReport($id, $type, $post, $kind) {
		if($kind == 1) {
			// Prepare the statement to delete the message/comment from the database
			if($type == 1) {
				// Get the current type (for images deletion)
				$query = $this->db->query(sprintf("SELECT `type`, `value` FROM `messages` WHERE `id` = '%s'", $this->db->real_escape_string($post)));
				$row = $query->fetch_assoc();
				
				// Execute the deletePhotos function
				deletePhotos($row['type'], $row['value']);
			
				$stmt = $this->db->prepare("DELETE FROM `messages` WHERE `id` = '{$this->db->real_escape_string($post)}'");
			} else {
				$stmt = $this->db->prepare("DELETE FROM `comments` WHERE `id` = '{$this->db->real_escape_string($post)}'");
			}
			// Execute the statement
			$stmt->execute();
			
			// Save the affected rows
			$affected = $stmt->affected_rows;
			
			// Close the statement
			$stmt->close();
			
			$this->db->query("UPDATE `reports` SET `state` = '2' WHERE `post` = '{$this->db->real_escape_string($post)}' AND `type` = '{$this->db->real_escape_string($type)}'");
			return 1;
		} else {
			// Make the report safe
			$stmt = $this->db->prepare("UPDATE `reports` SET `state` = '1' WHERE `post` = '{$this->db->real_escape_string($post)}' AND `type` = '{$this->db->real_escape_string($type)}'");
			
			// Execute the statement
			$stmt->execute();
			
			// Save the affected rows
			$affected = $stmt->affected_rows;
			
			// Close the statement
			$stmt->close();
			
			// If the row has been affected
			return ($affected) ? 1 : 0;
		}
	}
	
}
class feed {
	public $db;					// Database Property
	public $url;				// Installation URL Property
	public $title;				// Installation WebSite Title
	public $email;				// Installation Default E-mail
	public $id;					// The ID of the user
	public $username;			// The username
	public $user_email;			// The email of the current username
	public $per_page;			// The per_page limit for feed
	public $c_start;			// The row where to start the nex
	public $c_per_page;			// Comments per_page limit
	public $s_per_page;			// Subscribers per page (dedicated profile page)
	public $m_per_page;			// Conversation Messages (Chat) per page
	public $time;				// The time option from the admin panel
	public $censor;				// List of censored words
	public $max_size;			// Image size allowed for upload (messages)
	public $image_format;		// Image formats allowed for upload (messages)
	public $message_length;		// The maximum message length allowed for messages/comments
	public $max_images;			// The maxium images allowed to be uploaded per message
	public $is_admin;			// The option for is_admin to show the post no matter what
	public $profile;			// The current viewed user profile
	public $profile_id;			// The profile id of the current viewed user profile
	public $profile_data;		// The public variable which holds all the data for queried user
	public $friendsArray;		// The friends list Array([value],[count])
	public $l_per_post;			// Likes per post (small thumbs)
	public $online_time;		// The amount of time an user is being kept as online
	public $chat_length;		// The maximum chat length allowed for conversations
	public $email_comment;		// The admin settings for allowing e-mails on comments to be sent
	public $email_like;			// The admin settings for allowing e-mails on likes to be sent
	public $email_new_friend;	// The admin settings for allowing e-mails on new friendship to be sent
	public $smiles;				// The admin settings for displaying smiles in messages

	function getMessages($query, $type, $typeVal) {
		// QUERY: Holds the query string
		// TYPE: [loadFeed, loadProfile, loadHashtags]
		// TYPEVAL: Values for the JS functions
		global $LNG;

		// Run the query
		$result = $this->db->query($query);
		
		// Set the result into an array
		$rows = array();
		while($row = $result->fetch_assoc()) {
			$rows[] = $row;
		}
		
		// If the Feed is empty, display a welcome message
		if(empty($rows) && $type == 'loadHashtags') {
			return $this->showError('no_results');
		}
		
		// Define the $loadmore variable
		$loadmore = '';
		
		// If there are more results available than the limit, then show the Load More Comments
		if(array_key_exists($this->per_page, $rows)) {
			$loadmore = 1;
			
			// Unset the last array element because it's not needed, it's used only to predict if the Load More Messages should be displayed
			array_pop($rows);
		}
		
		// Define the $messages variable
		$messages = '';
		
		// If it's set profile, then set $profile
		if($this->profile) {
			$profile = ', \''.$this->profile.'\'';
		}

		// Start outputting the content
		$i = 0;
		foreach($rows as $row) {
			$po = '';
			foreach($this->plugins as $plugin) {
				if(array_intersect(array("7"), str_split($plugin['type']))) {
					$po .= plugin($plugin['name'], array('message' => $row['message'], 'id' => $row['id'], 'type' => $row['type'], 'value' => $row['value'], 'user_id' => $this->id), 1);
				}
			}
			// If the request is being made from groups
			if($this->group_data['id']) {
				// Add the latest viewed message on the group
				if($i == 0 && $this->group_member_data['status'] == 1) {
					// If the user is a member of the group
					$this->groupActivity(1, $row['id']);
				}
			}
			$time = $row['time']; $b = '';
			if($this->time == '0') {
				$time = date("c", strtotime($row['time']));
			} elseif($this->time == '2') {
				$time = $this->ago(strtotime($row['time']));
			} elseif($this->time == '3') {
				$date = strtotime($row['time']);
				$time = date('Y-m-d', $date);
				$b = '-standard';
			}
			
			// Define the style variable (resets the last value)
			$style = '';
			if($row['public'] == 1) {
				$public = '<div class="privacy-icons public-icon" title="'.$LNG['public'].'"></div>';
			} elseif($row['public'] == 2) {
				$public = '<div class="privacy-icons friends-icon" title="'.$LNG['friends'].'"></div>';
			} else {
				$public = '<div class="privacy-icons private-icon" title="'.$LNG['private'].'"></div>';
				$style = ' style="display: none"';
			}
			if(empty($this->username)) {
				$menu = '';
				$style = ' style="display: none"';
			} else {
				if($this->username == $row['username']) {
					$menulist = '
					<div class="message-menu-row" onclick="edit_message('.$row['id'].')" id="edit_text'.$row['id'].'">'.$LNG['edit'].'</div>
					<div class="message-menu-row" onclick="deleteModal('.$row['id'].', 1)">'.$LNG['delete'].'</div>
					'.($row['group'] ? '' : '<div class="message-menu-divider"></div>
					<div class="message-menu-row" onclick="privacy('.$row['id'].', 1)">'.$LNG['public'].'</div>
					<div class="message-menu-row" onclick="privacy('.$row['id'].', 2)">'.$LNG['friends'].'</div>
					<div class="message-menu-row" onclick="privacy('.$row['id'].', 0)">'.$LNG['private'].'</div>');
				} else {
					$menulist = '<div class="message-menu-row" onclick="report_the('.$row['id'].', 1)">'.$LNG['report'].'</div>';
				}
				$grouplist = '';
				if($row['group'] && in_array($this->group_member_data['permissions'], array(1, 2)) && $this->id != $row['idu']) {
					$grouplist = '
					<div class="message-menu-divider"></div>
					<div class="message-menu-row" onclick="group(2, '.$row['id'].', '.$row['group'].', '.$row['uid'].', \'\')">'.$LNG['delete_message'].'</div>
					<div class="message-menu-row" onclick="group(0, 0, '.$row['group'].', '.$row['uid'].', '.$row['id'].')">'.$LNG['remove_user'].'</div>
					';
				}
				$menu = '
				<div class="message-menu" onclick="messageMenu('.$row['id'].', 1)"></div>
				<div id="message-menu'.$row['id'].'" class="message-menu-container">
					'.$menulist.'
					'.$grouplist.'
				</div>';
			}
			
			$shared_title = $sharedMedia = $sharedContent = $group_title = '';
			if($row['group']) {
				$dataType = 2;
				// If the message is viewed from the post page
				if($this->is_post_page) {
					$getGroup = $this->db->query(sprintf("SELECT * FROM `groups` WHERE `id` = '%s'", $row['group']));
					$group = $getGroup->fetch_assoc();
					$group_title = ' '.sprintf($LNG['group_title'], $this->url.'/index.php?a=group&name='.$group['name'], $group['title']);
				}
			} elseif($this->profile) {
				$dataType = 1;
			} else {
				$dataType = 0;
			}
			
			if($row['type'] == 'shared') {
				$getOriginal = $this->db->query(sprintf("SELECT * FROM `messages`, `users` WHERE `messages`.`id` = '%s' AND `messages`.`uid` = `users`.`idu`", $row['value']));
				$shared = $getOriginal->fetch_assoc();
				
				// If the original message is public (anyone can see it)
				if($shared['public'] == 1) {
					// Include the media output
					$sharedContent = $shared['message'];
					$sharedMedia = $this->getType($shared['type'], $shared['value'], 0);
				} else {
					// If the message is private, only display half of the message's content
					$countLetters = round(strlen($shared['message']) / 2);
					$sharedContent = ($shared['message'] ? substr($shared['message'], 0, $countLetters).'...' : '');
				}
				
				$shared_title = ' '.sprintf($LNG['shared_title'], $this->url.'/index.php?a=profile&u='.$shared['username'], realName($shared['username'], $shared['first_name'], $shared['last_name']), $this->url.'/index.php?a=post&m='.$row['value']);
			}
			$messages .= '
			<div class="message-container last-message" id="message'.$row['id'].'" data-filter="'.str_replace('\'', '', $typeVal) .'" data-last="'.$row['id'].'" data-username="'.$this->profile.'" data-type="'.$dataType.'" data-userid="'.$row['uid'].'">
				<div class="message-content">
					<div class="message-inner">
						<div class="message-avatar" id="avatar'.$row['id'].'">
							<a href="'.$this->url.'/index.php?a=profile&u='.$row['username'].'" rel="loadpage">
								<img onmouseover="profileCard('.$row['idu'].', '.$row['id'].', 0, 0);" onmouseout="profileCard(0, 0, 0, 1);" onclick="profileCard(0, 0, 1, 1);" src="'.$this->url.'/thumb.php?src='.$row['image'].'&t=a&w=50&h=50">
							</a>
						</div>
						<div class="message-top">
							'.$menu.'
							<div class="message-author" id="author'.$row['id'].'">
								<a href="'.$this->url.'/index.php?a=profile&u='.$row['username'].'" rel="loadpage">'.realName($row['username'], $row['first_name'], $row['last_name']).'</a>'.$shared_title.$group_title.'
							</div>
							<div class="message-time">
								<span id="time'.$row['id'].'"><a href="'.$this->url.'/index.php?a=post&m='.$row['id'].'" rel="loadpage">
									<div class="timeago'.$b.'" title="'.$time.'">
										'.$time.'
									</div>
								</a></span><span id="privacy'.$row['id'].'">'.$public.'</span>
								<div id="message_loader'.$row['id'].'"></div>
							</div>
						</div>
						<div class="message-message" id="message_text'.$row['id'].'">			
						'.nl2br($this->parseMessage($row['message'])).'
						</div>
						'.($sharedContent ? '<div class="message-message"><div class="message-shared">'.nl2br($this->parseMessage($sharedContent)).'</div></div>' : '').'
					</div>
					<div class="message-divider"></div>
					'.($sharedMedia ? $sharedMedia : $this->getType($row['type'], $row['value'], $row['id'])).$po.'
					<div class="message-replies">
						<div class="message-actions"><div class="message-actions-content" id="message-action'.$row['id'].'">'.$this->getActions($row['id'], $row['likes']).'</div></div>
						<div class="message-replies-content" id="comments-list'.$row['id'].'">
							'.$this->getComments($row['id'], null, $this->c_start).'
						</div>
					</div>
					<div class="message-comment-box-container" id="comment_box_'.$row['id'].'"'.$style.'>
						<div class="message-reply-avatar">
							'.((!empty($this->user)) ? '<img src="'.$this->url.'/thumb.php?src='.$this->user['image'].'&t=a&w=50&h=50">' : '').'
						</div>
						<div class="message-comment-box-form">
							<textarea id="comment-form'.$row['id'].'" onclick="showButton('.$row['id'].')" placeholder="'.$LNG['leave_comment'].'" class="comment-reply-textarea"></textarea>
						</div>
						<div class="comment-btn button-active" id="comment_btn_'.$row['id'].'">
							<a onclick="postComment('.$row['id'].')">'.$LNG['post'].'</a>
						</div>
						<div class="delete_preloader" id="post_comment_'.$row['id'].'"></div>
					</div>
				</div>	
			</div>';
			$start = $row['id'];
			$i++;
		}
		
		// If the $loadmore button is set, then show the Load More Messages button
		if($loadmore) {
			$messages .= '
						<div class="message-container" id="more_messages">
							<div class="load_more"><a onclick="'.$type.'('.$start.', '.$typeVal.''.$profile.')" id="load-more">'.$LNG['view_more_messages'].'</a></div>
						</div>';
		}
		return array($messages, 0);
	}
	
	function getFeed($start, $value, $from = null) {
		// From: Load posts starting with a certain ID
		
		$this->friends = $this->getFriendsList();
		
		if(!empty($this->friends)) {
			$this->friendsList = $this->id.','.$this->friends;
		} else {
			$this->friendsList = $this->id;
		}
		
		// Disable the per_page limit if $from is set
		if(is_numeric($from)) {
			$this->per_page = 9999;
			$from = 'AND messages.id > \''.$this->db->real_escape_string($from).'\'';
		} else {
			$from = '';
		}

		// Allowed types (if it's empty, return false to cancel the query)
		$this->listTypes = $this->listTypes($this->friendsList);
		$this->listDates = $this->listDates($this->friendsList);
		
		// If the $start value is 0, empty the query;
		if($start == 0) {
			$start = '';
		} else {
			// Else, build up the query
			$start = 'AND messages.id < \''.$this->db->real_escape_string($start).'\'';
		}
		
		if(in_array($value, $this->listTypes)) {
			$query = sprintf("SELECT * FROM messages, users WHERE messages.uid IN (%s) AND messages.type = '%s' AND users.suspended = 0 AND messages.group = 0 AND messages.public <> 0 AND messages.uid = users.idu %s %s ORDER BY messages.id DESC LIMIT %s", $this->friendsList, $this->db->real_escape_string($value), $start, $from, ($this->per_page + 1));
			$value = '\''.$value.'\'';
		} elseif(in_array($value, $this->listDates)) {
			$query = sprintf("SELECT * FROM messages, users WHERE messages.uid IN (%s) AND extract(YEAR_MONTH from `time`) = '%s' AND users.suspended = 0 AND messages.group = 0 AND messages.public <> 0 AND messages.uid = users.idu %s %s ORDER BY messages.id DESC LIMIT %s", $this->friendsList, $this->db->real_escape_string($value), $start, $from, ($this->per_page + 1));
			$value = '\''.$value.'\'';
		} else {
			// The query to select the subscribed users
			$query = sprintf("SELECT * FROM messages, users WHERE messages.uid IN (%s) AND users.suspended = 0 AND messages.group = 0 AND messages.public <> 0 AND messages.uid = users.idu %s %s ORDER BY messages.id DESC LIMIT %s", $this->friendsList, $start, $from, ($this->per_page + 1));
			$value = '\'\'';
		}

		return $this->getMessages($query, 'loadFeed', $value);
	}
	
	function getProfile($start, $value, $from = null) {
		$profile = $this->profile_data;
		$this->profile_id = $profile['idu'];
		
		// If the username exist
		if(!empty($profile['idu'])) {
			if($this->is_admin) {
				$private = 0;
			} elseif($this->id == $this->profile_id) {
				$private = 0;
			} else {
				$friendship = $this->verifyFriendship($this->id, $this->profile_id);
				
				// If the profile is set to friends only and there is no friendship
				if($profile['private'] == 2 && $friendship['status'] !== '1') {
					$private = 'profile_semi_private';
				}
				if($profile['suspended']) {
					$private = 'profile_suspended';
				}
				// If the profile is fully private
				elseif($profile['private'] == 1) {
					$private = 'profile_private';
				}
				// If the profile is blocked
				elseif($this->getBlocked($this->profile_id, 2)) {
					$private = 'profile_blocked';
				}
				if($private) {
					return $this->showError($private);
				}
			}
			
			// Allowed types
			$this->listTypes = $this->listTypes('profile');
			$this->listDates = $this->listDates('profile');
			
			// Disable the per_page limit if $from is set
			if(is_numeric($from)) {
				$this->per_page = 9999;
				$from = 'AND messages.id > \''.$this->db->real_escape_string($from).'\'';
			} else {
				$from = '';
			}
			
			// If the $start value is 0, empty the query;
			if($start == 0) {
				$start = '';
			} else {
				// Else, build up the query
				$start = 'AND messages.id < \''.$this->db->real_escape_string($start).'\'';
			}
			
			// Decide if the query will include only public messages or not
			// If the user that views the profile is not the owner
			if($this->id !== $this->profile_data['idu']) {
				// Check if is admin or not
				if($this->is_admin) {
					$public = '';
				} else {
					// Check if there is any friendship relation
					$friendship = $this->verifyFriendship($this->id, $this->profile_data['idu']);
				
					if($friendship['status'] == '1') {
						$public = "AND `messages`.`public` <> 0";
					} else {
						$public = "AND `messages`.`public` = 1";
					}
				}
			}
			if(in_array($value, $this->listTypes)) {
				$query = sprintf("SELECT * FROM messages, users WHERE messages.uid = '%s' AND messages.type = '%s' AND messages.group = 0 AND messages.uid = users.idu %s %s %s ORDER BY messages.id DESC LIMIT %s", $this->db->real_escape_string($profile['idu']), $this->db->real_escape_string($value), $public, $start, $from, ($this->per_page + 1));
				$value = '\''.$value.'\'';
			} elseif(in_array($value, $this->listDates)) {
				$query = sprintf("SELECT * FROM messages, users WHERE messages.uid = '%s' AND extract(YEAR_MONTH from `time`) = '%s' AND messages.group = 0 AND messages.uid = users.idu %s %s %s ORDER BY messages.id DESC LIMIT %s", $this->db->real_escape_string($profile['idu']), $this->db->real_escape_string($value), $public, $start, $from, ($this->per_page + 1));
				$value = '\''.$value.'\'';
			} else {
				$query = sprintf("SELECT * FROM messages, users WHERE messages.uid = '%s' AND messages.group = 0 AND messages.uid = users.idu %s %s %s ORDER BY messages.id DESC LIMIT %s", $this->db->real_escape_string($profile['idu']), $public, $start, $from, ($this->per_page + 1));
				$value = '\'\'';
			}
			
			return $this->getMessages($query, 'loadProfile', $value);
		} else {
			return $this->showError('profile_not_exist');
		}
	}
	
	function getGroup($start, $group, $from = null) {
		// From: Load posts starting with a certain ID

		$users = $this->getGroupUsers($group, 0);
		
		// Check the Group's privacy
		if($this->group_data['privacy']) {
			if($this->is_admin) {
				$private = 0; 
			} elseif(!$this->groupPermission($this->group_data, $this->group_member_data)) {
				$private = 1;
			}
			if($private) return $this->showError('group_private');
		}
		
		// Disable the per_page limit if $from is set
		if(is_numeric($from)) {
			$this->per_page = 9999;
			$from = 'AND `messages`.`id` > \''.$this->db->real_escape_string($from).'\'';
		} else {
			$from = '';
		}
		
		// If the $start value is 0, empty the query;
		if($start == 0) {
			$start = '';
		} else {
			// Else, build up the query
			$start = 'AND `messages`.`id` < \''.$this->db->real_escape_string($start).'\'';
		}

		// The query to select the subscribed users
		$query = sprintf("SELECT * FROM `messages`, `users` WHERE `messages`.`uid` IN (%s) AND `group` = '%s' AND `users`.`suspended` = 0 AND `messages`.`public` = 1 AND `messages`.`uid` = `users`.`idu` %s %s ORDER BY `messages`.`id` DESC LIMIT %s", $users, $group, $start, $from, ($this->per_page + 1));
		
		return $this->getMessages($query, 'loadGroup', $group);
	}
	
	function groupPermission($group, $user, $type = null) {
		// Type 1: Check if the user can post
		// Type 0: Check if the user can view the group's messages
		if($type == 1) {
			// If the user is in group
			if($user['status'] == 1) {
				// If the group settings allow only admins to post
				if($group['posts']) {
					// Check if the user is an administrator
					if(in_array($user['permissions'], array(1, 2))) {
						return 1;
					} else {
						return false;
					}
				}
				return 1;
			}
		} else {
			// If the group is public
			if($group['privacy'] == 0) {
				return 1;
			}
			// If the group is private
			if($group['privacy'] == 1) {
				// If the user is in group
				if($user['status'] == 1) {
					return 1;
				}
			}
		}
		return false;
	}
	
	function getFriendsList($type = null) {
		// Type 1: Returns both confirmed and pending friendships
		// Type 0: Returns only confirmed friendships
		
		if($type) {
			$status = "";
		} else {
			$status = "AND `status` = '1'";
		}
		
		// The query to select the friends list
		$query = sprintf("SELECT `user2` as `friends` FROM `friendships` WHERE `user1` = '%s' %s UNION ALL SELECT `user1` as `friends` FROM `friendships` WHERE `user2` = '%s' %s ORDER BY `friends` ASC", $this->db->real_escape_string($this->id), $status, $this->db->real_escape_string($this->id), $status);

		// Run the query
		$result = $this->db->query($query);
		
		// The array to store the subscribed users
		$friends = array();
		while($row = $result->fetch_assoc()) {
			$friends[] = $row['friends'];
		}
		
		// Close the query
		$result->close();
		
		// Return the friends list (e.g: 13,22,19)
		return implode(',', $friends);
	}
	
	function getGroupUsers($name = null, $type = null) {
		// Type 1: Returns both approved and pending group members
		// Type 0: Returns only approved group members
		
		if($type) {
			$status = "";
		} else {
			$status = " AND `status` = '1'";
		}
		
		// The query to select the friends list
		$query = sprintf("SELECT `user` FROM `groups_users` WHERE `group` = '%s'%s", $name, $status);

		// Run the query
		$result = $this->db->query($query);
		
		// The array to store the subscribed users
		$users = array();
		while($row = $result->fetch_assoc()) {
			$users[] = $row['user'];
		}
		
		// Close the query
		$result->close();
		
		// Return the users list (e.g: 13,22,19)
		return implode(',', $users);
	}
	
	function groupData($name = null, $id = null) {
		if($id) {
			$query = sprintf("SELECT * FROM `groups` WHERE `id` = '%s'", $this->db->real_escape_string($id));
		} else {
			$query = sprintf("SELECT * FROM `groups` WHERE `name` = '%s'", $this->db->real_escape_string($name));
		}
		
		// Run the query
		$result = $this->db->query($query);
		
		return $result->fetch_assoc();
	}
	
	function groupOwner($id) {
		// Return the group owner ID (Admin panel)
		$query = sprintf("SELECT * FROM `groups_users` WHERE `group` = %s AND `permissions` = 2", $this->db->real_escape_string($id));
		
		// Run the query
		$result = $this->db->query($query);
		
		return $result->fetch_assoc();
	}
	
	function groupMemberData($group = null) {
		if($group && $this->id) {
			$query = $this->db->query(sprintf("SELECT `groups_users`.`status`, `groups_users`.`permissions` FROM `groups`, `groups_users` WHERE `groups`.`id` = '%s' AND `groups_users`.`user` = '%s' AND `groups`.`id` = `groups_users`.`group`", $this->db->real_escape_string($group), $this->db->real_escape_string($this->id)));

			return $query->fetch_assoc();
		}
	}
	
	function fetchGroup($group) {
		global $LNG, $CONF;
		$coverImage = ((!empty($group['cover'])) ? $group['cover'] : 'default.png');
		$cover = '<div class="twelve columns">
					<div class="cover-container">
						<div class="cover-content">
							<a onclick="gallery(\''.$coverImage.'\', \''.$group['id'].$group['title'].'\', \'covers\')" id="'.$coverImage.'"><div class="cover-image" style="background-position: center; background-image: url('.$this->url.'/thumb.php?src='.((!empty($group['cover'])) ? $group['cover'] : 'default.png').'&w=900&h=200&t=c)">
							</div></a>
							<div class="cover-description">
								<div class="cover-buttons cover-buttons-group">
									'.$this->coverButtons(1).'
								</div>
								<div class="cover-description-content cover-group-content">
									<div class="cover-username-container"><div class="cover-username"><a href="'.$this->url.'/index.php?a=group&name='.$group['name'].'" rel="loadpage">'.realName($group['title']).'</a></div></div>
									<div class="cover-description-buttons"><div id="group-btn-'.$group['id'].'" class="friend-btn">'.$this->joinGroup(0).'</div></div>
								</div>
							</div>
						</div>
					</div>
				</div>';
		return $cover;
	}
	
	function joinGroup($type) {
		global $LNG, $CONF;
		
		// Type 0: Return buttons
		
		// If the user is not logged-in, or has been group blocked
		if(!$this->id) {
			return false;
		} elseif($this->group_member_data['status'] == '2') {
			return false;
		} elseif($this->group_member_data['permissions'] == '2') {
			return false;
		}
		
		if($type == 1) {
			$old_id = $this->id;
			$this->id = '';
			if($this->group_member_data['status'] == '1') {
				// Remove the user
				$this->groupMember(0, $old_id);
			} elseif($this->group_member_data['status'] == '0') {
				// Remove the user
				$this->groupMember(0, $old_id);
			} else {
				// If the group is private, request to join
				if($this->group_data['privacy'] == 1) {
					$this->db->query(sprintf("INSERT INTO `groups_users` (`group`, `user`, `status`, `permissions`) VALUES ('%s', '%s', '%s', '%s')", $this->group_data['id'], $old_id, 0, 0));
				} else {
					// Add in group
					$this->db->query(sprintf("INSERT INTO `groups_users` (`group`, `user`, `status`, `permissions`) VALUES ('%s', '%s', '%s', '%s')", $this->group_data['id'], $old_id, 1, 0));
				}
			}
			
			$this->id = $old_id;
			$this->group_member_data = $this->groupMemberData($this->group_data['id']);
			return $this->joinGroup(0);
		} else {
			if($this->group_member_data['status'] == '1') {
				$class = 'approve-button group-join';
				$text = $LNG['leave_group'];
			} elseif($this->group_member_data['status'] == '0') {
				$class = 'pending-button group-join';
				$text = $LNG['pending_approval'];
			} else {
				$class = 'join-button';
				$text = $LNG['join_group'];
			}
			$output = '<div class="group-button '.$class.'" title="'.$text.'" onclick="group(6, 0, '.$this->group_data['id'].')"></div>';
		}
		return $output;
	}
	
	function sidebarGroupInfo($group) {
		global $LNG;
		
		$born = explode('-', $group['time']);
		
		// Make it into integer instead of a string (removes the 0, e.g: 03=>3, prevents breaking the language)
		$month = intval($born[1]);

		// Start checking the values
		if($month) {
			$birthdate = $LNG["month_$month"].' '.substr($born[2], 0, 2).', '.$born[0];
		}
		
		$extra = ($group['posts'] ? $LNG['admins_posts'] : $LNG['members_posts']);
		
		$rows = array(
			$LNG['created_on']		=> $birthdate,
			$LNG['privacy']		=> ($group['privacy'] ? $LNG['private'].$extra : $LNG['public'].$extra),
			$LNG['description']	=> $group['description']
		);

		$info = '<div class="sidebar-container widget-group-info"><div class="sidebar-content"><div class="sidebar-header">'.$LNG['profile_about'].''.(($this->group_member_data['permissions'] == 2) ? ' <span class="sidebar-header-link"><a href="'.$this->url.'/index.php?a=group&name='.$group['name'].'&r=edit" rel="loadpage">'.$LNG['admin_ttl_edit'].'</a></span>' : '').'</div>';
		
		foreach($rows as $column => $value) {
			if($value) {
				$info .= '<div class="sidebar-list">'.$column.': <strong>'.nl2br($value).'</strong></div>';
			}
		}
		
		$info .= '</div></div>';
		
		return $info;
	}
	
	public function profileData($username = null, $id = null) {
		// The query to select the profile
		// If the $id is set (used in Add Friend function for profiles) then search for the ID
		if($id) {
			$query = sprintf("SELECT `idu`, `username`, `email`, `first_name`, `last_name`, `country`, `location`, `address`, `school`, `work`, `website`, `bio`, `date`, `facebook`, `twitter`, `gplus`, `image`, `private`, `suspended`, `privacy`, `born`, `cover`, `verified`, `gender`, `interests`, `email_new_friend` FROM `users` WHERE `idu` = '%s'", $this->db->real_escape_string($id));
		} else {
			$query = sprintf("SELECT `idu`, `username`, `email`, `first_name`, `last_name`, `country`, `location`, `address`, `school`, `work`, `website`, `bio`, `date`, `facebook`, `twitter`, `gplus`, `image`, `private`, `suspended`, `privacy`, `born`, `cover`, `verified`, `gender`, `interests`, `email_new_friend` FROM `users` WHERE `username` = '%s'", $this->db->real_escape_string($username));
		}
		
		// Run the query
		$result = $this->db->query($query);
		
		return $result->fetch_assoc();
	}
	
	function fetchProfile($profile) {
		global $LNG, $CONF;
		$coverImage = ((!empty($profile['cover'])) ? $profile['cover'] : 'default.png');
		$coverAvatar = ((!empty($profile['image'])) ? $profile['image'] : 'default.png');
		$cover = '<div class="twelve columns">
					<div class="cover-container">
						<div class="cover-content">
							<a onclick="gallery(\''.$coverImage.'\', \''.$profile['idu'].$profile['username'].'\', \'covers\')" id="'.$coverImage.'"><div class="cover-image" style="background-position: center; background-image: url('.$this->url.'/thumb.php?src='.((!empty($profile['cover'])) ? $profile['cover'] : 'default.png').'&w=900&h=200&t=c)">
							</div></a>
							<div class="cover-description">
								<div class="cover-avatar-content">
									<div class="cover-avatar">
										<a onclick="gallery(\''.$coverAvatar.'\', \''.$profile['idu'].$profile['username'].'\', \'avatars\')" id="'.$coverAvatar.'"><span id="avatar'.$profile['idu'].$profile['username'].'"><img src="'.$this->url.'/thumb.php?src='.$coverAvatar.'&t=a&w=150&h=150"></span></a>
									</div>
								</div>
								<div class="cover-buttons">
									'.$this->coverButtons(0).'
								</div>
								<div class="cover-description-content">
									<span id="author'.$profile['idu'].$profile['username'].'"></span><span id="time'.$profile['idu'].$profile['username'].'"></span><div class="cover-username-container"><div class="cover-username"><a href="'.$this->url.'/index.php?a=profile&u='.$profile['username'].'" rel="loadpage">'.realName($profile['username'], $profile['first_name'], $profile['last_name']).'</a>'.((!empty($profile['verified'])) ? '<img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/verified.png" title="'.$LNG['verified_user'].'">' : '').'</div></div>
									<div class="cover-description-buttons"><div id="friend'.$profile['idu'].'" class="friend-btn">'.$this->friendship(null, null, null).'</div>'.$this->chatButton($profile['idu'], $profile['username'], 1).'</div>
								</div>
							</div>
						</div>
					</div>
				</div>';
		return $cover;
	}
	
	function countGroupMembers($name = null, $type = null) {
		// Type 0: Count the Group Members
		// Type 1: Count the Group Admins
		// Type 2: Count the Group Membership Requests
		// Type 3: Count the Group Blocked Members
		
		if($type == 1) {
			$status = 1;
			$type = ' AND `groups_users`.`permissions` IN (1,2)';
		} elseif($type == 2) {
			$status = 0;
			$type = '';
		} elseif($type == 3) {
			$status = 2;
			$type = '';
		} else {
			$status = 1;
			$type = '';
		}

		$query = $this->db->query(sprintf("SELECT COUNT(`groups_users`.`id`) FROM `groups_users`, `groups` WHERE `groups`.`id` = '%s' AND `groups`.`id` = `groups_users`.`group` AND `groups_users`.`status` = '%s' %s", $name, $status, $type));
		
		// Store the array results
		$result = $query->fetch_array();
		
		// Return the likes value
		return $result[0];
	}
	
	function coverButtons($type) {
		// Type 0: Return the buttons for profile covers
		// Type 1: Return the buttons for group covers 
		global $LNG;
		
		// array map: value => array(get_param, get_param_value, value)
		if($type) {
			$buttons = array(
						$LNG['discussion'] => array('', '', ''),
						$LNG['members'] => array('&r=', 'members', $this->countGroupMembers($this->group_data['id'], 0)),
						$LNG['admins'] => array('&r=', 'admins', $this->countGroupMembers($this->group_data['id'], 1)),
						(in_array($this->group_member_data['permissions'], array(1, 2)) && $this->group_member_data['status'] ? $LNG['requests'] : '') => array('&r=', 'requests', $this->countGroupMembers($this->group_data['id'], 2)),
						(in_array($this->group_member_data['permissions'], array(1, 2)) && $this->group_member_data['status'] ? $LNG['blocked'] : '') => array('&r=', 'blocked', $this->countGroupMembers($this->group_data['id'], 3)),
						($this->group_member_data['permissions'] == 2 && $this->group_member_data['status'] ? $LNG['edit'] : '') => array('&r=', 'edit', '')
						);
		} else {
			$groups = $this->countGroups();
			$likes = $this->getLikes();
			$pictures = $this->getPictures();
			$buttons = array(
						$LNG['timeline'] => array('', '', ''),
						$LNG['about'] => array('&r=', 'about', ''),
						($pictures ? $LNG['sidebar_picture'] : '') => array('&filter=', 'picture', $pictures), 
						(($this->friendsArray[1]) ? $LNG['friends'] : '') => array('&r=', 'friends', $this->friendsArray[1]),
						($likes ? $LNG['likes'] : '') => array('&r=', 'likes', $likes),
						($groups ? $LNG['groups'] : '') => array('&r=', 'groups', $groups)
						);
		}
		
		foreach($buttons as $value => $name) {
			// Check whether the value is empty or not in order to return the button
			
			if($value) {
				if($type) {
					$link = 'group&name='.$_GET['name'].$name[0].$name[1];
				} else {
					$link = 'profile&u='.((!empty($this->profile)) ? $this->profile : $this->username).$name[0].$name[1];
				}
				$button .= '<a class="cover-button'.((($name[1] == $_GET['r'] && empty($_GET['filter']) && empty($_GET['friends']) && empty($_GET['search'])) || ($name[1] == $_GET['filter'] && isset($_GET['filter']) && empty($_GET['r']))) ? ' cover-button-active' : '').'" rel="loadpage" href="'.$this->url.'/index.php?a='.$link.'">'.$value.(($name[2]) ? ' <span class="cover-button-value">('.$name[2].')</span>' : '').'</a>';
			}
		}
		
		$button .= '<div class="message-btn button-normal" onclick="messageMenu(\'profile\', 1)" id="profile-button"><div class="group-button menu-button" id="profile-btn"></div></div><div id="message-menuprofile" class="message-menu-container menu-profile-container">';
			
		foreach($buttons as $value => $name) {
			// Check whether the value is empty or not in order to return the button
			
			if($value) {
				if($type) {
					$link = 'group&name='.$_GET['name'].$name[0].$name[1];
				} else {
					$link = 'profile&u='.((!empty($this->profile)) ? $this->profile : $this->username).$name[0].$name[1];
				}
				$button .= '<a class="'.((($name[1] == $_GET['r'] && empty($_GET['filter'])) || ($name[1] == $_GET['filter'] && isset($_GET['filter']) && empty($_GET['r']))) ? ' profile-menu-active' : '').'" rel="loadpage" href="'.$this->url.'/index.php?a='.$link.'"><div class="message-menu-row">'.$value.(($name[2]) ? ' <span class="profile-menu-value">('.$name[2].')</span>' : '').'</div></a>';
			}
		}
			
		$button .='
			</div>
		';
		
		return $button;
	}
	
	function getProfileCard($profile) {
		global $LNG, $CONF;
		$coverImage = ((!empty($profile['cover'])) ? $profile['cover'] : 'default.png');
		$coverAvatar = ((!empty($profile['image'])) ? $profile['image'] : 'default.png');
		$subscribe = $this->friendship(null, null, null);
		$card = '
			<div class="profile-card-cover"><img src="'.$this->url.'/thumb.php?src='.((!empty($profile['cover'])) ? $profile['cover'] : 'default.png').'&w=300&h=100&t=c"></div>
			<div class="profile-card-avatar">
				<a href="'.$this->url.'/index.php?a=profile&u='.$profile['username'].'"><img src="'.$this->url.'/thumb.php?src='.$coverAvatar.'&t=a&w=112&h=112"></a>
			</div>
			<div class="profile-card-info">
				<a href="'.$this->url.'/index.php?a=profile&u='.$profile['username'].'" rel="loadpage"><span id="author'.$profile['idu'].$profile['username'].'"></span><span id="time'.$profile['idu'].$profile['username'].'"></span><div class="cover-username">'.realName($profile['username'], $profile['first_name'], $profile['last_name']).''.((!empty($profile['verified'])) ? '<img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/verified.png" title="'.$LNG['verified_user'].'" height="16" width="16">' : '').'</div></a>
			</div>
			'.((!empty($profile['bio'])) ? '<div class="profile-card-divider"></div><div class="profile-card-bio">'.$profile['bio'].'</div>' : '').'
			'.((!empty($subscribe)) ? '
			<div class="profile-card-divider"></div>
			<div class="profile-card-buttons"><div class="profile-card-buttons-container"><div id="friend'.$profile['idu'].'">'.$subscribe.'</div>'.$this->chatButton($profile['idu'], $profile['username'], 1).'</div></div>' : '').'
		';
		return $card;
	}
	
	function getAbout($profile) {
		global $LNG;
		
		// Contact Information section
		if($profile['country'] && $profile['location']) {
			$country = $profile['location'].', '.$profile['country'];
		} elseif($profile['country']) {
			$country = $profile['country'];
		} elseif($profile['location']) {
			$country = $profile['location'];
		}
		
		if($profile['address']) {
			$address = $profile['address'];
		}
		
		$location = (($address) ? '<div>'.$address.'</div>' : '').''.(($country) ? '<div>'.$country.'</div>' : '');
		
		if($profile['facebook'] || $profile['twitter'] || $profile['gplus']) {
			$social .= ($profile['facebook']) ? '<div><a href="http://facebook.com/'.$profile['facebook'].'" target="_blank" rel="nofllow">Facebook</a></div>' : '';
			$social .= ($profile['twitter']) ? '<div><a href="http://twitter.com/'.$profile['twitter'].'" target="_blank" rel="nofllow">Twitter</a></div>' : '';
			$social .= ($profile['gplus']) ? '<div><a href="http://plus.google.com/'.$profile['gplus'].'" target="_blank" rel="nofllow">Google+</a></div>' : '';
		}
		
		if($social) {
			$social = '<div class="about-text">'.$social.'</div>';
		}
		
		if($profile['website']) {
			$website = '<a href="'.$profile['website'].'" target="_blank" rel="nofllow">'.$profile['website'].'</a>';
		}
		
		// Basic Information section
		if($profile['gender']) {
			$gender = ($profile['gender'] == 1) ? $LNG['male'] : $LNG['female'];
		}
		
		if($profile['interests']) {
			$interests = ($profile['interests'] == 1) ? $LNG['men'] : $LNG['women'];
		}
		
		// Explode the born value [[0]=>Y,[1]=>M,[2]=>D];
		$born = explode('-', $profile['born']);
		
		// Make it into integer instead of a string (removes the 0, e.g: 03=>3, prevents breaking the language)
		$month = intval($born[1]);
		
		// Start checking the values
		if($month) {
			$birthdate = $LNG["month_$month"].' '.$born[2].', '.$born[0];
		}
		
		// Work and Education Information
		if($profile['school']) {
			$school = $profile['school'];
		}
		
		if($profile['work']) {
			$work = $profile['work'];
		}
		
		// About section
		if($profile['bio']) {
			$bio = $profile['bio'];
		}
		
		if($location || $website || $social) {
			$contactSection = 1;
		}
		if($gender || $birthdate || $interests) {
			$basicSection = 1;
		}
		if($work || $school) {
			$educationSection = 1;
		}
		if($bio) {
			$aboutSection = 1;
		}
		if(!$aboutSection && !$basicSection && !$contactSection && !$educationSection) {
			$info = $LNG['no_info_avail'];
		}
		$about = '
		<div class="message-container">
			<div class="message-content">
				<div class="message-inner">
					'.$info.'
					'.(($contactSection) ? '<div><strong>'.$LNG['contact_information'].'</strong></div>
					'.(($location)	? '<div class="about-row"><div class="about-text">'.$LNG['address'].'</div><div class="about-text">'.$location.'</div></div>' : '').'
					'.(($website) 	? '<div class="about-row"><div class="about-text">'.$LNG['ttl_website'].'</div><div class="about-text">'.$website.'</div></div>' : '').'
					'.(($social) 	? '<div class="about-row"><div class="about-text">'.$LNG['other_accounts'].'</div>'.$social.'</div>' : '').'<br>' : '').'
					'.(($basicSection) ? '<div><strong>'.$LNG['basic_information'].'</strong></div>
					'.(($gender)	? '<div class="about-row"><div class="about-text">'.$LNG['ttl_gender'].'</div><div class="about-text">'.$gender.'</div></div>' : '').'
					'.(($birthdate)	? '<div class="about-row"><div class="about-text">'.$LNG['ttl_birthdate'].'</div><div class="about-text">'.$birthdate.'</div></div>' : '').'
					'.(($interests) ? '<div class="about-row"><div class="about-text">'.$LNG['interests'].'</div><div class="about-text">'.$interests.'</div></div>' : '').'<br>' : '').'
					'.(($educationSection) ? '<div><strong>'.$LNG['work_and_education'].'</strong></div>
					'.(($work)		? '<div class="about-row"><div class="about-text">'.$LNG['works_at'].'</div><div class="about-text">'.$work.'</div></div>' : '').'
					'.(($school) 	? '<div class="about-row"><div class="about-text">'.$LNG['studied_at'].'</div><div class="about-text">'.$school.'</div></div>' : '').'<br>' : '').'
					'.(($aboutSection) ? '<div class="about-row"><strong>'.$LNG['about'].'</strong></div>
					'.(($bio) ? '<div class="about-row"><div class="about-text">'.$LNG['ttl_bio'].'</div><div class="about-text">'.$bio.'</div></div>' : '') : '').'
				</div>
			</div>
		</div>';
		return $about;
	}
	
	function fetchProfileWidget($username, $name, $image) {
		global $LNG;
		$widget =  '<div class="sidebar-container widget-welcome">
						<div class="sidebar-content">
							<div class="sidebar-header">'.$LNG['welcome'].'</div>
							<div class="sidebar-inner">
								<div class="sidebar-avatar"><a href="'.$this->url.'/index.php?a=profile&u='.$username.'" rel="loadpage"><img src="'.$this->url.'/thumb.php?src='.$image.'&t=a&w=50&h=50"></a></div>
								<div class="sidebar-avatar-desc">
									<a href="'.$this->url.'/index.php?a=profile&u='.$username.'" rel="loadpage">'.((!empty($name) ? $name : $username)).'</a>
									<div class="sidebar-avatar-edit"><a href="'.$this->url.'/index.php?a=settings" rel="loadpage">'.$LNG['admin_ttl_edit_profile'].'</a></div>
								</div>
							</div>
						</div>
					</div>';
		return $widget;
	}
	
	function checkNewMessages($last, $filter = null, $type = null) {
		global $LNG;
		// Type 0: Feed
		// Type 1: Profile
		// Type 2: Group
		if($type == 1) {
			$message = $this->getProfile(0, $filter, $last);
		} elseif($type == 2) {
			$message = $this->getGroup(0, $filter, $last);
		} else {
			$message = $this->getFeed(0, $filter, $last);
		}
		return $message[0];
	}
	
	function sidebarProfileInfo($profile) {
		global $LNG;
		
		// Explode the born value [[0]=>Y,[1]=>M,[2]=>D];
		$born = explode('-', $profile['born']);
		
		// Make it into integer instead of a string (removes the 0, e.g: 03=>3, prevents breaking the language)
		$month = intval($born[1]);
		
		// Start checking the values
		if($month) {
			$birthdate = $LNG["month_$month"].' '.$born[2].', '.$born[0];
		}
		if($profile['country'] && $profile['location']) {
			$country = $profile['location'].', '.$profile['country'];
		} elseif($profile['country']) {
			$country = $profile['country'];
		} elseif($profile['location']) {
			$country = $profile['location'];
		}
		if($profile['school']) {
			$school = $profile['school'];
		}
		if($profile['work']) {
			$work = $profile['work'];
		}
		if($profile['gender']) {
			$gender = ($profile['gender'] == 1) ? $LNG['male'] : $LNG['female'];
		}
		
		$rows = array(
			$LNG['works_at']			=> array('work', $work),
			$LNG['studied_at']			=> array('school', $school),
			$LNG['lives_in']			=> array('location', $country),
			$LNG['born_on']				=> array('calendar', $birthdate),
			$LNG['ttl_gender']			=> array(($profile['gender'] == 1 ? 'male' : 'female'), $gender),
			$LNG['friends']				=> array('friends', $this->sidebarFriends(0, 1))
		);

		$info = '<div class="sidebar-container widget-about"><div class="sidebar-content"><div class="sidebar-header"><a href="'.$this->url.'/index.php?a=profile&u='.$this->profile.'&r=about" rel="loadpage">'.$LNG['profile_about'].'</a>'.(($this->profile == $this->username) ? ' <span class="sidebar-header-link"><a href="'.$this->url.'/index.php?a=settings" rel="loadpage">'.$LNG['admin_ttl_edit'].'</a></span>' : '').'</div>';
		
		foreach($rows as $column => $value) {
			if($value[1]) {
				$info .= '<div class="sidebar-list"><div class="about-icon about-'.$value[0].'"></div>'.$column.': <strong>'.$value[1].'</strong></div>';
			}
		}
		
		$info .= '</div></div>';
		
		return $info;
	}
	
	function checkNewNotifications($limit, $type = null, $for = null, $ln = null, $cn = null, $sn = null, $fn = null, $dn = null, $bn = null, $gn = null) {
		global $LNG, $CONF;
		// $ln, $cn, $mn holds the filters for the notifications
		// Type 0: Just check for and show the new notification alert
		// Type 1: Return the last X notifications from each category. (Drop Down Notifications)
		// Type 2: Return the latest X notifications (read and unread) (Notifications Page)
		
		// For 0: Returns the Global Notifications
		// For 1: Return results for the Chat Messages Notifications (Drop Down)
		// For 2: Return Chat Messages results for the Notifications Page
		// For 3: Return the Friend Requsts Notifications (Drop Down)

		// Start checking for new notifications
		if(!$type) {
			// Check for new likes events
			if($ln) {
				$checkLikes = $this->db->query(sprintf("SELECT `id` FROM `notifications` WHERE `to` = '%s' AND `from` <> '%s' AND `type` = '2' AND `read` = '0'", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id)));
				
				$lc = $checkLikes->num_rows;
			}
			
			// Check for new comments events
			if($cn) {
				$checkComments = $this->db->query(sprintf("SELECT `id` FROM `notifications` WHERE `to` = '%s' AND `from` <> '%s' AND `type` = '1' AND `read` = '0'", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id)));
						
				// If any, return 1 (show notification)
				$cc = $checkComments->num_rows;
			}
			
			// Check for new messages events (shared messages)
			if($sn) {
				$checkShares = $this->db->query(sprintf("SELECT `id` FROM `notifications` WHERE `to` = '%s' AND `from` <> '%s' AND `type` = '3' AND `read` = '0'", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id)));
				
				// If any, return 1 (show notification)
				$sc = $checkShares->num_rows;
			}
			
			// Check for groups invitations
			if($gn) {
				$checkGroups = $this->db->query(sprintf("SELECT `id` FROM `notifications` WHERE `to` = '%s' AND `from` <> '%s' AND `type` = '6' AND `read` = '0'", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id)));
				
				$gc = $checkGroups->num_rows;
			}
			
			$checkFriends = $this->db->query(sprintf("SELECT `id` FROM `notifications` WHERE `to` = '%s' AND `from` <> '%s' AND `type` = '4' AND `read` = '0'", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id)));
				
			// If any, return 1 (show notification)
			$rc = $checkFriends->num_rows;
			
			// Check for new friend additions
			if($fn) {
				$confirmedFriends = $this->db->query(sprintf("SELECT `id` FROM `notifications` WHERE `to` = '%s' AND `from` <> '%s' AND `type` = '5' AND `read` = '0'", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id)));
				
				// If any, return 1 (show notification)
				$fc = $confirmedFriends->num_rows;
			}
			
			if($for) {
				if($dn) {
					$checkChats = $this->db->query(sprintf("SELECT `id` FROM `chat` WHERE `to` = '%s' AND `read` = '0'", $this->db->real_escape_string($this->id)));
					
					// If any, return 1 (show notification)
					$dc = $checkChats->num_rows;
				}
			}
			
			$output = array('response' => array('global' => $lc + $cc + $sc + $fc + $gc, 'messages' => $dc, 'friends' => $rc));
			return json_encode($output);
		} else {
			// Define the arrays that holds the values (prevents the array_merge to fail, when one or more options are disabled)
			$likes = array();
			$comments = array();
			$shares = array();
			$friends = array();
			$chats = array();
			$birthdays = array();
			$groups = array();
			
			if($type) {
				// Get the events and display all unread messages [applies only to the drop down widgets]
				if($for == 2 && $type !== 2 || !$for && $type !== 2) {
					if($ln) {
						// Check for new likes events
						$checkLikes = $this->db->query(sprintf("SELECT * FROM `notifications`,`users` WHERE `notifications`.`from` = `users`.`idu` AND `notifications`.`to` = '%s' and `notifications`.`from` <> '%s' AND `notifications`.`type` = '2' AND `notifications`.`read` = '0' ORDER BY `notifications`.`id` DESC", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id)));
						// Fetch the comments
						while($row = $checkLikes->fetch_assoc()) {
							$likes[] = $row;
						}
					}
					
					if($cn) {
						// Check for new comments events
						$checkComments = $this->db->query(sprintf("SELECT * FROM `notifications`,`users` WHERE `notifications`.`from` = `users`.`idu` AND `notifications`.`to` = '%s' and `notifications`.`from` <> '%s' AND `notifications`.`type` = '1' AND `notifications`.`read` = '0' ORDER BY `notifications`.`id` DESC", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id)));
						// Fetch the comments
						while($row = $checkComments->fetch_assoc()) {
							$comments[] = $row;
						}
					}
					
					if($sn) {
						// Check for new shared events
						$checkShares = $this->db->query(sprintf("SELECT * FROM `notifications`,`users` WHERE `notifications`.`from` = `users`.`idu` AND `notifications`.`to` = '%s' and `notifications`.`from` <> '%s' AND `notifications`.`type` = '3' AND `notifications`.`read` = '0' ORDER BY `notifications`.`id` DESC", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id)));
						// Fetch the messages
						while($row = $checkShares->fetch_assoc()) {
							$shares[] = $row;
						}
					}
					
					if($fn) {
						// Check for new shared events
						$checkFriends = $this->db->query(sprintf("SELECT * FROM `notifications`,`users` WHERE `notifications`.`from` = `users`.`idu` AND `notifications`.`to` = '%s' AND `notifications`.`from` <> '%s' AND `notifications`.`type` = '5' AND `notifications`.`read` = '0' ORDER BY `notifications`.`id` DESC", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id)));
						// Fetch the messages
						while($row = $checkFriends->fetch_assoc()) {
							$friends[] = $row;
						}
					}
					
					if($gn) {
						// Check for new group invitations
						$checkGroups = $this->db->query(sprintf("SELECT `notifications`.`id`, `notifications`.`from`, `notifications`.`to`, `notifications`.`parent`, `notifications`.`type`, `notifications`.`read`, `users`.`username`, `users`.`first_name`, `users`.`last_name`, `users`.`image`, `groups`.`title`, `groups`.`name` FROM `notifications`,`users`,`groups` WHERE `notifications`.`from` = `users`.`idu` AND `notifications`.`to` = '%s' and `notifications`.`from` <> '%s' AND `notifications`.`type` = '6' AND `notifications`.`read` = '0' AND `groups`.`id` = `notifications`.`parent` ORDER BY `notifications`.`id` DESC", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id)));
						// Fetch the comments
						while($row = $checkGroups->fetch_assoc()) {
							$groups[] = $row;
						}
					}
				}
				// Return the unread messages for drop-down notification (excludes $for 2 and $type 2)
				elseif($type !== 2 && $for == 1) {
					if($dn) {
						// Check for new messages events
						$checkChats = $this->db->query(sprintf("SELECT * FROM (SELECT * FROM `chat`,`users` WHERE `chat`.`to` = '%s' AND `chat`.`read` = '0' AND `chat`.`from` = `users`.`idu` ORDER BY `id` DESC) as x GROUP BY `from`", $this->db->real_escape_string($this->id)));
						
						// If there are no unread chat messages
						if($checkChats->num_rows < 1) {
							$checkChats = $this->db->query(sprintf("SELECT * FROM (SELECT * FROM `chat`,`users` WHERE `chat`.`to` = '%s' AND `chat`.`from` = `users`.`idu` ORDER BY `id` DESC) as x GROUP BY `from` LIMIT %s", $this->db->real_escape_string($this->id), $limit));
						}
						// Fetch the chat
						while($row = $checkChats->fetch_assoc()) {
							$chats[] = $row;
						}
					}
				}
				// Return the unread requests for the drop-down notifications (excludes $for 4 and $type 2)
				elseif($type !== 2 && $for == 3) {
					$checkFriends = $this->db->query(sprintf("SELECT * FROM `notifications`,`users` WHERE `notifications`.`from` = `users`.`idu` AND `notifications`.`to` = '%s' and `notifications`.`from` <> '%s' AND `notifications`.`type` = '4' ORDER BY `notifications`.`read` ASC, `notifications`.`id` DESC", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id)));
					// Fetch the messages
					while($row = $checkFriends->fetch_assoc()) {
						$friends[] = $row;
					}
				}
				
				// If there are no new (unread) notifications (for the drop-down widgets), get the lastest notifications
				if(!$for) {
					// Verify for the drop-down notifications
					if(empty($likes) && empty($comments) && empty($shares) && empty($friends) && empty($chats) && empty($groups) || $type == 2) {
						$all = 1;
					}
				}
				// For the Notifications Page
				elseif($for == 2 && $type == 2) {
					// Verify for the notifications page
					$all = 1;
				}
				elseif($for == 3 && $type == 1) {
					// Verify for the drop-down notifications
					if(empty($friends) || $type == 2) {
						$all = 1;
					}
				} elseif($for == 1 && $type == 1) {
					// Verify for the drop-down notifications
					if(empty($chats) || $type == 1) {
						$all = 1;
					}
				}
				
				if($all) {
					// LR: Enable limit rows when there are unread messages
					$lr = 1;
					// If the request is made for the Chat Messages, prevent loading the rest of the notifications
					if($for != 1) {
						if($ln) {
							$checkLikes = $this->db->query(sprintf("SELECT * FROM `notifications`,`users` WHERE `notifications`.`from` = `users`.`idu` AND `notifications`.`to` = '%s' and `notifications`.`from` <> '%s' AND `notifications`.`type` = '2' ORDER BY `notifications`.`id` DESC LIMIT %s", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id), $limit));
							
							while($row = $checkLikes->fetch_assoc()) {
								$likes[] = $row;
							}
						}
						
						if($cn) {
							$checkComments = $this->db->query(sprintf("SELECT * FROM `notifications`,`users` WHERE `notifications`.`from` = `users`.`idu` AND `notifications`.`to` = '%s' and `notifications`.`from` <> '%s' AND `notifications`.`type` = '1' ORDER BY `notifications`.`id` DESC LIMIT %s", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id), $limit));
							
							while($row = $checkComments->fetch_assoc()) {
								$comments[] = $row;
							}
						}
						
						if($sn) {
							$checkShares = $this->db->query(sprintf("SELECT * FROM `notifications`,`users` WHERE `notifications`.`from` = `users`.`idu` AND `notifications`.`to` = '%s' and `notifications`.`from` <> '%s' AND `notifications`.`type` = '3' ORDER BY `notifications`.`id` DESC LIMIT %s", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id), $limit));
							
							while($row = $checkShares->fetch_assoc()) {
								$shares[] = $row;
							}
						}
						
						if($gn) {
							$checkGroups = $this->db->query(sprintf("SELECT `notifications`.`id`, `notifications`.`from`, `notifications`.`to`, `notifications`.`parent`, `notifications`.`type`, `notifications`.`read`, `notifications`.`time`, `users`.`username`, `users`.`first_name`, `users`.`last_name`, `users`.`image`, `groups`.`title`, `groups`.`name` FROM `notifications`,`users`,`groups` WHERE `notifications`.`from` = `users`.`idu` AND `notifications`.`to` = '%s' and `notifications`.`from` <> '%s' AND `notifications`.`type` = '6' AND `groups`.`id` = `notifications`.`parent` ORDER BY `notifications`.`id` DESC LIMIT %s", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id), $limit));
							
							while($row = $checkGroups->fetch_assoc()) {
								$groups[] = $row;
							}
						}
					}
					// On the notifications center show the confirmed friendships
					if($for == 2) {
						if($fn) {
							$checkFriends = $this->db->query(sprintf("SELECT * FROM `notifications`,`users` WHERE `notifications`.`from` = `users`.`idu` AND `notifications`.`to` = '%s' and `notifications`.`from` <> '%s' AND `notifications`.`type` = '5' ORDER BY `notifications`.`id` DESC LIMIT %s", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id), $limit));
							
							while($row = $checkFriends->fetch_assoc()) {
								$friends[] = $row;
							}
						}
						if($bn) {
							$friendslist = $this->getFriendsList(1);
							if(!empty($friendslist)) {
								$checkBirthdays = $this->db->query(sprintf("SELECT * FROM `users` WHERE EXTRACT(MONTH FROM `born`) = '%s' AND EXTRACT(DAY FROM `born`) = '%s' AND `idu` IN (%s)", date('m'), date('d'), $this->getFriendsList(1)));
								
								while($row = $checkBirthdays->fetch_assoc()) {
									$birthdays[] = $row;
								}
							}
						}
					}
					// On the notifications widget show the unconfirmed friendships
					else {
						// Make the request only if is for the global notifications widget (avoids showing up in the friends requests widget)
						if(!$for) {
							if($fn) {
								$checkFriends = $this->db->query(sprintf("SELECT * FROM `notifications`,`users` WHERE `notifications`.`from` = `users`.`idu` AND `notifications`.`to` = '%s' and `notifications`.`from` <> '%s' AND `notifications`.`type` = '5' ORDER BY `notifications`.`id` DESC LIMIT %s", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id), $limit));
								
								while($row = $checkFriends->fetch_assoc()) {
									$friends[] = $row;
								}
							}
						}
					}
					
					if($for == 2) {
						if($dn) {
							$checkChats = $this->db->query(sprintf("SELECT * FROM (SELECT * FROM `chat`,`users` WHERE `chat`.`to` = '%s' AND `chat`.`from` = `users`.`idu` ORDER BY `id` DESC) as x GROUP BY `from` LIMIT %s", $this->db->real_escape_string($this->id), $limit));
						
							while($row = $checkChats->fetch_assoc()) {
								$chats[] = $row;
							}
						}
					}
					
					// If there are no latest notifications
					if($for == 2) {
						// Verify for the notifications page
						if(empty($likes) && empty($comments) && empty($shares) && empty($friends) && empty($chats) && empty($birthdays) && empty($groups)) {
							return '<div class="notification-row"><div class="notification-padding">'.$LNG['no_notifications'].'</a></div></div><div class="notification-row"><div class="notification-padding"><a href="'.$this->url.'/index.php?a=settings&b=notifications" rel="loadpage">'.$LNG['notifications_settings'].'</a></div></div>';
						}
					} else {
						// Verify for the drop-down notifications
						if($for == 3) {
							$likes = array(); $comments = array(); $shares = array(); $chats = array(); $groups = array();
						}
						if(empty($likes) && empty($comments) && empty($shares) && empty($friends) && empty($chats) && empty($groups)) {
							return '<div class="notification-row"><div class="notification-padding">'.$LNG['no_notifications'].'</a></div></div>';
						}
					}
				}
			}
			
			// Add the types into the recursive array results
			$x = 0;
			foreach($likes as $like) {
				$likes[$x]['event'] = 'like';
				$x++;
			}
			$y = 0;
			foreach($comments as $comment) {
				$comments[$y]['event'] = 'comment';
				$y++;
			}
			$z = 0;
			foreach($shares as $share) {
				$shares[$z]['event'] = 'shared';
				$z++;
			}
			$a = 0;
			foreach($friends as $friend) {
				$friends[$a]['event'] = 'friend';
				$a++;
			}
			$b = 0;
			foreach($chats as $chat) {
				$chats[$b]['event'] = 'chat';
				$b++;
			}
			$c = 0;
			foreach($birthdays as $birthday) {
				$birthdays[$c]['event'] = 'birthday';
				$c++;
			}
			$d = 0;
			foreach($groups as $group) {
				$groups[$d]['event'] = 'group';
				$d++;
			}
			
			$array = array_merge($likes, $comments, $shares, $friends, $chats, $birthdays, $groups);

			// Sort the array
			usort($array, 'sortDateAsc');
			
			$i = 0;
			$currentTime = time();
			foreach($array as $value) {
				if($i == $limit && $lr == 1) break;
				$time = $value['time']; $b = '';
				if($this->time == '0') {
					$time = date("c", strtotime($value['time']));
				} elseif($this->time == '2') {
					$time = $this->ago(strtotime($value['time']));
				} elseif($this->time == '3') {
					$date = strtotime($value['time']);
					$time = date('Y-m-d', $date);
					$b = '-standard';
				}
				$events .= '<div class="notification-row'.((($value['read'] == 0 && $value['event'] == 'chat') || ($value['read'] == 0 && $value['event'] == 'friend' && $for == 3)) ? ' notification-unread' : '').'" id="notification'.$value['id'].'"><div class="notification-padding">';
				if($value['event'] == 'like') {
					$events .= '<div class="notification-image"><a href="'.$this->url.'/index.php?a=profile&u='.$value['username'].'" rel="loadpage"><img class="notifications" src='.$this->url.'/thumb.php?src='.$value['image'].'&t=a&w=50&h=50"></a></div><div class="notification-text">'.sprintf(($value['child'] ? $LNG['new_like_c_notification'] : $LNG['new_like_notification']), $this->url.'/index.php?a=profile&u='.$value['username'], realName($value['username'], $value['first_name'], $value['last_name']), ($value['child'] ? $this->url.'/index.php?a=post&m='.$value['parent'].'#comment'.$value['child'] : $this->url.'/index.php?a=post&m='.$value['parent'])).'.<br><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/n_like.png" width="16" height="16"><span class="timeago'.$b.'" title="'.$time.'">'.$time.'</span></div>';
				} elseif($value['event'] == 'comment') {
					$events .= '<div class="notification-image"><a href="'.$this->url.'/index.php?a=profile&u='.$value['username'].'" rel="loadpage"><img class="notifications" src='.$this->url.'/thumb.php?src='.$value['image'].'&t=a&w=50&h=50"></a></div><div class="notification-text">'.sprintf($LNG['new_comment_notification'], $this->url.'/index.php?a=profile&u='.$value['username'], realName($value['username'], $value['first_name'], $value['last_name']), $this->url.'/index.php?a=post&m='.$value['parent'].'#'.$value['child']).'.<br><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/n_comment.png" width="16" height="16"><span class="timeago'.$b.'" title="'.$time.'">'.$time.'</span></div>';
				} elseif($value['event'] == 'shared') {
					$events .= '<div class="notification-image"><a href="'.$this->url.'/index.php?a=profile&u='.$value['username'].'" rel="loadpage"><img class="notifications" src='.$this->url.'/thumb.php?src='.$value['image'].'&t=a&w=50&h=50"></a></div><div class="notification-text">'.sprintf($LNG['new_shared_notification'], $this->url.'/index.php?a=profile&u='.$value['username'], realName($value['username'], $value['first_name'], $value['last_name']), $this->url.'/index.php?a=post&m='.$value['child']).'.<br><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/n_shared.png" width="16" height="16"><span class="timeago'.$b.'" title="'.$time.'">'.$time.'</span></div>';
				} elseif($value['event'] == 'group') {
					$events .= '<div class="notification-image"><a href="'.$this->url.'/index.php?a=profile&u='.$value['username'].'" rel="loadpage"><img class="notifications" src='.$this->url.'/thumb.php?src='.$value['image'].'&t=a&w=50&h=50"></a></div><div class="notification-text">'.sprintf($LNG['new_group_notification'], $this->url.'/index.php?a=profile&u='.$value['username'], realName($value['username'], $value['first_name'], $value['last_name']), $this->url.'/index.php?a=group&name='.$value['name'], $value['title']).'.<br><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/n_group.png" width="16" height="16"><span class="timeago'.$b.'" title="'.$time.'">'.$time.'</span></div>';
				} elseif($value['event'] == 'friend') {
					if($for == 2 || !$for) {
						$events .= '<div class="notification-image"><a href="'.$this->url.'/index.php?a=profile&u='.$value['username'].'" rel="loadpage"><img class="notifications" src='.$this->url.'/thumb.php?src='.$value['image'].'&t=a&w=50&h=50"></a></div><div class="notification-text">'.sprintf($LNG['new_friend_notification'], $this->url.'/index.php?a=profile&u='.$value['username'], realName($value['username'], $value['first_name'], $value['last_name']), $this->url.'/index.php?a=post&m='.$value['child']).'.<br><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/n_friendship.png" width="16" height="16"><span class="timeago'.$b.'" title="'.$time.'">'.$time.'</span></div>';
					} else {
						$events .= '<div class="notification-image"><a href="'.$this->url.'/index.php?a=profile&u='.$value['username'].'"><img class="notifications" src='.$this->url.'/thumb.php?src='.$value['image'].'&t=a&w=50&h=50"></a></div><div class="notification-text notification-friendships"><a href="'.$this->url.'/index.php?a=profile&u='.$value['username'].'" rel="loadpage">'.realName($value['username'], $value['first_name'], $value['last_name']).'</a><br><span class="timeago'.$b.'" title="'.$time.'">'.$time.'</span></div><div class="notification-buttons" id="notification-buttons'.$value['id'].'"><div class="notification-button button-normal"><a onclick="friend(\''.$value['idu'].'\', \'3\', \''.$value['id'].'\')">'.$LNG['decline'].'</a></div><div class="notification-button button-active"><a onclick="friend(\''.$value['idu'].'\', \'2\', \''.$value['id'].'\')">'.$LNG['confirm'].'</a></div></div>';
					}
				} elseif($value['event'] == 'chat') {
					if(($currentTime - $value['online']) > $this->online_time) {
						$icon = 'offline';
					} else {
						$icon = 'online';
					}
					$events .= '<div class="notification-image"><a href="'.$this->url.'/index.php?a=profile&u='.$value['username'].'" rel="loadpage"><img class="notifications" src='.$this->url.'/thumb.php?src='.$value['image'].'&t=a&w=50&h=50"></a></div><div class="notification-text">'.sprintf($LNG['new_chat_notification'], $this->url.'/index.php?a=profile&u='.$value['username'], realName($value['username'], $value['first_name'], $value['last_name']), 'openChatWindow(\''.$value['idu'].'\', \''.$value['username'].'\', \''.addslashes(realName($value['username'], $value['first_name'], $value['last_name'])).'\', \''.$this->url.'\', \''.$this->url.'/'.$CONF['theme_url'].'/images/icons/'.$icon.'.png\')', $this->url.'/index.php?a=messages&u='.$value['username'].'&id='.$value['idu']).'<br><span class="chat-snippet">'.$this->parseMessage(substr($value['message'], 0, 45)).'...</span><br><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/n_chat.png" width="16" height="16"><span class="timeago'.$b.'" title="'.$time.'">'.$time.'</span></div>';
				} elseif($value['event'] == 'birthday') {
					// Explode the born value [[0]=>Y,[1]=>M,[2]=>D];
					$born = explode('-', $value['born']);
					
					$events .= '<div class="notification-image"><a href="'.$this->url.'/index.php?a=profile&u='.$value['username'].'" rel="loadpage"><img class="notifications" src='.$this->url.'/thumb.php?src='.$value['image'].'&t=a&w=50&h=50"></a></div><div class="notification-text">'.sprintf($LNG['new_birthday_notification'], $this->url.'/index.php?a=profile&u='.$value['username'], realName($value['username'], $value['first_name'], $value['last_name'])).'.<br><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/n_birthday.png" width="16" height="16"><span class="timeago">'.sprintf($LNG['years_old'], (date('Y')-$born[0])).'</span></div>';
				}
				$events .= '</div></div>';
				$i++;
			}
			
			if(!$for) {
				// Mark global notifications as read
				$this->db->query("UPDATE `notifications` SET `read` = '1', `time` = `time` WHERE `to` = '{$this->id}' AND `read` = '0' AND `type` <> '4'");
			}
			elseif($for == 3) {
				// Mark friend notifications as read
				$this->db->query("UPDATE `notifications` SET `read` = '1', `time` = `time` WHERE `to` = '{$this->id}' AND `read` = '0' AND `type` = '4'");
			}
			// Update when the $for is set, and it's not viewed from the Notifications Page
			elseif($type !== 2) {
				// Mark chat messages notifications as read
				$this->db->query("UPDATE `chat` SET `read` = '1', `time` = `time` WHERE `to` = '{$this->id}' AND `read` = '0'");
			}
			// return the result
			return $events;
		}
		// If no notification was returned, return 0
	}
	
	function chatButton($id, $username, $z = null) {
		// Profile: Returns the current row username
		// Z: A switcher for the sublist CSS class
		global $LNG;
		if($z == 1) {
			$style = ' subslist_message';
		}
		if(!empty($this->username) && $this->username !== $username) {
			return '<a href="'.$this->url.'/index.php?a=messages&u='.$username.'&id='.$id.'" title="'.$LNG['send_message'].'" rel="loadpage"><div class="message_btn'.$style.'"></div></a>';
		}
	}
	
	function friendship($type = null, $list = null, $z = null) {
		global $LNG;
		// Type 0: Show the button
		// Type 1: Go trough the add friend query
		// List: Array (for the dedicated profile page list)
		// $z 1: A switcher for the sublist CSS class
		// $z 2: Request from the notifications widget to confirm the friendship
		// $z 3: Request from the notifications widget to decline the friendship
		
		// Return if the user is not logged in
		if(!$this->id) {
			return false;
		}
		if($list) {
			$profile = $list;
		} else {
			$profile = $this->profile_data;
		}
		
		// Verify if the username is logged in, and it's not the same with the viewed profile
		if(!empty($this->username) && $this->username !== $profile['username']) {
			if($z == 1) {
				$style = ' subslist';
			}
			
			if($type) {
				$friendship = $this->verifyFriendship($this->id, $this->db->real_escape_string($profile['idu']));
				// If the friendship status is confirmed OR if the friendship status is pending and the sender is the owner OR the request is to delete the friendship request then cancel the friendship
				if($friendship['status'] == '1' || ($friendship['status'] == '0' && $friendship['from'] == $this->id) || ($friendship['to'] == $this->id && $type == 3)) {
					$result = $this->db->query(sprintf("DELETE FROM `friendships` WHERE (`user1` = '%s' AND `user2` = '%s') OR (`user1` = '%s' AND `user2` = '%s')", $this->db->real_escape_string($this->id), $this->db->real_escape_string($profile['idu']), $this->db->real_escape_string($profile['idu']), $this->db->real_escape_string($this->id)));
					
					$deleteNotification = $this->db->query(sprintf("DELETE FROM `notifications` WHERE ((`from` = '%s' AND `to` = '%s') OR (`from` = '%s' AND `to` = '%s')) AND `type` IN (4,5)", $this->db->real_escape_string($this->id), $this->db->real_escape_string($profile['idu']), $this->db->real_escape_string($profile['idu']), $this->db->real_escape_string($this->id)));
					
					// If the decline was done from the notifications widget
					if($type == 3) {
						return '<div class="notification-button button-normal"><a href="'.$this->url.'/index.php?a=profile&u='.$profile['username'].'" target="_blank">'.$LNG['declined'].'</a></div>';
					}
				}
				// If there is a pending invitation
				elseif($friendship['status'] == '0' && $friendship['to'] == $this->id && ($type == 1 || $type == 2)) {
					$result = $this->db->query(sprintf("UPDATE `friendships` SET `status` = '1' WHERE (`user1` = '%s' AND `user2` = '%s') OR (`user1` = '%s' AND `user2` = '%s')", $this->db->real_escape_string($this->id), $this->db->real_escape_string($profile['idu']), $this->db->real_escape_string($profile['idu']), $this->db->real_escape_string($this->id)));
					
					// If user has emails on new friendships enabled
					if($profile['email_new_friend']) {
						// Send e-mail
						sendMail($profile['email'], sprintf($LNG['ttl_friendship_confirmed_email'], $this->username), sprintf($LNG['friendship_confirmed_email'], realName($profile['username'], $profile['first_name'], $profile['last_name']), $this->url.'/index.php?a=profile&u='.$this->username, $this->username, $this->title, $this->title, $this->url.'/index.php?a=settings&b=notifications'), $this->email);
					}
					
					$updateNotification = $this->db->query(sprintf("UPDATE `notifications` SET `type` = '5', `read` = 0, `to` = '%s', `from` = '%s' WHERE `from` = '%s' AND `to` = '%s' AND `type` = 4", $this->db->real_escape_string($profile['idu']), $this->db->real_escape_string($this->id), $this->db->real_escape_string($profile['idu']), $this->db->real_escape_string($this->id)));
					
					// If the approve was done from the notifications widget
					if($type == 2) {
						return '<div class="notification-button button-normal"><a href="'.$this->url.'/index.php?a=profile&u='.$profile['username'].'" target="_blank">'.$LNG['confirmed'].'</a></div>';
					}
				}
				// If there are no friendship relations
				else {
					// If the user is not blocked
					if(!$this->getBlocked($profile['idu'], 2)) {
						$result = $this->db->query(sprintf("INSERT INTO `friendships` (`user1`, `user2`, `time`) VALUES ('%s', '%s', CURRENT_TIMESTAMP)", $this->db->real_escape_string($this->id), $this->db->real_escape_string($profile['idu'])));
						
						$insertNotification = $this->db->query(sprintf("INSERT INTO `notifications` (`from`, `to`, `type`, `read`) VALUES ('%s', '%s', '4', '0')", $this->db->real_escape_string($this->id), $profile['idu']));
						
						if($this->email_new_friend) {
							// If user has emails on new friendships enabled
							if($profile['email_new_friend']) {
								// Send e-mail
								sendMail($profile['email'], sprintf($LNG['ttl_new_friend_email'], $this->username), sprintf($LNG['new_friend_email'], realName($profile['username'], $profile['first_name'], $profile['last_name']), $this->url.'/index.php?a=profile&u='.$this->username, $this->username, $this->title, $this->title, $this->url.'/index.php?a=settings&b=notifications'), $this->email);
							}
						}
					}
				}
			}
		} else {
			return false;
		}
		
		$friendship = $this->verifyFriendship($this->id, $this->db->real_escape_string($profile['idu']));
		
		if($friendship['status'] == '1') {
			return '<div class="friend-button friend-remove'.$style.'" title="'.$LNG['remove_friend'].'" onclick="friend('.$profile['idu'].', 1'.(($z == 1) ? ', 1' : '').')"></div>';
		} elseif($friendship['status'] == '0') {
			return '<div class="friend-button friend-pending'.$style.'" title="'.(($this->id == $friendship['from']) ? $LNG['friend_request_sent'] : $LNG['friend_request_accept']).'" onclick="friend('.$profile['idu'].', 1'.(($z == 1) ? ', 1' : '').')"></div>';
		} else {
			return '<div class="friend-button'.$style.'" title="'.$LNG['add_friend'].'" onclick="friend('.$profile['idu'].', 1'.(($z == 1) ? ', 1' : '').')"></div>';
		}
	}
	
	function showError($error) {
		global $LNG;
		$message = '<div class="message-container"><div class="message-content"><div class="message-header">'.$LNG[$error.'_ttl'].'</div><div class="message-inner">'.$LNG["$error"].'</div></div></div>';
		
		return array($message, 1);
	
	}
	
	function verifyFriendship($user_id, $profile_id) {
		if($user_id == $profile_id) {
			$result = array();
			$result['status'] = 'owner';
		} else {
			$query = $this->db->query(sprintf("SELECT * FROM `friendships` WHERE ((`user1` = '%s' AND `user2` = '%s') OR (`user1` = '%s' AND `user2` = '%s'))", $this->db->real_escape_string($user_id), $this->db->real_escape_string($profile_id), $this->db->real_escape_string($profile_id), $this->db->real_escape_string($user_id)));		
		
			$result = $query->fetch_assoc();
		}
		
		// Returns the friendship status
		// Status: 	0 Pending
		//			1 Confirmed
		
		return array(	'status'	=> $result['status'],
						'from'		=> $result['user1'],
						'to'		=> $result['user2']);
	}

	function getMessage($id) {
		$query = $this->db->query(sprintf("SELECT `idu`,`username`,`private`,`public` FROM `messages`, `users` WHERE `messages`.`id` = '%s' AND `messages`.`uid` = `users`.`idu`", $this->db->real_escape_string($id)));
		$result = $query->fetch_assoc();

		// If the current user is not the owner of the message
		if($result['idu'] !== $this->id) {
			$friendship = $this->verifyFriendship($this->id, $result['idu']);

			// Verify if the message
			if(!$result['public']) {
				$private = 1;
			} elseif($result['public'] == 2 && $friendship['status'] !== '1') {
				$private = 1;
			}
		}
		
		if($this->is_admin) {
			$private = 0;
		}
		
		if($private) {
			return $this->showError(($result['public'] == 2) ? 'message_semi_private' : 'message_private');
		} else {
			// Get the message for Messages Page
			$query = sprintf("SELECT * FROM messages, users WHERE messages.id = '%s' AND messages.uid = users.idu", $this->db->real_escape_string($id));
			
			return $this->getMessages($query, null, null);
		}
	}
	
	function getLastMessage() {
		$query = sprintf("SELECT * FROM `messages`, `users` WHERE `uid` = '%s' AND `messages`.`uid` = `users`.`idu` ORDER BY `id` DESC LIMIT 0, 1", $this->db->real_escape_string($this->id));
		
		$message = $this->getMessages($query, null, null);
		return $message[0];
	}
	
	function getComments($id, $cid, $start) {
		// The query to select the subscribed users
		
		// If the $start value is 0, empty the query;
		if($start == 0) {
			$start = '';
		} else {
			// Else, build up the query
			$start = 'AND comments.id < \''.$this->db->real_escape_string($cid).'\'';
		}
		$query = sprintf("SELECT * FROM comments, users WHERE comments.mid = '%s' AND comments.uid = users.idu %s ORDER BY comments.id DESC LIMIT %s", $this->db->real_escape_string($id), $start, ($this->c_per_page + 1));
	
		return $this->comments($query, array('id' => $id, 'start' => $start));
	}
	
	function getLastComment() {
		$query = sprintf("SELECT * FROM `comments`, `users` WHERE `uid` = '%s' AND `comments`.`uid` = `users`.`idu` ORDER BY `id` DESC LIMIT 0, 1", $this->db->real_escape_string($this->id));
		
		return $this->comments($query);
	}
	
	function comments($query, $values = null) {
		global $LNG;
		// check if the query was executed
		if($result = $this->db->query($query)) {
			
			// Set the result into an array
			$rows = array();
			while($row = $result->fetch_assoc()) {
				$rows[] = $row;
			}
			$rows = array_reverse($rows);
			
			// Define the $comments variable;
			$comments = '';
			
			// If there are more results available than the limit, then show the Load More Comments
			if(array_key_exists($this->c_per_page, $rows)) {
				$loadmore = 1;
				
				// Unset the first array element because it's not needed, it's used only to predict if the Load More Comments should be displayed
				unset($rows[0]);
			}
			
			foreach($rows as $comment) {
				// Verify the Like state
				$verify = $this->verifyLike($comment['id'], 1);
				
				if($verify) {
					$state = $LNG['dislike'];
					$y = 2;
				} else {
					$state = $LNG['like'];
					$y = 1;
				}
				
				// Define the time selected in the Admin Panel
				$time = $comment['time']; $b = '';
				if($this->time == '0') {
					$time = date("c", strtotime($comment['time']));
				} elseif($this->time == '2') {
					$time = $this->ago(strtotime($comment['time']));
				} elseif($this->time == '3') {
					$date = strtotime($comment['time']);
					$time = date('Y-m-d', $date);
					$b = '-standard';
				}
				
				if($this->username == $comment['username']) { // If it's current username is the same with the current author
					$delete = '<a onclick="deleteModal('.$comment['id'].', 0)" title="'.$LNG['delete'].'"><div class="delete_btn"></div></a>';
				} elseif(empty($this->username)) { // If the user is not registered
					$delete = '';
				} else { // If the current username is not the same as the author
					$delete = '<a onclick="report_the('.$comment['id'].', 0)" title="'.$LNG['report_this_comment'].'"><div class="report_btn"></div></a>';
				}
				
				// Variable which contains the result
				$comments .= '
				<div class="message-reply-container" id="comment'.$comment['id'].'">
					'.$delete.'
					<div class="message-reply-avatar">
						<a href="'.$this->url.'/index.php?a=profile&u='.$comment['username'].'" rel="loadpage"><img onmouseover="profileCard('.$comment['idu'].', '.$comment['id'].', 1, 0)" onmouseout="profileCard(0, 0, 1, 1);" onclick="profileCard(0, 0, 1, 1);" src="'.$this->url.'/thumb.php?src='.$comment['image'].'&t=a"></a>
					</div>
					<div class="message-reply-message">
						<span class="message-reply-author"><a href="'.$this->url.'/index.php?a=profile&u='.$comment['username'].'" rel="loadpage">'.realName($comment['username'], $comment['first_name'], $comment['last_name']).'</a></span>: '.$this->parseMessage($comment['message']).'
					</div>
					<div class="message-reply-footer">
						<div class="message-time">
							'.($this->id ? '<span class="like-comment"><a onclick="doLike('.$comment['id'].', 1)" id="doLikeC'.$comment['id'].'">'.$state.'</a> -&nbsp;</span>' : '').'
							<div class="timeago'.$b.'" title="'.$time.'">
								'.$time.'
							</div>
							<div class="like_btn" id="like_c_btn'.$comment['id'].'"> <a onclick="likesModal('.$comment['id'].', 1)" title="'.$LNG['view_all_likes'].'">'.$comment['likes'].'</a></div>
						</div>
					</div>
					<div class="delete_preloader" id="del_comment_'.$comment['id'].'"></div>
					
				</div>';
				$message_id = $comment['mid'];
			}
			
			if($loadmore && $this->c_per_page) {
				$load = '<div class="load-more-comments" id="more_comments_'.htmlentities($values['id'], ENT_QUOTES).'"><a onclick="loadComments('.$message_id.', '.$rows[1]['id'].', '.($values['start'] + $this->c_per_page).')">'.$LNG['view_more_comments'].'</a></div>';
			}
					
			// Close the query
			$result->close();
			
			// Return the comments variable
			return $load.$comments;
		} else {
			return false;
		}
	}
	
	function like($id, $type, $action = null) {
		global $LNG;
		// Type 0: Like Message
		// Type 1: Like Comment
		
		if($type == 1) {
			$table = 'comments';
		} else {
			$table = 'messages';
		}
		
		// Select the comment's likes (if the comments exists)
		$query = $this->db->query(sprintf("SELECT * FROM `users`, `%s` WHERE `%s`.`id` = '%s' AND `%s`.`uid` = `users`.`idu`", $table, $table, $this->db->real_escape_string($id), $table));
		$post = $query->fetch_assoc();
		
		// If the comment does not exists
		if(empty($post['id'])) {
			return false;
		}
		
		// Select the likes, if any
		$query = $this->db->query(sprintf("SELECT * FROM `likes`, `users` WHERE `likes`.`post` = '%s' AND `likes`.`type` = '%s' AND `likes`.`by` = '%s' AND `likes`.`by` = `users`.`idu`", $this->db->real_escape_string($id),  $this->db->real_escape_string($type), $this->db->real_escape_string($this->id)));

		// If a like already exists, dislike
		if($query->num_rows > 0) {
			$this->db->query(sprintf("DELETE FROM `likes` WHERE `type` = '%s' AND `post` = '%s' AND `by` = '%s'", $this->db->real_escape_string($type), $this->db->real_escape_string($id), $this->db->real_escape_string($this->id)));
			$this->db->query(sprintf("UPDATE `%s` SET `likes` = `likes` -1, `time` = `time` WHERE id = '%s'", $table, $this->db->real_escape_string($id)));
			$value = $LNG['like'];
			$action = 0;
		} else {
			$this->db->query(sprintf("INSERT INTO `likes` (`post`, `by`, `type`) VALUES ('%s', '%s', '%s')", $this->db->real_escape_string($id), $this->db->real_escape_string($this->id), $this->db->real_escape_string($type)));
			$this->db->query(sprintf("UPDATE `%s` SET `likes` = `likes` + 1, `time` = `time` WHERE id = '%s'", $table, $this->db->real_escape_string($id)));
			$value = $LNG['dislike'];
			$action = 1;
		}
		
		if($type == 1) {
			$parent = $post['mid'];
			$child = $post['id'];
			$email_url = $this->url.'/index.php?a=post&m='.$parent.'#comment'.$child;
			$email_content = $LNG['like_c_email'];
			$email_title = $LNG['ttl_like_c_email'];
			$likes = $this->getLikers($child, ($action == 1 ? $post['likes'] + 1 : $post['likes'] - 1), 1);
		} else {
			$parent = $post['id'];
			$child = 0;
			$email_url = $this->url.'/index.php?a=post&m='.$parent;
			$email_content = $LNG['like_email'];
			$email_title = $LNG['ttl_like_email'];
			$likes = $this->getLikers($parent, ($action == 1 ? $post['likes'] + 1 : $post['likes'] - 1));
		}
		
		// If the action is "Like"
		if($action > 0) {
			$this->db->query(sprintf("INSERT INTO `notifications` (`from`, `to`, `parent`, `child`, `type`, `read`) VALUES ('%s', '%s', '%s', '%s', '2', '0')", $this->db->real_escape_string($this->id), $post['uid'], $parent, $child));
	
			// If email on likes is enabled in admin settings
			if($this->email_like) {
				// If user has emails on like enabled and it\'s not liking his own post
				if($post['email_like'] && ($this->id !== $post['idu'])) {
					// Send e-mail
					sendMail($post['email'], sprintf($email_title, $this->username), sprintf($email_content, realName($post['username'], $post['first_name'], $post['last_name']), $this->url.'/index.php?a=profile&u='.$this->username, $this->username, $email_url, $this->title, $this->url.'/index.php?a=settings&b=notifications'), $this->email);
				}
			}
		} else {
			$this->db->query(sprintf("DELETE FROM `notifications` WHERE `parent` = '%s' AND `child` = '%s' AND `type` = '2' AND `from` = '%s'", $parent, $child, $this->db->real_escape_string($this->id)));
		}
		
		// Return the output
		return json_encode(array('value' => $value, 'count' => $likes));
	}
	
	function getLikers($id, $count, $type = null) {
		global $LNG;
		if($type) {
			return ' <a onclick="likesModal('.$id.', 1)" title="'.$LNG['view_all_likes'].'">'.$count.'</a>';
		}
		// If the post has any likes and the l_per_post is enabled
		if($this->l_per_post && $count > 0) {
			$query = sprintf("SELECT * FROM `likes`,`users` WHERE `post` = '%s' AND `likes`.`type` = 0 AND `likes`.`by` = `users`.`idu` ORDER BY `likes`.`id` DESC LIMIT %s", $this->db->real_escape_string($id), $this->db->real_escape_string($this->l_per_post));
		
			$result = $this->db->query($query);
			while($row = $result->fetch_assoc()) {
				$array[] = $row;
			}
			
			// Define the $people who liked variable
			if(is_array($array)) {
				$people = '<span class="likes-container">';
				foreach($array as $row) {
					$people .= '<a href="'.$this->url.'/index.php?a=profile&u='.$row['username'].'" rel="loadpage"><img src="'.$this->url.'/thumb.php?src='.$row['image'].'&w=25&h=25&t=a" title="'.realName($row['username'], $row['first_name'], $row['last_name']).' '.$LNG['liked_this'].'"></a> ';
				}
				$people .= '</span>';
			}
		}
		return $people.'<a title="'.$LNG['view_all_likes'].'" onclick="likesModal('.$id.', 0)">'.$count.'</a>';
	}
	
	function parseMessage($message) {
		global $LNG, $CONF;
		
		// Parse links
		$parseUrl = preg_replace_callback('/(?i)\b((?:https?:\/\/|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}\/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))/', "parseCallback", $message);
		
		// Parse @mentions and #hashtags
		$parsedMessage = preg_replace(array('/(^|[^a-z0-9_])@([a-z0-9_]+)/i', '/(^|[^a-z0-9_])#(\w+)/u'), array('$1<a href="'.$this->url.'/index.php?a=profile&u=$2" rel="loadpage">@$2</a>', '$1<a href="'.$this->url.'/index.php?a=search&tag=$2" rel="loadpage">#$2</a>'), $parseUrl);
		
		// Define the censored words
		$censored = explode(',', $this->censor);
		
		// Strip any html tags except anchors, and replace any bad words
		$parsedMessage = str_replace($censored, $LNG['censored'], $parsedMessage);
		
		// Define smiles
		$smiles = smiles();
		
		if($this->smiles) {
			foreach($smiles as $smile => $img) {
				$parsedMessage = str_replace($smile, '<img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/emoticons/'.$img.'" height="14" width="14">', $parsedMessage);
			}
		}

		return $parsedMessage;
	}
	
	function getType($type, $value, $id) {
		global $LNG, $CONF;
		
		foreach($this->plugins as $plugin) {
			if(array_intersect(array("1"), str_split($plugin['type']))) {
				$po .= plugin($plugin['name'], array('id' => $id, 'type' => $type, 'value' => $value, 'user_id' => $this->id), 1);
			}
		}
		
		if($po) {
			return $po;
		}
		
		// Switch the case
		switch($type) {
		
			// If it's a map
			case "map":
				return '<div class="message-type-map event-map"><img src="https://maps.googleapis.com/maps/api/staticmap?center='.$value.'&zoom=13&size=700x150&maptype=roadmap&markers=color:red%7C'.$value.'&sensor=false&scale=2&visual_refresh=true"></div>
				<div class="message-divider"></div>';
				break;
			
			// If it's a ate action
			case "food":
				return '<div class="message-type-general event-food"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/events/food.png">'.sprintf($LNG['food'], $value).'</div>
				<div class="message-divider"></div>';
				break;
				
			// If it's a visit action
			case "visited":
				return '<div class="message-type-general event-visited"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/events/visited.png">'.sprintf($LNG['visited'], $value).'</div>
				<div class="message-divider"></div>';
				break;
			
			// If it's a game action
			case "game":
				return '<div class="message-type-general event-game"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/events/game.png">'.sprintf($LNG['played'], $value).'</div>
				<div class="message-divider"></div>';
				break;
			
			// If it's a music/song action
			case "music":
				// Explode each slash to determine the /username or find the users/ into the string [switch the height]
				$count = explode('/', $value);
				if(count($count) <= 2 || strpos($value, 'users/') !== false) {
					$height = '380';
				} else {
					$height = '120';
				}
				if(substr($value, 0, 3) == 'sc:') {
					return '<iframe width="100%" height="'.$height.'" scrolling="no" frameborder="no" src="https://w.soundcloud.com/player/?url=https://soundcloud.com'.str_replace('sc:', '', $value).'"></iframe><div class="message-divider"></div>';
				} else {
					return '<div class="message-type-general event-music"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/events/music.png">'.sprintf($LNG['listened'], $value).'</div>
					<div class="message-divider"></div>';
				}
				break;
				
			// If it's a picture
			case "picture":
				$images = explode(',', $value);
				if(count($images) == 1) {
					$result .= '<div class="message-type-image event-picture">';
					$i = 0;
					foreach($images as $image) {
						$result .= '<a onclick="gallery(\''.$image.'\', '.$id.', \'media\')" id="'.$image.'"><img src="'.$this->url.'/thumb.php?src='.$image.'&w=650&h=300&t=m"></a>';
						$i++;
					}
				} else {
					$result .= '<div class="message-type-image event-picture"><div class="image-container-padding">';
					$i = 0;
					foreach($images as $image) {
						$result .= '<a onclick="gallery(\''.$image.'\', '.$id.', \'media\')" id="'.$image.'"><div class="image-thumbnail-container"><div class="image-thumbnail"><img src="'.$this->url.'/thumb.php?src='.$image.'&w=204&h=204&t=m"></div></div></a>';
						$i++;
					}
					$result .= '</div>';
				}
				return $result.'</div><div class="message-divider"></div>';
				break;

			// If it's a video
			case "video":
				if(substr($value, 0, 3) == 'yt:') {
					return '<div class="message-type-player event-video"><iframe width="100%" height="315" src="//www.youtube.com/embed/'.str_replace('yt:', '', $value).'" frameborder="0" allowfullscreen></iframe></div>
					<div class="message-divider"></div>';
				} elseif(substr($value, 0, 3) == 'vm:') {
					return '<div class="message-type-player event-video"><iframe width="100%" height="315" src="//player.vimeo.com/video/'.str_replace('vm:', '', $value).'" frameborder="0" allowfullscreen></iframe></div>
					<div class="message-divider"></div>';
				} else {
					return '<div class="message-type-general event-video"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/events/video.png">'.sprintf($LNG['watched'], $value).'</div>
					<div class="message-divider"></div>';
				}
				
			// If it's empty
			case "":
				return false;
		}
	}
	
	function deleteMessagesImages($id, $group = null) {
		if($group) {
			$user = '';
			if($id) {
				$user = sprintf(" AND `uid` = '%s'", $this->db->real_escape_string($id));
			}
			$query = $this->db->query(sprintf("SELECT `type`, `value` FROM `messages` WHERE `group` = '%s' AND `type` = 'picture'%s", $this->db->real_escape_string($group), $user));
		} else {
			$query = $this->db->query(sprintf("SELECT `type`, `value` FROM `messages` WHERE `uid` = '%s' AND `type` = 'picture'", $this->db->real_escape_string($id)));
		}
		
		while($row = $query->fetch_assoc()) {
			$output .= $row['value'].',';
		}
		
		deletePhotos('picture', $output);
	}
	
	function getMessagesIds($id = null, $group = null, $extra = null, $share = null) {
		// Extra: get all the ids posted in a group
		if($extra) {
			$query = $this->db->query(sprintf("SELECT `id` FROM `messages` WHERE `group` = '%s'%s ORDER BY `id` ASC", $this->db->real_escape_string($extra), $share));
		} elseif($share) {
			$query = $this->db->query(sprintf("SELECT `id` FROM `messages` WHERE `type` = 'shared' AND `value` IN (%s) ORDER BY `id` ASC", $this->db->real_escape_string($share)));
		} else {
			if($group) {
				$group = " AND `group` = '".$group."'";
			}
			$query = $this->db->query(sprintf("SELECT `id` FROM `messages` WHERE `uid` = '%s'%s ORDER BY `id` ASC", ($id ? $this->db->real_escape_string($id) : $this->db->real_escape_string($this->id)), $group));
		}
		while($row = $query->fetch_assoc()) {
			$output[] = $row['id'];
		}
		
		return implode(',', $output);
	}
	
	function delete($id, $type) {
		// Type 0: Delete Comment
		// Type 1: Delete Message
		// Type 2: Delete Chat Message
		
		// Prepare the statement
		if($type == 0) {
			$stmt = $this->db->prepare("DELETE FROM `comments` WHERE `id` = '{$this->db->real_escape_string($id)}' AND `uid` = '{$this->db->real_escape_string($this->id)}'");
			
			// Set $x variable to 1 if the delete query is for `comments`
			$x = 0;
		} elseif($type == 1) {
			// Get the current type (for images deletion)
			$query = $this->db->query(sprintf("SELECT `type`, `value` FROM `messages` WHERE `id` = '%s' AND `uid` = '%s'", $this->db->real_escape_string($id), $this->db->real_escape_string($this->id)));
			$message = $query->fetch_assoc();
			
			$stmt = $this->db->prepare("DELETE FROM `messages` WHERE `id` = '{$this->db->real_escape_string($id)}' AND `uid` = '{$this->db->real_escape_string($this->id)}'");
			
			// Set $x variable to 1 if the delete query is for `messages`
			$x = 1;
		} elseif($type == 2) {
			$stmt = $this->db->prepare("DELETE FROM `chat` WHERE `id` = '{$this->db->real_escape_string($id)}' AND `from` = '{$this->db->real_escape_string($this->id)}'");
			
			$x = 2;
		}

		// Execute the statement
		$stmt->execute();
		
		// Save the affected rows
		$affected = $stmt->affected_rows;
		
		// Close the statement
		$stmt->close();
		
		// If the messages/comments table was affected
		if($affected) {
			// Deletes the Comments/Likes/Reports if the Message was deleted
			if($x == 1) {
				$sids = $this->getMessagesIds(null, null, null, $id);
				
				// If there are any messages shared
				if($sids) {
					$this->deleteShared($sids);
				}
				
				$this->db->query(sprintf("DELETE FROM `comments` WHERE `mid` = '%s'", $this->db->real_escape_string($id)));
				$this->db->query(sprintf("DELETE FROM `likes` WHERE `post` = '%s' AND `type` = 0", $this->db->real_escape_string($id)));
				$this->db->query(sprintf("DELETE FROM `reports` WHERE `post` = '%s' AND `parent` = '0'", $this->db->real_escape_string($id)));
				$this->db->query(sprintf("DELETE FROM `notifications` WHERE `parent` = '%s'", $this->db->real_escape_string($id)));
				
				// If the message was a shared one, delete it from notifications as well
				if($message['type'] == 'shared') {
					$this->db->query("DELETE FROM `notifications` WHERE `child` = '{$this->db->real_escape_string($id)}' AND `parent` = '{$message['value']}' AND `type` = 3");
				} else {
					$this->db->query("DELETE FROM `messages` WHERE `type` = 'shared' AND `value` = '{$this->db->real_escape_string($id)}'");
				}
				
				// Execute the deletePhotos function
				deletePhotos($message['type'], $message['value']);
				
				foreach($this->plugins as $plugin) {
					if(array_intersect(array("d"), str_split($plugin['type']))) {
						plugin($plugin['name'], array('id' => $message['id'], 'type' => $message['type'], 'value' => $message['value']), 4);
					}
				}
			} elseif($x == 0) {
				$this->db->query(sprintf("DELETE FROM `likes` WHERE `post` = '%s' AND `type` = 1", $this->db->real_escape_string($id)));
				$this->db->query("DELETE FROM `reports` WHERE `post` = '{$this->db->real_escape_string($id)}' AND `parent` <> '0'");
				$this->db->query("DELETE FROM `notifications` WHERE `child` = '{$this->db->real_escape_string($id)}' AND `type` = '2'");
			}
		}
		
		return ($affected) ? 1 : 0;
	}
	
	function deleteShared($id) {
		$this->db->query(sprintf("DELETE FROM `comments` WHERE `mid` IN (%s)", $id));
		$this->db->query(sprintf("DELETE FROM `likes` WHERE `post` IN (%s) AND `type` = 0", $id));
		$this->db->query(sprintf("DELETE FROM `reports` WHERE `post` IN (%s) AND `parent` = '0'", $id));
		$this->db->query(sprintf("DELETE FROM `notifications` WHERE `parent` IN (%s)", $id));
	}
	
	function report($id, $type) {
		global $LNG;
		// Check if the Message exists
		if($type == 1) {
			$result = $this->db->query(sprintf("SELECT `id` FROM `messages` WHERE `id` = '%s'", $this->db->real_escape_string($id)));
		} else {
			$result = $this->db->query(sprintf("SELECT `id`,`mid` FROM `comments` WHERE `id` = '%s'", $this->db->real_escape_string($id)));
			$parent = $result->fetch_array(MYSQLI_ASSOC); 
		}
		// If the Message/Comment exists
		if($result->num_rows) {
			$result->close();
		
			// Get the report status, 0 = already exists * 1 = is safe
			$query = sprintf("SELECT `state` FROM `reports` WHERE `post` = '%s' AND `type` = '%s'", $this->db->real_escape_string($id), $this->db->real_escape_string($type));
			$result = $this->db->query($query);
			$state = $result->fetch_assoc();
			
			//  If the report already exists
			if($result->num_rows) {
				// If the comment state is 0, then already exists
				if($state['state'] == 0) {
					return $LNG["{$type}_already_reported"];
				} elseif($state['state'] == 1) {
					return $LNG["{$type}_is_safe"];
				} else {
					return $LNG["{$type}_is_deleted"];
				}
			} else {
				$stmt = $this->db->prepare(sprintf("INSERT INTO `reports` (`post`, `parent`, `by`, `type`) VALUES ('%s', '%s', '%s', '%s')", $this->db->real_escape_string($id), ($parent['mid']) ? $parent['mid'] : 0, $this->db->real_escape_string($this->id), $this->db->real_escape_string($type)));

				// Execute the statement
				$stmt->execute();
				
				// Save the affected rows
				$affected = $stmt->affected_rows;

				// Close the statement
				$stmt->close();
				
				// If the comment was added, return 1
				return ($affected) ? $LNG["{$type}_report_added"] : $LNG["{$type}_report_error"];
			}
		} else {
			return $LNG["{$type}_not_exists"];
		}
	}
	
	function addComment($id, $comment) {
		$query = sprintf("SELECT * FROM `messages`,`users` WHERE `id` = '%s' AND `messages`.`uid` = `users`.`idu`", $this->db->real_escape_string($id));
		$result = $this->db->query($query);

		$row = $result->fetch_assoc();

		// If the message is shared to friends only
		if($row['public'] == 2) {
			// If the user is also the owner
			if($this->id == $row['uid']) {
				$row['public'] = 1;
			} else {
				// Check if there is any friendship relation
				$friendship = $this->verifyFriendship($this->id, $row['uid']);
				
				// Set the message to appear as public
				if($friendship['status'] == 1) {
					$row['public'] = 1;
				}
			}
		}
		
		// If the POST is public
		if($row['public'] == 1 && !$this->getBlocked($row['uid'], 2)) {
			// Add the insert message
			$stmt = $this->db->prepare("INSERT INTO `comments` (`uid`, `mid`, `message`) VALUES ('{$this->db->real_escape_string($this->id)}', '{$this->db->real_escape_string($id)}', '{$this->db->real_escape_string(htmlspecialchars($comment))}')");

			// Execute the statement
			$stmt->execute();
			
			// Save the affected rows
			$affected = $stmt->affected_rows;

			// Close the statement
			$stmt->close();
			
			// Select the last inserted message
			$getId = $this->db->query(sprintf("SELECT `id`,`uid`,`mid` FROM `comments` WHERE `uid` = '%s' AND `mid` = '%s' ORDER BY `id` DESC", $this->db->real_escape_string($this->id), $row['id']));
			$lastComment = $getId->fetch_assoc();
			
			// Do the INSERT notification
			$insertNotification = $this->db->query(sprintf("INSERT INTO `notifications` (`from`, `to`, `parent`, `child`, `type`, `read`) VALUES ('%s', '%s', '%s', '%s', '1', '0')", $this->db->real_escape_string($this->id), $row['uid'], $row['id'], $lastComment['id']));
			
			if($affected) {
				// If email on likes is enabled in admin settings
				if($this->email_comment) {
				
					// If user has emails on like enabled and it\'s not liking his own post
					if($row['email_comment'] && ($this->id !== $row['idu'])) {
						global $LNG;
						
						// Send e-mail
						sendMail($row['email'], sprintf($LNG['ttl_comment_email'], $this->username), sprintf($LNG['comment_email'], realName($row['username'], $row['first_name'], $row['last_name']), $this->url.'/index.php?a=profile&u='.$this->username, $this->username, $this->url.'/index.php?a=post&m='.$id, $this->title, $this->url.'/index.php?a=settings&b=notifications'), $this->email);
					}
				}
			}
			
			// If the comment was added, return 1
			return ($affected) ? 1 : 0;
		} else {
			return 0;
		}
	}
	
	function changePrivacy($id, $value) {
		global $LNG;
		$stmt = $this->db->prepare("UPDATE `messages` SET `public` = '{$this->db->real_escape_string($value)}', `time` = `time`  WHERE `id` = '{$this->db->real_escape_string($id)}' AND `uid` = '{$this->db->real_escape_string($this->id)}' AND `group` = 0");
		
		// Execute the statement
		$stmt->execute();
		
		// Save the affected rows
		$affected = $stmt->affected_rows;
		
		// Close the statement
		$stmt->close();

		if($value == 1) {
			$public = '<div class="privacy-icons public-icon" title="'.$LNG['public'].'"></div>';
		} elseif($value == 2) {
			$public = '<div class="privacy-icons friends-icon" title="'.$LNG['friends'].'"></div>';
		} else {
			$public = '<div class="privacy-icons private-icon" title="'.$LNG['private'].'"></div>';
		}
		return $public;
	}
	
	function ago($i) {
		global $LNG;
		$m = time() - $i; $o = $LNG['just_now'];
		$t = array($LNG['year_s'] => 31556926, $LNG['month_s'] => 2629744, $LNG['week_s'] => 604800, $LNG['day_s'] => 86400, $LNG['hour_s'] => 3600, $LNG['minute_s'] =>60, $LNG['second_s'] => 1);
		foreach($t as $u => $s) {
			if($s <= $m) {
				$v = floor($m/$s);
				$o = "$v $u".' '.$LNG['ago'];
				break;
			}
		}
		return $o;
	}
		
	function sidebarGender($bold) {
		global $LNG, $CONF;
		
		// Start the output
		$row = array('male', 'female');
		$link = '<div class="sidebar-container widget-gender"><div class="sidebar-content"><div class="sidebar-header">'.$LNG['filter_gender'].'</div>';
		if(!in_array($bold, array('m', 'f'))) {
			$class = ' sidebar-link-active';
		}
		$link .= '<div class="sidebar-link'.$class.'"><a href="'.$this->url.'/index.php?a='.$_GET['a'].'&q='.htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8').((!empty($_GET['age'])) ? '&age='.$_GET['age'] : '').'" rel="loadpage"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/filters/all.png">'.$LNG["all_genders"].'</a></div>';

		foreach($row as $type) {
			$class = '';
			if(substr($type, 0, 1) == $bold) {
				$class = ' sidebar-link-active';
			}
			
			// Output the links
			$link .= '<div class="sidebar-link'.$class.'"><a href="'.$this->url.'/index.php?a='.$_GET['a'].'&q='.htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8').'&filter='.substr($type, 0, 1).((!empty($_GET['age'])) ? '&age='.$_GET['age'] : '').'" rel="loadpage"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/filters/'.$type.'.png">'.$LNG["sidebar_{$type}"].'</a></div>';
		}
		$link .= '</div></div>';
		return $link;
	}
	
	function sidebarSearch() {
		global $LNG, $CONF;
		
		// Start the output
		$row = array('tag', 'groups');
		$link = '<div class="sidebar-container widget-search"><div class="sidebar-content"><div class="sidebar-header">'.$LNG['title_search'].'</div>';
		if(!empty($_GET['q'])) {
			$class = ' sidebar-link-active';
		}
		$link .= '<div class="sidebar-link'.$class.'"><a href="'.$this->url.'/index.php?a='.$_GET['a'].'&q='.htmlspecialchars($_GET['q'].$_GET['tag'].$_GET['groups'], ENT_QUOTES, 'UTF-8').'" rel="loadpage">'.$LNG["sidebar_people"].'</a></div>';

		foreach($row as $type) {
			$class = '';
			$url = '&'.$type.'='.htmlspecialchars($_GET['q'].$_GET['tag'].$_GET['groups'], ENT_QUOTES, 'UTF-8');
			if(!empty($_GET[$type])) {
				$class = ' sidebar-link-active';
			}
			
			// Output the links
			$link .= '<div class="sidebar-link'.$class.'"><a href="'.$this->url.'/index.php?a='.$_GET['a'].$url.'" rel="loadpage">'.$LNG["sidebar_{$type}"].'</a></div>';
		}
		$link .= '</div></div>';
		return $link;
	}
	
	function sidebarAge($bold) {
		global $LNG, $CONF;
		
		// Start the output
		$ages = array('22-18', '29-22', '39-29', '49-39', '59-49', '69-59', '99-69');
		$link = '<div class="sidebar-container widget-age"><div class="sidebar-content"><div class="sidebar-header">'.$LNG['filter_age'].'</div>';
		if(!in_array($bold, $ages)) {
			$class = ' sidebar-link-active';
		}
		$link .= '<div class="sidebar-link'.$class.'"><a href="'.$this->url.'/index.php?a='.$_GET['a'].'&q='.htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8').((!empty($_GET['filter'])) ? '&filter='.htmlspecialchars($_GET['filter'], ENT_QUOTES, 'UTF-8') : '').'" rel="loadpage">'.$LNG["all_ages"].'</a></div>';
		foreach($ages as $age) {
			// Split the ages
			$between = explode('-', $age);
			
			$class = '';
			if($age == $bold) {
				$class = ' sidebar-link-active';
			}
			
			// Output the links
			$link .= '<div class="sidebar-link'.$class.'"><a href="'.$this->url.'/index.php?a='.$_GET['a'].'&q='.htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8').'&age='.$age.((!empty($_GET['filter'])) ? '&filter='.htmlspecialchars($_GET['filter'], ENT_QUOTES, 'UTF-8') : '').'" rel="loadpage">'.$between[1].' - '.$between[0].'</a></div>';
		}
		$link .= '</div></div>';
		return $link;
	}
	
	function sidebarNotifications($bold) {
		global $LNG, $CONF;
		
		// Start the output
		$row = array('likes', 'comments', 'shared', 'friendships', 'groups', 'chats', 'birthdays');
		$link = '<div class="sidebar-container widget-notifications"><div class="sidebar-content"><div class="sidebar-header">'.$LNG['events'].'</div>';
		if(!in_array($bold, $row)) {
			$class = ' sidebar-link-active';
		}
		$link .= '<div class="sidebar-link'.$class.'"><a href="'.$this->url.'/index.php?a='.$_GET['a'].'" rel="loadpage"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/events/all.png">'.$LNG["all_events"].'</a></div>';
		
		foreach($row as $type) {
			$class = '';
			if($type == $bold) {
				$class = ' sidebar-link-active';
			}
			
			// Output the links
			$link .= '<div class="sidebar-link'.$class.'"><a href="'.$this->url.'/index.php?a='.$_GET['a'].'&filter='.$type.'" rel="loadpage"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/events/'.$type.'.png">'.$LNG["sidebar_{$type}"].'</a></div>';
		}
		$link .= '</div></div>';
		return $link;
	}
	
	function sidebarTypes($bold) {
		global $LNG, $CONF;
		$row = $this->listTypes;
		
		// Sort the array elements
		sort($row);
		
		$profile = ($this->profile) ? '&u='.$this->profile : '';
		// If the result is not empty
		if($row) {
			// Start the output
			$link = '<div class="sidebar-container widget-types"><div class="sidebar-content"><div class="sidebar-header">'.$LNG['events'].'</div>';

			if(empty($bold)) {
				$class = ' sidebar-link-active';
			}
			$link .= '<div class="sidebar-link'.$class.'"><a href="'.$this->url.'/index.php?a='.$_GET['a'].$profile.'" rel="loadpage"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/events/all.png">'.$LNG["all_events"].'</a></div>';
			
			$i = 1;
			foreach($row as $type) {
				$class = '';
				if($type == $bold) {
					$class = ' sidebar-link-active';
				}
				
				// Output the links
				$link .= '<div class="sidebar-link sidebar-events'.$class.'"'.$hidden.'><a href="'.$this->url.'/index.php?a='.$_GET['a'].$profile.'&filter='.$type.'" rel="loadpage"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/events/'.$type.'.png">'.$LNG["sidebar_{$type}"].'</a></div>';
				
				// Display the show more arrow
				if(($i == 5 && count($row) > 5 && empty($bold)) || ($i == 5 && count($row) > 5 && !empty($bold) && !ctype_alpha($bold))) {
					// Output the links
					$link .= '<div class="sidebar-link sidebar-more" id="show-more-btn-1"><a href="javascript:;" onclick="sidebarShow(1)"><div class="message-menu sidebar-arrow"></div></a></div>';
					// Hide the rest of the elements
					$hidden = ' style="display: none;"';
				}
				$i++;
			}
			$link .= '</div></div>';
			return $link;
		}
	}
	
	function sidebarDates($bold) {
		global $LNG;
		$row = $this->listDates;
		
		$profile = ($this->profile) ? '&u='.$this->profile : '';
		// If the result is not empty
		if($row) {
			// Start the output
			$link = '<div class="sidebar-container widget-archive"><div class="sidebar-content"><div class="sidebar-header">'.$LNG['archive'].'</div>';
			if(empty($bold)) {
				$class = ' sidebar-link-active';
			}
			$link .= '<div class="sidebar-link'.$class.'"><a href="'.$this->url.'/index.php?a='.$_GET['a'].$profile.'" rel="loadpage">'.$LNG["all_time"].'</a></div>';
			$i = 1;
			foreach($row as $date) {
				// Explode the date value [[0]=>Y,[1]=>M];
				$datetime = explode('-', wordwrap($date, 4, '-', true));
				
				// Make it into integer instead of a string (removes the 0, e.g: 03=>3, prevents breaking the language)
				$month = intval($datetime[1]);
				
				$class = '';
				if($date == $bold) {
					$class = ' sidebar-link-active';
				}
				
				// Output the links
				$link .= '<div class="sidebar-link sidebar-dates'.$class.'"'.$hidden.'><a href="'.$this->url.'/index.php?a='.$_GET['a'].$profile.'&filter='.$date.'" rel="loadpage">'.$LNG["month_{$month}"].' - '.$datetime[0].'</a></div>';

				// Display the show more arrow
				if(($i == 5 && count($row) > 5 && empty($bold)) || ($i == 5 && count($row) > 5 && !empty($bold) && !is_numeric($bold))) {
					// Output the links
					$link .= '<div class="sidebar-link sidebar-more" id="show-more-btn-2"><a href="javascript:;" onclick="sidebarShow(2)"><div class="message-menu sidebar-arrow"></div></a></div>';
					// Hide the rest of the elements
					$hidden = ' style="display: none;"';
				}
				$i++;
			}
			$link .= '</div></div>';
			return $link;
		}
	}
	
	function listTypes($values = null) {
		if($values == false) {
			return false;
		} elseif($values == 'profile') {
			// If the user that views the profile is not the owner
			if($this->id !== $this->profile_data['idu']) {
				if($this->is_admin) {
					$public = '';
				} else {
					// Check if there is any friendship relation
					$friendship = $this->verifyFriendship($this->id, $this->profile_data['idu']);
				
					if($friendship['status'] == '1') {
						$public = "AND `messages`.`public` <> 0";
					} else {
						$public = "AND `messages`.`public` = 1";
					}
				}
			}
			$query = sprintf("SELECT DISTINCT `type` FROM `messages` WHERE `uid` = '%s' AND `messages`.`group` = 0 %s", $this->db->real_escape_string($this->profile_id), $public);
		} elseif($values) {
			$query = sprintf("SELECT DISTINCT `messages`.`type` FROM `messages`, `users` WHERE `messages`.`uid` = `users`.`idu` AND `messages`.`group` = 0 AND `messages`.`uid` IN (%s) AND `messages`.`public` <> 0 AND `users`.`suspended` = 0", $this->db->real_escape_string($values));
		}
		$result = $this->db->query($query);
		
		while($row = $result->fetch_assoc()) {
			$rows[] = $row;
		}
		
		// If the select was made
		if($result = $this->db->query($query)) {
			// Define the array;
			$store = array();
			foreach($rows as $type) {
				// Check for the result not to be empty
				if(!empty($type['type'])) {
					// Add the elemnets to the array
					$store[] = $type['type'];
				}
			}
			return $store;
		} else {
			return false;
		}
	}
	
	function listDates($values = null) {
		if($values == false) {
			return false;
		} elseif($values == 'profile') {
			// If the user that views the profile is not the owner
			if($this->id !== $this->profile_data['idu']) {
				if($this->is_admin) {
					$public = '';
				} else {
					// Check if there is any friendship relation
					$friendship = $this->verifyFriendship($this->id, $this->profile_data['idu']);
				
					if($friendship['status'] == '1') {
						$public = "AND `messages`.`public` <> 0";
					} else {
						$public = "AND `messages`.`public` = 1";
					}
				}
			}
			$query = sprintf("SELECT DISTINCT extract(YEAR_MONTH from `time`) AS dates FROM `messages` WHERE uid = '%s' AND `messages`.`group` = 0 %s ORDER BY `time` DESC", $this->db->real_escape_string($this->profile_id), $public);
		} elseif($values) {
			$query = sprintf("SELECT DISTINCT extract(YEAR_MONTH from `messages`.`time`) AS dates FROM `messages`, `users` WHERE `messages`.`uid` = `users`.`idu` AND `messages`.`group` = 0 AND `messages`.`uid` IN (%s) AND `messages`.`public` <> 0 AND `users`.`suspended` = 0 ORDER BY `messages`.`time` DESC", $this->db->real_escape_string($values));
		}
		
		$result = $this->db->query($query);
				
		while($row = $result->fetch_assoc()) {
			$rows[] = $row;
		}
		
		// If the select was made
		if($result = $this->db->query($query)) {
			// Define the array;
			$store = array();
			foreach($rows as $date) {
				// Add the elemnts to the array
				$store [] = $date['dates'];
			}
			return $store;
		} else {
			return false;
		}
	}
	
	function sidebarFriends($type, $for) {
		global $LNG;
		$rows = $this->friendsArray;
		
		// If the select was made
		if($rows[1] > 0) {			
			if($for == 0) {
				// If the request is being made from the Profile page and the user is logged-in
				if($this->profile_data['idu'] && $this->id && $this->id !== $this->profile_data['idu']) {
					// Store the IDs into an array to compare them for Mutual friendships
					foreach($rows[0] as $user) {
						$friends_two[] = $user['idu'];
					}
					// Get the logged-in user friends list
					$friends_one = explode(',', $this->friends);

					// Count the common friends ids
					$mutual = count(array_intersect($friends_one, $friends_two));
					$mutual_text = '<span class="sidebar-header-link"><a href="'.$this->url.'/index.php?a=profile&u='.((!empty($this->profile)) ? $this->profile : $this->username).'&r=friends" rel="loadpage">'.$LNG['mutual'].' <span class="sidebar-header-light">('.$mutual.')</span></a></span>';
				}	

				$i = 0;
				$output = '<div class="sidebar-container widget-friends"><div class="sidebar-content"><div class="sidebar-header"><a href="'.$this->url.'/index.php?a=profile&u='.((!empty($this->profile)) ? $this->profile : $this->username).'&r=friends" rel="loadpage">'.$LNG['friends'].' <span class="sidebar-header-light">('.$rows[1].')</span></a>'.$mutual_text.'</div><div class="sidebar-padding">';
				foreach($rows[0] as $row) {
					if($i == 6) break; // Display only the last 6 subscriptions
					$username = realName($row['username'], $row['first_name'], $row['last_name']);
					// Add the elemnts to the array
					$output .= '<a href="'.$this->url.'/index.php?a=profile&u='.$row['username'].'" rel="loadpage"><div class="sidebar-subscriptions"><div class="sidebar-title-container"><div class="sidebar-title-name">'.$username.'</div></div><img src="'.$this->url.'/thumb.php?src='.$row['image'].'&t=a&w=112&h=112"></div></a>';
					$i++;
				}
				$output .= '</div></div></div>';
			} elseif($for == 1) {
				$output = '<a href="'.$this->url.'/index.php?a=profile&u='.((!empty($this->profile)) ? $this->profile : $this->username).'&r=friends" rel="loadpage">'.$rows[1].' '.$LNG['people'].'</a>';
			}
			return $output;
		} else {
			return false;
		}
	}
	
	function onlineUsers($type = null, $value = null, $window = null) {
		global $LNG, $CONF;
		// Type 2: Show the Friends Results for the live search for Chat/Messages
		//		 : If value is set, find friends
		// Type 1: Display the friends for the Chat/Messages page
		//		 : If value is set, find exact username
		// Type 0: Display the friends for Chat Window
		
		// Get friends list
		if(!$type) {
			$friendslist = $this->getFriendsList();
		} else {
			$friendslist = $this->getFriendsList();
		}
		$currentTime = time();

		if(!empty($friendslist)) {
			if($type == 1) {
				// Display current friends
				$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE `idu` IN (%s) ORDER BY `online` DESC", $this->db->real_escape_string($friendslist)));
			} elseif($type == 2) {
				if($value) {
					// Search in friends
					$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE (`username` LIKE '%s' OR concat_ws(' ', `first_name`, `last_name`) LIKE '%s') AND `idu` IN (%s) ORDER BY `online` DESC", '%'.$this->db->real_escape_string($value).'%', '%'.$this->db->real_escape_string($value).'%', $this->db->real_escape_string($friendslist)));
				} else {
					// Display current friends
					// If it's for the chat window, when the search result is empty, display only the online users
					if($window) {
						$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE `idu` IN (%s) AND `online` > '%s'-'%s' ORDER BY `username` ASC", $this->db->real_escape_string($friendslist), $currentTime, $this->online_time));
					} else {
						$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE `idu` IN (%s) ORDER BY `online` DESC", $this->db->real_escape_string($friendslist)));
					}
				}
			} else {
				// Display the online friends (for the chat window)
				$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE `idu` IN (%s) AND `online` > '%s'-'%s' ORDER BY `username` ASC", $this->db->real_escape_string($friendslist), $currentTime, $this->online_time));
			}
			
			// Store the array results
			while($row = $query->fetch_assoc()) {
				$rows[] = $row;
			}
		}
		
		// usort($rows, 'sortOnlineUsers');
		
		if($type == 1) {
			// Output the users
			$output = '<div class="sidebar-container widget-online-users"><div class="sidebar-content"><div class="sidebar-header"><input type="text" placeholder="'.$LNG['search_in_friends'].'"  id="search-list"></div><div class="sidebar-chat-list">';
			if(!empty($rows)) {
				$i = 0;
				foreach($rows as $row) {
					// Switch the images, depending on the online state
					if(($currentTime - $row['online']) > $this->online_time) {
						$icon = 'offline';
					} else {
						$icon = 'online';
					}
					$output .= ($row['username'] == $_GET['u']) ? '<strong>' : '';
					$output .= '<div class="sidebar-users"><a href="'.$this->url.'/index.php?a=messages&u='.$row['username'].'&id='.$row['idu'].'" rel="loadpage"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/'.$icon.'.png" class="sidebar-status-icon"> <img src="'.$this->url.'/thumb.php?src='.$row['image'].'&w=25&h=25&t=a"> '.realName($row['username'], $row['first_name'], $row['last_name']).'</a></div>';
					$output .= ($row['username'] == $_GET['u']) ? '</strong>' : '';
					$i++;
				}
			} else {
				$output .= '<div class="sidebar-inner">'.$LNG['lonely_here'].'</div>';
			}
			$output .= '</div></div></div>';
		} elseif($type == 2) {
			$output = '';
			if(!empty($rows)) {
				$i = 0;
				foreach($rows as $row) {
					// Switch the images, depending on the online state
					if(($currentTime - $row['online']) > $this->online_time) {
						$icon = 'offline';
					} else {
						$icon = 'online';
					}
					$url = ($window) ? '<a onclick="openChatWindow(\''.$row['idu'].'\', \''.$row['username'].'\', \''.addslashes(realName($row['username'], $row['first_name'], $row['last_name'])).'\', \''.$this->url.'\', \''.$this->url.'/'.$CONF['theme_url'].'/images/icons/'.$icon.'.png\')"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/'.$icon.'.png" class="sidebar-status-icon"> <img src="'.$this->url.'/thumb.php?src='.$row['image'].'&w=25&h=25&t=a"> '.realName($row['username'], $row['first_name'], $row['last_name']).'</a>' : '<a href="'.$this->url.'/index.php?a=messages&u='.$row['username'].'&id='.$row['idu'].'" rel="loadpage"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/'.$icon.'.png" class="sidebar-status-icon"> <img src="'.$this->url.'/thumb.php?src='.$row['image'].'&w=25&h=25&t=a"> '.realName($row['username'], $row['first_name'], $row['last_name']).'</a>';
					$output .= '<div class="sidebar-users">'.$url.'</div>';
					
					$i++;
				}
			} else {
				$output .= '<div class="sidebar-inner">'.$LNG['no_results'].'</div>';
			}
		} else {
			// If the query has content
			if(!empty($rows)) {
				// Output the online users				
				$i = 0;
				foreach($rows as $row) {
					// Switch the images, depending on the online state
					if(($currentTime - $row['online']) > $this->online_time) {
						$icon = 'offline';
					} else {
						$icon = 'online';
					}
					$output .= '<div class="sidebar-users"><a onclick="openChatWindow(\''.$row['idu'].'\', \''.$row['username'].'\', \''.addslashes(realName($row['username'], $row['first_name'], $row['last_name'])).'\', \''.$this->url.'\', \''.$this->url.'/'.$CONF['theme_url'].'/images/icons/'.$icon.'.png\')"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/'.$icon.'.png" class="sidebar-status-icon"> <img src="'.$this->url.'/thumb.php?src='.$row['image'].'&w=25&h=25&t=a"> '.realName($row['username'], $row['first_name'], $row['last_name']).'</a></div>';
					
					$i++;
				}
			}
		}
		if($type) {
			return $output;
		} else {
			return array('friends_chat' => array('friends_count' => (($query->num_rows > 0) ? $query->num_rows : 0), 'friends_list' => $output));
		}
	}

	function getChat($uid, $user) {
		global $LNG, $CONF;
		$output =	'<div class="message-container">
						<div class="message-content">
							<div class="message-form-header">
								<div class="message-form-user"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/chat.png"></div>
								<span class="chat-username">'.((empty($user['username'])) ? $LNG['conversation'] : realName($user['username'], $user['first_name'], $user['last_name'])).'</span><span class="blocked-link">'.$this->getBlocked($uid).'</span>
								<div class="preloader message-loader" style="display: none"></div>
							</div>
							<div class="chat-container scrollable" id="chat-container-'.$uid.'">
								'.((empty($user['username'])) ? $this->chatError($LNG['start_conversation']) : $this->getChatMessages($uid)).'
							</div>
							<div class="message-divider"></div>

							<div class="chat-form-inner"><input id="chat" class="chat-user'.$uid.'" placeholder="'.$LNG['write_message'].'" name="chat" onkeydown="if(event.keyCode == 13) { postChat(null, 1) }"></div>
						</div>	
					</div>';
		return $output;
	}

	function checkChat($uid) {
		global $CONF;
		if(is_array($uid)) {
			$output = array();
			
			foreach($uid as $fid) {
				// $query = $this->db->query(sprintf("SELECT * FROM `chat` WHERE `from` = '%s' AND `to` = '%s' AND `read` = '0'", $this->db->real_escape_string($fid), $this->db->real_escape_string($this->id)));
				$userStatus = $this->db->query(sprintf("SELECT `online` FROM `users` WHERE `idu` = '%s'", $this->db->real_escape_string($fid)));
				$result = $userStatus->fetch_assoc();
				
				if((time() - $result['online']) > $this->online_time) {
					$icon = 'offline';
				} else {
					$icon = 'online';
				}
				$output[$fid] = array('message' => $this->getChatMessages($fid, null, null, 2), 'status' => '<img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/'.$icon.'.png" class="sidebar-status-icon">');
			}
			
			return array('friends_messages' => $output);
		} else {
			$query = $this->db->query(sprintf("SELECT * FROM `chat` WHERE `from` = '%s' AND `to` = '%s' AND `read` = '0'", $this->db->real_escape_string($uid), $this->db->real_escape_string($this->id)));
			if($query->num_rows) {
				return $this->getChatMessages($uid, null, null, 2);
			}
		}
		return false;
	}
	
	function getChatMessages($uid, $cid, $start, $type = null, $for = null) {
		// uid = user id (from which user the message was sent)
		// cid = where the pagination will start
		// start = on/off
		// type 1: swtich the query to get the last message
		global $LNG;

		// If the $start value is 0, empty the query;
		if($start == 0) {
			$start = '';
		} else {
			// Else, build up the query
			$start = 'AND `chat`.`id` < \''.$this->db->real_escape_string($cid).'\'';
		}
		
		if($type == 1) {
			$query = sprintf("SELECT * FROM `chat`, `users` WHERE (`chat`.`from` = '%s' AND `chat`.`to` = '%s' AND `chat`.`from` = `users`.`idu`) ORDER BY `chat`.`id` DESC LIMIT 1", $this->db->real_escape_string($this->id), $this->db->real_escape_string($uid));
		} elseif($type == 2) {
			$query = sprintf("SELECT * FROM `chat`,`users` WHERE `from` = '%s' AND `to` = '%s' AND `read` = '0' AND `chat`.`from` = `users`.`idu` ORDER BY `chat`.`id` DESC", $this->db->real_escape_string($uid), $this->db->real_escape_string($this->id));
		} else {
			$query = sprintf("SELECT * FROM `chat`, `users` WHERE (`chat`.`from` = '%s' AND `chat`.`to` = '%s' AND `chat`.`from` = `users`.`idu`) %s OR (`chat`.`from` = '%s' AND `chat`.`to` = '%s' AND `chat`.`from` = `users`.`idu`) %s ORDER BY `chat`.`id` DESC LIMIT %s", $this->db->real_escape_string($this->id), $this->db->real_escape_string($uid), $start, $this->db->real_escape_string($uid), $this->db->real_escape_string($this->id), $start, ($this->m_per_page + 1));
		}
		
		// check if the query was executed
		if($result = $this->db->query($query)) {
			
			if($type !== 1) {
				// Set the read status to 1 whenever you load messages [IGNORE TYPE: 1]
				$update = $this->db->query(sprintf("UPDATE `chat` SET `read` = '1', `time` = `time` WHERE `from` = '%s' AND `to` = '%s' AND `read` = '0'", $this->db->real_escape_string($uid), $this->db->real_escape_string($this->id)));
			}

			// Set the result into an array
			while($row = $result->fetch_assoc()) {
				$rows[] = $row;
			}
			$rows = array_reverse($rows);
			
			// Define the $output variable;
			$output = '';
			
			// If there are more results available than the limit, then show the Load More Chat Messages
			if(array_key_exists($this->m_per_page, $rows)) {
				$loadmore = 1;
				
				// Unset the first array element because it's not needed, it's used only to predict if the Load More Chat Messages should be displayed
				unset($rows[0]);
			}
			
			foreach($rows as $row) {
				// Define the time selected in the Admin Panel
				$time = $row['time']; $b = '';
				if($this->time == '0') {
					$time = date("c", strtotime($row['time']));
				} elseif($this->time == '2') {
					$time = $this->ago(strtotime($row['time']));
				} elseif($this->time == '3') {
					$date = strtotime($row['time']);
					$time = date('Y-m-d', $date);
					$b = '-standard';
				}
				
				if($this->username == $row['username']) { // If it's current username is the same with the current author
					$delete = '<a onclick="deleteModal('.$row['id'].', 2)" title="'.$LNG['delete'].'"><div class="delete_btn"></div></a>';
				} else {
					$delete = '';
				}
				
				// Variable which contains the result
				$output .= '
				<div class="message-reply-container" id="chat'.$row['id'].'">
					'.$delete.'
					<div class="message-reply-avatar">
						<a href="'.$this->url.'/index.php?a=profile&u='.$row['username'].'" rel="loadpage" title="'.realName($row['username'], $row['first_name'], $row['last_name']).'"><img src="'.$this->url.'/thumb.php?src='.$row['image'].'&t=a"></a>
					</div>
					<div class="message-reply-message">'.$this->parseMessage($row['message']).'
						<div class="message-time">
							<div class="timeago'.$b.'" title="'.$time.'">
								'.$time.'
							</div>
						</div>
					</div>
					<div class="delete_preloader" id="del_chat_'.$row['id'].'"></div>
					
				</div>';
				$start = $row['id'];
			}
			if($loadmore) {
				$load = '<div class="load-more-chat" id="'.(($for == 1) ? 'l-m-c-'.$uid : 'l-m-c').'"><a onclick="loadChat('.htmlentities($uid, ENT_QUOTES).', '.$rows[1]['id'].', 1, '.(($for) ? 1 : 0).')">'.$LNG['view_more_conversations'].'</a></div>';
			}
					
			// Close the query
			$result->close();
			
			// Return the conversations
			return $load.$output;
		} else {
			return false;
		}
	}
	
	function postChat($message, $uid) {
		global $LNG;
		
		$user = $this->profileData(null, $uid);

		if(strlen($message) > $this->chat_length) {
			return $this->chatError(sprintf($LNG['chat_too_long'], $this->chat_length));
		} elseif($uid == $this->id) {
			return $this->chatError(sprintf($LNG['chat_self']));
		} elseif(!$user['username']) {
			return $this->chatError(sprintf($LNG['chat_no_user']));
		}

		$query = $this->db->query(sprintf("SELECT * FROM `blocked` WHERE `by` = '%s' AND uid = '%s'", $this->db->real_escape_string($this->id), $this->db->real_escape_string($uid)));
				
		if($query->num_rows) {
			return $this->chatError(sprintf($LNG['blocked_user'], realName($user['username'], $user['first_name'], $user['last_name'])));
		} else {
			$query = $this->db->query(sprintf("SELECT * FROM `blocked` WHERE `by` = '%s' AND uid = '%s'", $this->db->real_escape_string($uid), $this->db->real_escape_string($this->id)));
			
			if($query->num_rows) {
				return $this->chatError(sprintf($LNG['blocked_by'], realName($user['username'], $user['first_name'], $user['last_name'])));
			}
		}
			
		// Prepare the insertion
		$stmt = $this->db->prepare(sprintf("INSERT INTO `chat` (`from`, `to`, `message`, `read`, `time`) VALUES ('%s', '%s', '%s', '%s', CURRENT_TIMESTAMP)", $this->db->real_escape_string($this->id), $this->db->real_escape_string($uid), $this->db->real_escape_string(htmlspecialchars($message)), 0));

		// Execute the statement
		$stmt->execute();
		
		// Save the affected rows
		$affected = $stmt->affected_rows;

		// Close the statement
		$stmt->close();
		if($affected) {
			return $this->getChatMessages($uid, null, null, 1);
		}
	}
	
	function updateStatus($offline = null) {
		if(!$offline) {
			$this->db->query(sprintf("UPDATE `users` SET `online` = '%s' WHERE `idu` = '%s'", time(), $this->db->real_escape_string($this->id)));
		}
	}
	
	function chatError($value) {
		return '<div class="chat-error">'.$value.'</div>';
	}

	function sidebarGroups($visible = null) {
		// Visibile: If the user is on the Group page
		global $CONF, $LNG;

		// Select the groups and group by the ones owned, group by name
		$query = $this->db->query(sprintf("SELECT * FROM `groups_users`, `groups` WHERE `groups_users`.`user` = '%s' AND `groups_users`.`status` = 1 AND `groups_users`.`group` = `groups`.`id` ORDER BY `permissions` DESC, `groups`.`title` ASC", $this->db->real_escape_string($this->id)));
		$row = array();
		
		while($rows = $query->fetch_assoc()) {
			$row[] = $rows;
		}
		
		$output = '<div class="sidebar-container widget-groups"><div class="sidebar-content"><div class="sidebar-header"><a href="'.$this->url.'/index.php?a=group" rel="loadpage">'.$LNG['groups'].'</a></span></div>';

		if(!$visible) {
			$output .= '<div class="sidebar-link"><a href="'.$this->url.'/index.php?a=group" rel="loadpage"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/create_group.png" width="24" height="24">'.$LNG['create_group'].'</a></div>';
		}
		
		if($row) {
			$i = 1;
			foreach($row as $group) {
				if($group['permissions'] == 2) {
					$menulist = '
					<a href="'.$this->url.'/index.php?a=group&name='.$group['name'].'&r=edit" rel="loadpage"><div class="message-menu-row">'.$LNG['edit'].'</div></a>
					<div class="message-menu-divider"></div>
					<a href="'.$this->url.'/index.php?a=group&name='.$group['name'].'&r=delete" rel="loadpage"><div class="message-menu-row">'.$LNG['delete'].'</div></a>';
				} else {
					$menulist = '<a href="'.$this->url.'/index.php?a=group&name='.$group['name'].'" rel="loadpage"><div class="message-menu-row">'.$LNG['leave_group'].'</div></a>';
				}
				$menu = '
				<div id="group-menu'.$group['id'].'" class="message-menu-container group-menu-container">
					'.$menulist.'
				</div>';
				
				// When on group page, highlight groups that user creeated
				$class = '';
				if($visible && $group['permissions'] == 2) {
					$class = ' sidebar-link-active';
				}
				
				$notifications = $this->groupActivity(2, 0, $group['id']);
				
				$output .= '<div class="sidebar-link sidebar-group'.$class.'" id="group-'.$group['id'].'"'.$hidden.'><a href="'.$this->url.'/index.php?a=group&name='.$group['name'].'" rel="loadpage"><img src="'.$this->url.'/thumb.php?src='.$group['cover'].'&w=48&h=48&t=c" width="24" height="24">'.($notifications ? '<span class="admin-notifications-number group-notifications-number">'.$notifications.'</span>' : '').''.$group['title'].'</a><div class="sidebar-settings-container" onclick="messageMenu('.$group['id'].', 2)"><div class="settings_btn sidebar-settings'.($visible ? '' : ' s-settings-hidden').'"></div></div></div>';
				
				// Add the context menu
				$output .= $menu;
				
				// Display the show more arrow
				if($i == 5 && count($row) > 5 && !$visible) {
					// Output the links
					$output .= '<div class="sidebar-link sidebar-more" id="show-more-btn-3"><a href="javascript:;" onclick="sidebarShow(3)"><div class="message-menu sidebar-arrow"></div></a></div>';
					
					// Hide the rest of the elements
					$hidden = ' style="display: none;"';
				}
				
				$i++;
			}
		}
		$output .= '</div></div>';
		return $output;
	}
	
	function sidebarInput($type) {
		global $LNG;
		
		if($type == 1) {
			$class = 'search-group';
			$title = $LNG['search_this_group'];
			$url = 'index.php?a=group&name='.$this->group_data['name'].'&search=';
			$placeholder = $LNG['search_this_group'];
			$value = (!empty($_GET['search']) ? $_GET['search'] : '');
		} else {
			$class = 'invite-group';
			$title = $LNG['invite_friends'];
			$url = 'index.php?a=group&name='.$this->group_data['name'].'&friends=';
			$placeholder = $LNG['search_in_friends'];
			$value = (!empty($_GET['friends']) ? $_GET['friends'] : '');
		}
		
		$output = '<div class="sidebar-container widget-'.$class.'"><div class="sidebar-content"><div class="sidebar-header">'.$title.'</div><div class="sidebar-inner"><input type="text" name="search-group" id="'.$class.'" class="search-group" onkeydown="if(event.keyCode==13){searchFriends(\''.$url.'\', '.$type.')}" placeholder="'.$placeholder.'" value="'.$value.'"><div id="search-group-btn" onclick="searchFriends(\''.$url.'\', '.$type.');"></div></div></div></div>';
		
		return $output;
	}
	
	function inviteGroup($type, $user) {
		// Type 0: Check if the user can be invited to join a group
		// Type 1: Send the invitation
		
		// Check if the invited user is a friend
		$friendsList = $this->getFriendsList(0);
		$friendsList = explode(',', $friendsList);
		if(!in_array($user, $friendsList)) {
			return false;
		}
		
		if($type) {
			// Get the current group/invitation status
			$status = $this->inviteGroup(0, $user);
			
			// If the user is not notified or is not in the group
			if(!$status) {
				$query = $this->db->query(sprintf("INSERT INTO `notifications` (`from`, `to`, `parent`, `child`, `type`, `read`) VALUES ('%s', '%s', '%s', '0', '6', '0')", $this->id, $this->db->real_escape_string($user), $this->group_data['id']));
				
				// If email on likes is enabled in admin settings
				if($this->email_group_invite) {
				
					// Select the tageted user information
					$query = $this->db->query(sprintf("SELECT `email_group_invite`, `username`, `first_name`, `last_name`, `email` FROM `users` WHERE `idu` = '%s'", $this->db->real_escape_string($user)));
				
					$row = $query->fetch_assoc();
					
					// If user has emails on group invitations enabled
					if($row['email_group_invite'] && ($this->id !== $row['idu'])) {
						global $LNG;
						
						// Send e-mail
						sendMail($row['email'], sprintf($LNG['ttl_group_invite'], $this->username), sprintf($LNG['group_invite'], realName($row['username'], $row['first_name'], $row['last_name']), $this->url.'/index.php?a=profile&u='.$this->username, $this->username, $this->url.'/index.php?a=group&name='.$this->group_data['name'], $this->group_data['title'], $this->title, $this->url.'/index.php?a=settings&b=notifications'), $this->email);
					}
				}
			}
		} else {
			$query = $this->db->query(sprintf("SELECT * FROM `notifications` WHERE `from` = '%s' AND `to` = '%s' AND `parent` = '%s' AND `type` = 6", $this->id, $this->db->real_escape_string($user), $this->group_data['id']));

			if($query->num_rows > 0) {
				return 1;
			} else {
				// Check if the user is already in the group
				$query = $this->db->query(sprintf("SELECT * FROM `groups_users` WHERE `user` = '%s' AND `group` = '%s'", $this->db->real_escape_string($user), $this->group_data['id']));
				
				if($query->num_rows > 0) {
					return 2;
				}
				return false;
			}
		}
	}
	
	function searchFriendsGroup($value) {
		global $LNG, $CONF;
	
		$friendsList = $this->getFriendsList(0);
		if(!$friendsList) {
			return false;
		}
		$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE (`users`.`username` LIKE '%s' OR concat_ws(' ', `users`.`first_name`, `users`.`last_name`) LIKE '%s') AND `users`.`suspended` = 0 AND `users`.`idu` IN (%s) ORDER BY `users`.`verified` DESC, `users`.`idu` DESC LIMIT 0, 50", '%'.$this->db->real_escape_string($value).'%', '%'.$this->db->real_escape_string($value).'%', $friendsList));
		
		// Sometimes the query might fail due to the fact that utf8 characters are being passed and the `username` sql field does not allow special chars
		if(!$query) {
			$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE concat_ws(' ', `users`.`first_name`, `users`.`last_name`) LIKE '%s' AND `users`.`suspended` = 0 AND `users`.`idu` IN (%s) ORDER BY `users`.`verified` DESC, `users`.`idu` DESC LIMIT 0, 50", '%'.$this->db->real_escape_string($value).'%', $friendsList));
		}
		
		while($row = $query->fetch_assoc()) {
			$rows[] = $row;
		}
		
		foreach($rows as $row) {
			$status = $this->inviteGroup(0, $row['idu']);
			$class = 'button-normal';
			$action = '';
			if($status == 1) {
				$buttons = $LNG['invited'];
			} elseif($status == 2) {
				$buttons = $LNG['member'];
			} else {
				$buttons = $LNG['invite'];
				$class = 'button-active';
				$action = 'group(7, '.$row['idu'].', '.$this->group_data['id'].')';
			}
			$output .= '<div class="message-container" id="group-invite-'.$row['idu'].'">
							<div class="message-content">
								<div class="message-inner">
									<div class="users-button '.$class.'">
										<a onclick="'.$action.'">'.$buttons.'</a>
									</div>
									<div class="message-avatar" id="avatar'.$row['idu'].'">
										<a href="'.$this->url.'/index.php?a=profile&u='.$row['username'].'" rel="loadpage">
											<img src="'.$this->url.'/thumb.php?src='.$row['image'].'&t=a&w=50&h=50">
										</a>
									</div>
									<div class="message-top">
										<div class="message-author" id="author'.$row['idu'].'">
											<a href="'.$this->url.'/index.php?a=profile&u='.$row['username'].'" rel="loadpage">'.$row['username'].'</a>'.((!empty($row['verified'])) ? '<span class="verified-small"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/verified.png" title="'.$LNG['verified_user'].'"></span>' : '').'
										</div>
										<div class="message-time">
											'.realName(null, $row['first_name'], $row['last_name']).''.((!empty($row['location'])) ? ' ('.$row['location'].')' : '&nbsp;').' 
										</div>
									</div>
								</div>
							</div>
						</div>';
		}
		return $output;
	}
	
	function listGroupMembers($type = null, $start = null) {
		// Type 0: Load group members
		// Type 1: Load group admins
		// Type 2: Load group requests
		// Type 3: Load group blocks
		// Type 4: Search group members
		global $LNG, $CONF;
		
		$start = $this->db->real_escape_string($start);
				
		if($type == 1) {
			$query = $this->db->query(sprintf("SELECT * FROM `groups_users`,`users` WHERE `groups_users`.`status` = 1 AND `groups_users`.`permissions` IN (1, 2) AND `groups_users`.`group` = '%s' AND `groups_users`.`user` = `users`.`idu` LIMIT %s, %s", $this->group_data['id'], $start, ($this->s_per_page + 1)));
		} elseif($type == 2) {
			$query = $this->db->query(sprintf("SELECT * FROM `groups_users`,`users` WHERE `groups_users`.`status` = 0 AND `groups_users`.`group` = '%s' AND `groups_users`.`user` = `users`.`idu` LIMIT %s, %s", $this->group_data['id'], $start, ($this->s_per_page + 1)));
		} elseif($type == 3) {
			$query = $this->db->query(sprintf("SELECT * FROM `groups_users`,`users` WHERE `groups_users`.`status` = 2 AND `groups_users`.`group` = '%s' AND `groups_users`.`user` = `users`.`idu` ORDER BY `groups_users`.`time` DESC LIMIT %s, %s", $this->group_data['id'], $start, ($this->s_per_page + 1)));
		} elseif($type == 4) {
			$query = $this->db->query(sprintf("SELECT * FROM `groups_users`,`users` WHERE `groups_users`.`status` = 1 AND `groups_users`.`group` = '%s' AND `groups_users`.`user` = `users`.`idu` AND (`users`.`username` LIKE '%s' OR concat_ws(' ', `users`.`first_name`, `users`.`last_name`) LIKE '%s') ORDER BY `groups_users`.`time` DESC LIMIT 0, 50", $this->group_data['id'], '%'.$this->db->real_escape_string($start).'%', '%'.$this->db->real_escape_string($start).'%'));
			
			// Sometimes the query might fail due to the fact that utf8 characters are being passed and the `username` sql field does not allow special chars
			if(!$query) {
				$query = $this->db->query(sprintf("SELECT * FROM `groups_users`,`users` WHERE `groups_users`.`status` = 1 AND `groups_users`.`group` = '%s' AND `groups_users`.`user` = `users`.`idu` AND concat_ws(' ', `users`.`first_name`, `users`.`last_name`) LIKE '%s' ORDER BY `groups_users`.`time` DESC LIMIT 0, 50", $this->group_data['id'], '%'.$this->db->real_escape_string($start).'%'));
			}
		} else {
			$query = $this->db->query(sprintf("SELECT * FROM `groups_users`,`users` WHERE `groups_users`.`status` = 1 AND `groups_users`.`group` = '%s' AND `groups_users`.`user` = `users`.`idu` ORDER BY `groups_users`.`permissions` DESC, `groups_users`.`time` DESC LIMIT %s, %s", $this->group_data['id'], $start, ($this->s_per_page + 1)));
		}
		
		while($row = $query->fetch_assoc()) {
			$rows[] = $row;
		}
		
		if(array_key_exists($this->s_per_page, $rows)) {
			$loadmore = 1;
			
			// Unset the last array element because it's not needed, it's used only to predict if the Load More Messages should be displayed
			array_pop($rows);
		}
		
		foreach($rows as $row) {
			/* 
			// Array Map
			// array => { url, name, dynamic load, class type}
			*/
			$array = array();
			// If the logged-in user has admin permissions and the $row['user'] is not the same with the logged-in user
			if(in_array($this->group_member_data['permissions'], array(1, 2)) && $row['user'] !== $this->id) {
				// If the user has Admin privileges
				if($row['permissions'] == '1') {
					$x = 1;
				} else {
					$x = 0;
				}
				// If the logged-in user is the group owner
				if($this->group_member_data['permissions'] == '2') {
					$y = 1;
				} else {
					$y = 0;
				}
				// If the logged-in user is a group Admin
				if($this->group_member_data['permissions'] == '1') {
					$z = 1;
				} else {
					$z = 0;
				}
				// If the user is not the Group owner
				if($row['permissions'] !== '2') {
					if($type == 1) {
						if($y) {
							$array = array($LNG['remove'] => array(0, 'remove'), $LNG['block'] => array(2, 'block'), $LNG['remove_admin'] => array(5, 'remove-admin'));
						}
					} elseif($type == 2) {
						$array = array($LNG['decline'] => array(0, 'remove'), $LNG['block'] => array(2, 'block'), $LNG['approve'] => array(1, 'approve'));
					} elseif($type == 3) {
						$array = array($LNG['remove'] => array(0, 'remove'), $LNG['unblock'] => array(3, 'approve'));
					} else {
						if($z && !$x || $y) {
							$array = array($LNG['remove'] => array(0, 'remove'), $LNG['block'] => array(2, 'block'));
						}
						if($y) {
							$array[($x ? $LNG['remove_admin'] : $LNG['make_admin'])] = ($x ? array(5, 'remove-admin') : array(4, 'make-admin'));
						}
					}
				}
				// Output the buttons
				$buttons = '<div class="sidebar-gr-btn-container">';
				foreach($array as $button => $value) {
					$buttons .= '<a onclick="group(0, '.$value[0].', '.$row['group'].', '.$row['user'].', '.$row['id'].')" title="'.$button.'"><div class="group-button '.$value[1].'-button"></div></a>';
				}
				$buttons .= '</div>';
			}
			
			$output .= '<div class="message-container" id="group-request-'.$row['id'].'">
							<div class="message-content">
								<div class="message-inner">
								'.$buttons.'
									<div class="message-avatar" id="avatar'.$row['idu'].'">
										<a href="'.$this->url.'/index.php?a=profile&u='.$row['username'].'" rel="loadpage">
											<img src="'.$this->url.'/thumb.php?src='.$row['image'].'&t=a&w=50&h=50">
										</a>
									</div>
									<div class="message-top">
										<div class="message-author" id="author'.$row['idu'].'">
											<a href="'.$this->url.'/index.php?a=profile&u='.$row['username'].'" rel="loadpage">'.$row['username'].'</a>'.((!empty($row['verified'])) ? '<span class="verified-small"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/verified.png" title="'.$LNG['verified_user'].'"></span>' : '').'
										</div>
										<div class="message-time">
											'.realName(null, $row['first_name'], $row['last_name']).''.((!empty($row['location'])) ? ' ('.$row['location'].')' : '&nbsp;').' 
										</div>
									</div>
								</div>
							</div>
						</div>';
		}
		if($loadmore) {
				$output .= '<div class="message-container" id="more_messages">
								<div class="load_more"><a onclick="group(1, '.$type.', '.$this->group_data['id'].', \''.$this->db->real_escape_string($start + $this->s_per_page).'\', \'\')">'.$LNG['view_more_messages'].'</a></div>
							</div>';
		}
		return $output;
	}
	
	function sidebarBirthdays() {
		global $CONF, $LNG;
		$friendslist = $this->friends;
		// If there are no friends, return false
		if(empty($friendslist)) {
			return false;
		}
		
		$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE EXTRACT(MONTH FROM `born`) = '%s' AND EXTRACT(DAY FROM `born`) = '%s' AND `idu` IN (%s)", date('m'), date('d'), $friendslist));
		
		$result = $query->fetch_assoc();
		if($query->num_rows) {
			return '<div class="sidebar-container widget-birthdays"><div class="sidebar-content"><div class="sidebar-header"><a href="'.$this->url.'/index.php?a=notifications&filter=birthdays" rel="loadpage">'.$LNG['friends_birthdays'].'</a></div><div class="sidebar-inner"><div class="sidebar-birthdays"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/n_birthday.png" width="16" height="16">'.(($query->num_rows == 1) ? sprintf($LNG['new_birthday_notification'], $this->url.'/index.php?a=notifications&filter=birthdays', realName($result['username'], $result['first_name'], $result['last_name'])) : sprintf($LNG['x_and_x_others'], $this->url.'/index.php?a=notifications&filter=birthdays', realName($result['username'], $result['first_name'], $result['last_name']), $this->url.'/index.php?a=notifications&filter=birthdays', ($query->num_rows-1))).'</div></div></div></div>';
		}
	}
	
	function sidebarPlaces($id) {
		global $LNG;
		
		// Get the maps posts (public if the logged in user is the same with the viewed profile)
		if($this->id == $id) {
			$query = $this->db->query(sprintf("SELECT * FROM messages, users WHERE messages.uid = '%s' AND messages.group = 0 AND messages.type = 'map' AND messages.uid = users.idu ORDER BY messages.id DESC", $this->db->real_escape_string($id)));
		} else {
			$query = $this->db->query(sprintf("SELECT * FROM messages, users WHERE messages.uid = '%s' AND messages.group = 0 AND messages.type = 'map' AND messages.uid = users.idu AND `messages`.`public` = '1' ORDER BY messages.id DESC", $this->db->real_escape_string($id)));
		}

		// Store the array results
		while($row = $query->fetch_assoc()) {
			$rows[] = $row;
		}

		// If there are maps available
		if(!empty($rows)) {
			$i = 0;
			$output = '<div class="sidebar-container widget-places"><div class="sidebar-content"><div class="sidebar-header"><a href="'.$this->url.'/index.php?a=profile&u='.((!
			empty($this->profile)) ? $this->profile : $this->username).'&filter=map" rel="loadpage">'.$LNG['sidebar_map'].' <span class="sidebar-header-light">('.$query->num_rows.')</span></a></div><div class="sidebar-padding">';
			foreach($rows as $row) {
				if($i == 6) break; // Display only the last 6 maps
				
				$output .= '<a href="'.$this->url.'/index.php?a=post&m='.$row['id'].'" rel="loadpage"><div class="sidebar-subscriptions"><div class="sidebar-title-container"><div class="sidebar-places-name">'.$row['value'].'</div></div><img src="https://maps.googleapis.com/maps/api/staticmap?center='.$row['value'].'&zoom=13&size=150x150&maptype=roadmap&sensor=false&scale=2&visual_refresh=true"></div></a>';
				
				$i++;
			}
			$output .= '</div></div></div>';
			return $output;
		} else {
			return false;
		}
	}
	
	function sidebarFriendsActivity($limit, $type = null) {
		global $LNG, $CONF;

		$friendslist = $this->friends;
		// If there are no friends, return false
		if(empty($friendslist)) {
			return false;
		}
		
		// Define the arrays that holds the values (prevents the array_merge to fail, when one or more options are disabled)
		$likes = array();
		$comments = array();
		$messages = array();
		
		$checkLikes = $this->db->query(sprintf("SELECT * FROM `likes`, `users` WHERE `likes`.`by` = `users`.`idu` AND `likes`.`type` = '0' AND `likes`.`by` IN (%s) AND `users`.`suspended` = 0 ORDER BY `id` DESC LIMIT %s", $friendslist, 25));
		while($row = $checkLikes->fetch_assoc()) {
			$likes[] = $row;
		}
	
		$checkComments = $this->db->query(sprintf("SELECT * FROM `comments`, `users` WHERE `comments`.`uid` = `users`.`idu` AND `comments`.`uid` IN (%s) AND `users`.`suspended` = 0 ORDER BY `id` DESC LIMIT %s", $friendslist, 25));
		while($row = $checkComments->fetch_assoc()) {
			$comments[] = $row;
		}
	
		$checkMessages = $this->db->query(sprintf("SELECT * FROM `messages`, `users` WHERE `messages`.`uid` = `users`.`idu` AND `messages`.`uid` IN (%s) AND `messages`.`public` = '1' AND `users`.`suspended` = 0 ORDER BY `id` DESC LIMIT %s", $friendslist, 25));
		while($row = $checkMessages->fetch_assoc()) {
			$messages[] = $row;
		}
		
		// If there are no latest notifications
		if(empty($likes) && empty($comments) && empty($messages)) {
			return false;
		}
		
		// Add the types into the recursive array results
		$x = 0;
		foreach($likes as $like) {
			$likes[$x]['event'] = 'like';
			$x++;
		}
		$y = 0;
		foreach($comments as $comment) {
			$comments[$y]['event'] = 'comment';
			$y++;
		}
		$z = 0;
		foreach($messages as $message) {
			$messages[$z]['event'] = 'message';
			$z++;
		}
		
		$array = array_merge($likes, $comments, $messages);

		// Sort the array
		usort($array, 'sortDateAsc');
		
		$activity .= '<div class="sidebar-container widget-friends-activity"><div class="sidebar-content"><div class="sidebar-header">'.$LNG['sidebar_friends_activity'].'</div><div class="sidebar-fa-content scrollable">';
		$i = 0;
		foreach($array as $value) {
			if($i == $limit) break;
			$time = $value['time']; $b = '';
			if($this->time == '0') {
				$time = date("c", strtotime($value['time']));
			} elseif($this->time == '2') {
				$time = $this->ago(strtotime($value['time']));
			} elseif($this->time == '3') {
				$date = strtotime($value['time']);
				$time = date('Y-m-d', $date);
				$b = '-standard';
			}
			$activity .= '<div class="notification-row"><div class="notification-padding">';
			if($value['event'] == 'like') {
				$activity .= '<div class="sidebar-fa-image"><a href="'.$this->url.'/index.php?a=profile&u='.$value['username'].'" rel="loadpage"><img class="notifications" src='.$this->url.'/thumb.php?src='.$value['image'].'&t=a&w=50&h=50"></a></div><div class="sidebar-fa-text">'.sprintf($LNG['new_like_fa'], $this->url.'/index.php?a=profile&u='.$value['username'], realName($value['username'], $value['first_name'], $value['last_name']), $this->url.'/index.php?a=post&m='.$value['post']).'. <span class="timeago'.$b.'" title="'.$time.'">'.$time.'</span></div>';
			} elseif($value['event'] == 'comment') {
				$activity .= '<div class="sidebar-fa-image"><a href="'.$this->url.'/index.php?a=profile&u='.$value['username'].'" rel="loadpage"><img class="notifications" src='.$this->url.'/thumb.php?src='.$value['image'].'&t=a&w=50&h=50"></a></div><div class="sidebar-fa-text">'.sprintf($LNG['new_comment_fa'], $this->url.'/index.php?a=profile&u='.$value['username'], realName($value['username'], $value['first_name'], $value['last_name']), $this->url.'/index.php?a=post&m='.$value['mid']).'. <span class="timeago'.$b.'" title="'.$time.'">'.$time.'</span></div>';
			} elseif($value['event'] == 'message') {
				$activity .= '<div class="sidebar-fa-image"><a href="'.$this->url.'/index.php?a=profile&u='.$value['username'].'" rel="loadpage"><img class="notifications" src='.$this->url.'/thumb.php?src='.$value['image'].'&t=a&w=50&h=50"></a></div><div class="sidebar-fa-text">'.sprintf($LNG['new_message_fa'], $this->url.'/index.php?a=profile&u='.$value['username'], realName($value['username'], $value['first_name'], $value['last_name']), $this->url.'/index.php?a=post&m='.$value['id']).'. <span class="timeago'.$b.'" title="'.$time.'">'.$time.'</span></div>';
			}
			$activity .= '</div></div>';
			$i++;
		}
		$activity .= '</div></div></div>';
		
		return $activity;
	}
	
	function sidebarSuggestions($interests) {
		global $LNG;
		
		$friendslist = $this->getFriendsList(1);
		
		// If there are friends available, exclude them
		if($friendslist) {
			$friendslist = $this->id.','.$friendslist;
		} else {
			$friendslist = $this->id;
		}
		
		$query = $this->db->query(sprintf("SELECT `idu`, `username`, `first_name`, `last_name`, `location`, `image`  FROM `users` WHERE `image` <> 'default.png' AND `idu` NOT IN (%s) AND `gender` = '%s' AND `suspended` = 0 ORDER BY `idu` DESC LIMIT 6", $friendslist, $interests));

		// Store the array results
		while($row = $query->fetch_assoc()) {
			$rows[] = $row;
		}

		// If suggestions are available
		if(!empty($rows)) {
			$i = 0;
			
			$output = '<div class="sidebar-container widget-suggestions"><div class="sidebar-content"><div class="sidebar-header">'.$LNG['sidebar_suggestions'].'</div><div class="sidebar-padding">';
			foreach($rows as $row) {
				if($i == 6) break; // Display only the last 6 suggestions
				
				// Add the elemnts to the array
				$output .= '<a href="'.$this->url.'/index.php?a=profile&u='.$row['username'].'" rel="loadpage"><div class="sidebar-subscriptions"><div class="sidebar-title-container"><div class="sidebar-title-name">'.realName($row['username'], $row['first_name'], $row['last_name']).'</div></div><img src="'.$this->url.'/thumb.php?src='.$row['image'].'&t=a&w=112&h=112"></div></a>';
				$i++;
			}
			$output .= '</div></div></div>';
			return $output;
		} else {
			return false;
		}
	}
	
	function sidebarTrending($bold, $per_page) {
		global $LNG;
		
		// Select all the messages that has #hashtags today [starting from the start of the day until the end of the day]
		$query = $this->db->query(sprintf("SELECT * FROM `messages`, `users` WHERE `messages`.`uid` = `users`.`idu` AND `messages`.`time` > CURRENT_DATE AND `messages`.`time` < CURRENT_DATE + INTERVAL 7 DAY AND `messages`.`tag` <> '' AND `messages`.`public` = 1 AND `users`.`suspended` = 0"));
		
		// Store the hashtags into a string
		while($row = $query->fetch_assoc()) {
			$hashtags .= $row['tag'];
		}

		// If there are trends available
		if(!empty($hashtags)) {
			$i = 0;
			// Count the array values and filter out the blank spaces (also lowercase all array elements to prevent case-insensitive showing up, e.g: Test, test, TEST)
			$hashtags = explode(',', $hashtags);
			$count = array_count_values(array_map('strtolower', array_filter($hashtags)));
			
			// Sort them by trend
			arsort($count);
			$output = '<div class="sidebar-container widget-trending"><div class="sidebar-content"><div class="sidebar-header">'.$LNG['sidebar_trending'].'</div>';
			foreach($count as $row => $value) {
				if($i == $per_page) break; // Display and break when the trends hits the limit
				if($row == $bold) {
					$output .= '<div class="sidebar-link"><strong><a href="'.$this->url.'/index.php?a=search&tag='.$row.'" rel="loadpage">#'.$row.'</a></strong></div>';
				} else {
					$output .= '<div class="sidebar-link"><a href="'.$this->url.'/index.php?a=search&tag='.$row.'" rel="loadpage">#'.$row.'</a></div>';
				}
				$i++;
			}
			$output .= '</div></div>';
			return $output;
		} else {
			return false;
		}
	}
	
	function getPictures() {
		// Type 0: Return the pictures count
		$query = $this->db->query(sprintf("SELECT count(`id`) FROM `messages` WHERE `type` = 'picture' AND `group` = 0 AND `uid` = '%s'", $this->profile_data['idu']));
		
		$result = $query->fetch_array();
		return $result[0];
	}
	
	function countGroups() {
		// Type 0: Return the pictures count
		$query = $this->db->query(sprintf("SELECT count(`id`) FROM `groups_users` WHERE `user` = '%s' AND `status` = '1'", $this->profile_data['idu']));
		
		$result = $query->fetch_array();
		return $result[0];
	}
	
	function getLikes($start, $type, $value = null, $for = null) {
		global $LNG;
		// Type 0: Return the likes count
		// Type 1: Return the liked posts
		// Type 2: Return the likes for messages & comments
		// For 	0: Messages
		// For	1: Commments
	
		if($type) {
			if($type == 1) {
				if($start == 0) {
					$start = '';
				} else {
					$start = 'AND `likes`.`id` < \''.$this->db->real_escape_string($start).'\'';
				}
				
				$query = sprintf("SELECT 
				`likes`.`id` as `like_id`, `likes`.`post` as `like_post`, `likes`.`by` as `like_by`, `likes`.`time` as `time`,
				`messages`.`id` as `id`, `messages`.`message` as `message`, `messages`.`type` as `type`, `messages`.`value` as `value`,
				`users`.`username` as `username`, `users`.`first_name` as `first_name`, `users`.`last_name` as `last_name`, `users`.`image` as `image`
				FROM `likes`,`messages`,`users` WHERE `likes`.`by` = '%s' AND `likes`.`type` = 0 AND `likes`.`post` = `messages`.`id` AND `messages`.`uid` = `users`.`idu` AND `messages`.`public` = 1 AND `users`.`suspended` = 0 %s ORDER BY `likes`.`time` DESC LIMIT %s", $this->profile_data['idu'], $start, ($this->per_page + 1));
				
				$getLikes = $this->db->query($query);
				
				// Declare the rows array
				$rows = array();
				while($row = $getLikes->fetch_assoc()) {
					// Store the result into the array
					$rows[] = $row;
				}
				
				// Decide whether the load more will be shown or not
				if(array_key_exists($this->per_page, $rows)) {
					$loadmore = 1;
						
					// Unset the last array element because it's not needed, it's used only to predict if the Load More Messages should be displayed
					array_pop($rows);
				}

				// Start the output
				foreach($rows as $value) {
					$time = $value['time']; $b = '';
					if($this->time == '0') {
						$time = date("c", strtotime($value['time']));
					} elseif($this->time == '2') {
						$time = $this->ago(strtotime($value['time']));
					} elseif($this->time == '3') {
						$date = strtotime($value['time']);
						$time = date('Y-m-d', $date);
						$b = '-standard';
					}

					$output .= '<div class="message-container"><div class="message-content"><div class="message-inner">
					<a href="'.$this->url.'/index.php?a=profile&u='.$this->profile_data['username'].'" rel="loadpage">'.realName($this->profile_data['username'], $this->profile_data['first_name'], $this->profile_data['last_name']).'</a> '.sprintf($LNG['x_liked_y_post'], '<a href="'.$this->url.'/index.php?a=profile&u='.$value['username'].'" rel="loadpage"><div class="like_btn like_btn_extended" style="float: none;"><img src="'.$this->url.'/thumb.php?src='.$value['image'].'&w=25&h=25&t=a"></div>'.realName($value['username'], $value['first_name'], $value['last_name']).'</a>', $this->url.'/index.php?a=post&m='.$value['like_post']).' - <span class="timeago'.$b.'" title="'.$time.'" style="float: none;">'.$time.'</span>
					 '.((!empty($value['message'])) ? '<div class="like_text_snippet">'.($this->parseMessage(substr($value['message'], 0, 60))).'...</div>' : '').'</div></div></div>';
				}
				
				// Display the load more button
				if($loadmore) {
					$output .= '<div class="message-container" id="more_messages">
									<div class="load_more"><a onclick="loadLikes('.$value['like_id'].', \''.$this->profile_data['idu'].'\', \''.$this->profile_data['username'].'\')" id="load-more">'.$LNG['view_more_messages'].'</a></div>
								</div>';
				}
			} else {
				global $CONF;
				if($start == 0) {
					$start = '';
				} else {
					// Else, build up the query
					$start = 'AND `likes`.`id` < \''.$this->db->real_escape_string($start).'\'';
				}
				
				$query = $this->db->query(sprintf("SELECT * FROM `likes`, `users` WHERE `likes`.`post` = '%s' AND `likes`.`type` = '%s' AND `likes`.`by` = `users`.`idu` %s ORDER BY `likes`.`id` DESC LIMIT %s", $this->db->real_escape_string($value), $this->db->real_escape_string($for), $start, ($this->per_page + 1)));

				// Declare the rows array
				$rows = array();
				while($row = $query->fetch_assoc()) {
					// Store the result into the array
					$rows[] = $row;
				}
				
				// Decide whether the load more will be shown or not
				if(array_key_exists($this->per_page, $rows)) {
					$loadmore = 1;
						
					// Unset the last array element because it's not needed, it's used only to predict if the Load More Messages should be displayed
					array_pop($rows);
				}
				
				foreach($rows as $row) {
					$output .= '<div class="modal-listing">
									<div class="modal-listing-inner">
										<div id="friend'.$row['idu'].'">'.$this->friendship(0, array('idu' => $row['idu'], 'username' => $row['username'], 'private' => $row['private']), 1).'</div>'.$this->chatButton($row['idu'], $row['username'], 1).'
										<div class="message-avatar" id="avatar'.$row['idu'].'">
											<a href="'.$this->url.'/index.php?a=profile&u='.$row['username'].'" rel="loadpage">
												<img src="'.$this->url.'/thumb.php?src='.$row['image'].'&t=a&w=50&h=50">
											</a>
										</div>
										<div class="message-top">
											<div class="message-author" id="author'.$row['idu'].'" rel="loadpage">
												<a href="'.$this->url.'/index.php?a=profile&u='.$row['username'].'" rel="loadpage">'.$row['username'].'</a>'.((!empty($row['verified'])) ? '<span class="verified-small"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/verified.png" title="'.$LNG['verified_user'].'"></span>' : '').'
											</div>
											<div class="message-time">
												'.realName(null, $row['first_name'], $row['last_name']).''.((!empty($row['location'])) ? ' ('.$row['location'].')' : '&nbsp;').' 
											</div>
										</div>
									</div>
								</div>';
					$start = $row['id'];
				}
				
				if($loadmore) {
					$output .= '<div id="more-likes" class="modal-listing-load-more">
									<a onclick="loadLikes('.$start.', \''.$value.'\', \'\', \''.$type.'\', '.$for.')">'.$LNG['view_more_messages'].'</a>
								</div>';
				}
			}
			return $output;
		} else {
			$query = $this->db->query(sprintf("SELECT count(`likes`.`id`) FROM `likes`,`messages`,`users` WHERE `messages`.`uid` = `users`.`idu` AND `likes`.`by` = '%s' AND `likes`.`post` = `messages`.`id` AND `likes`.`type` = 0 AND `messages`.`public` = '1' AND `users`.`suspended` = 0", $this->profile_data['idu']));
			
			// Store the array results
			$result = $query->fetch_array();
			
			// Return the likes value
			return $result[0];
		}
	}
	
	function getHashtags($start, $per_page, $value, $type = null) {
		global $LNG;
		// TYPE 0: Return the messages for the queried hashtag
		// TYPE 1: Return the queries hashtags list
		if($type) {
			if($type) {
				$query = $this->db->query(sprintf("SELECT `messages`.`tag` FROM `messages`, `users` WHERE `messages`.`uid` = `users`.`idu` AND `messages`.`tag` LIKE '%s' AND `messages`.`public` = 1 AND `users`.`suspended` = 0 LIMIT 10", '%'.$this->db->real_escape_string($value).'%'));
			}
			
			// Store the hashtags into a string
			while($row = $query->fetch_assoc()) {
				$hashtags .= $row['tag'];
			}

			$output = '<div class="search-content"><div class="search-results"><div class="notification-inner"><a onclick="manageResults(2)"><strong>'.$LNG['view_all_results'].'</strong></a> <a onclick="manageResults(0)" title="'.$LNG['close_results'].'"><div class="delete_btn"></div></a></div>';
			// If there are no results
			if(empty($hashtags)) {
				$output .= '<div class="message-inner">'.$LNG['no_results'].'</div>';
			} else {
				// Explore each hashtag string into an array
				$explode = explode(',', $hashtags);
				
				// Merge all matched arrays into a string
				$rows = array_unique(array_map('strtolower', $explode));

				foreach($rows as $row) {
					if(stripos($row, $value) !== false) {
						$output .= '<div class="hashtag">
										<a href="'.$this->url.'/index.php?a=search&tag='.$row.'" rel="loadpage">
											<div class="hashtag-inner">
												#'.$row.'
											</div>
										</a>
									</div>';
					}
				}
			}
			$output .= '</div></div>';
		} else {
			// If the $start value is 0, empty the query;
			if($start == 0) {
				$start = '';
			} else {
				// Else, build up the query
				$start = 'AND messages.id < \''.$this->db->real_escape_string($start).'\'';
			}

			$query = sprintf("SELECT * FROM `messages`, `users` WHERE `messages`.`tag` REGEXP '[[:<:]]%s[[:>:]]' AND `messages`.`uid` = `users`.`idu` %s AND `messages`.`public` = 1 AND `users`.`suspended` = 0 ORDER BY `messages`.`id` DESC LIMIT %s", $this->db->real_escape_string($value), $start, ($this->per_page + 1));
			$value = '\''.$value.'\'';

			return $this->getMessages($query, 'loadHashtags', $value);
		}
		return $output;
	}
	
	function getSearch($start, $per_page, $value, $filter = null, $age = null, $type = null) {
		// $type - switches the type for live search or static one [search page]
		global $LNG, $CONF;
		
		// Define the query type
		// Query Type 0: Normal search username, first and last name
		// Query Type 1: Live Search
		if(filter_var($value, FILTER_VALIDATE_EMAIL)) {
			$qt = 1;
		} else {
			$qt = 0;
		}
		
		// If the gender filter is set, and the age is also set
		if(($filter == 'm' || $filter == 'f') && preg_match('/^[0-9]+-[0-9]+$/i', $age)) {
			if($filter == 'm') {
				$gender = 1;
			} else {
				$gender = 2;
			}

			// Build the current date
			$year = date('Y'); $month = date('m'); $day = date('d');
			$date = explode('-', $age);
			
			// Between age
			$x = ($year-$date[0]).'-'.$month.'-'.$day;
			// To age
			$y = ($year-$date[1]).'-'.$month.'-'.$day;
			
			if($qt == 1) {
				$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE `gender` = '%s' AND `born` BETWEEN '%s' AND '%s' AND `email` = '%s' LIMIT 1", $gender,  $this->db->real_escape_string($x), $this->db->real_escape_string($y), $this->db->real_escape_string($value)));
			} else {
				$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE `gender` = '%s' AND `born` BETWEEN '%s' AND '%s' AND (`username` LIKE '%s' OR concat_ws(' ', `first_name`, `last_name`) LIKE '%s') AND `suspended` = 0 ORDER BY `verified` DESC, `idu` DESC LIMIT %s, %s", $gender, $this->db->real_escape_string($x), $this->db->real_escape_string($y), '%'.$this->db->real_escape_string($value).'%', '%'.$this->db->real_escape_string($value).'%', $this->db->real_escape_string($start), ($per_page + 1)));
								
				// Sometimes the query might fail due to the fact that utf8 characters are being passed and the `username` sql field does not allow special chars
				if(!$query) {
					$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE `gender` = '%s' AND `born` BETWEEN '%s' AND '%s' AND concat_ws(' ', `first_name`, `last_name`) LIKE '%s' AND `suspended` = 0 ORDER BY `verified` DESC, `idu` DESC LIMIT %s, %s", $gender, $this->db->real_escape_string($x), $this->db->real_escape_string($y), '%'.$this->db->real_escape_string($value).'%', $this->db->real_escape_string($start), ($per_page + 1)));
				}
			}
		}
		// If the filter is male / female (alpha type)
		elseif($filter == 'm' || $filter == 'f') {
			if($filter == 'm') {
				$gender = 1;
			} else {
				$gender = 2;
			}
			if($qt == 1) {
				$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE `gender` = '%s' AND `email` = '%s' LIMIT 1", $gender, $this->db->real_escape_string($value)));
			} else {
				$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE `gender` = '%s' AND (`username` LIKE '%s' OR concat_ws(' ', `first_name`, `last_name`) LIKE '%s') AND `suspended` = 0 ORDER BY `verified` DESC, `idu` DESC LIMIT %s, %s", $gender, '%'.$this->db->real_escape_string($value).'%', '%'.$this->db->real_escape_string($value).'%', $this->db->real_escape_string($start), ($per_page + 1)));
				
				// Sometimes the query might fail due to the fact that utf8 characters are being passed and the `username` sql field does not allow special chars
				if(!$query) {
					$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE `gender` = '%s' AND concat_ws(' ', `first_name`, `last_name`) LIKE '%s' AND `suspended` = 0 ORDER BY `verified` DESC, `idu` DESC LIMIT %s, %s", $gender, '%'.$this->db->real_escape_string($value).'%', $this->db->real_escape_string($start), ($per_page + 1)));
				}
			}
		} 
		// If the filter is a date range (digit type)
		elseif(preg_match('/^[0-9]+-[0-9]+$/i', $age)) {
			// Build the current date
			$year = date('Y'); $month = date('m'); $day = date('d');
			$date = explode('-', $age);
			
			// Between age
			$x = ($year-$date[0]).'-'.$month.'-'.$day;
			// To age
			$y = ($year-$date[1]).'-'.$month.'-'.$day;
			
			if($qt == 1) {
				$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE `born` BETWEEN '%s' AND '%s' AND `email` = '%s' LIMIT 1", $this->db->real_escape_string($x), $this->db->real_escape_string($y), $this->db->real_escape_string($value)));
			} else {
				$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE `born` BETWEEN '%s' AND '%s' AND (`username` LIKE '%s' OR concat_ws(' ', `first_name`, `last_name`) LIKE '%s')  AND `suspended` = 0 ORDER BY `verified` DESC, `idu` DESC LIMIT %s, %s", $this->db->real_escape_string($x), $this->db->real_escape_string($y), '%'.$this->db->real_escape_string($value).'%', '%'.$this->db->real_escape_string($value).'%', $this->db->real_escape_string($start), ($per_page + 1)));
				
				// Sometimes the query might fail due to the fact that utf8 characters are being passed and the `username` sql field does not allow special chars
				if(!$query) {
					$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE `born` BETWEEN '%s' AND '%s' AND concat_ws(' ', `first_name`, `last_name`) LIKE '%s' AND `suspended` = 0 ORDER BY `verified` DESC, `idu` DESC LIMIT %s, %s", $this->db->real_escape_string($x), $this->db->real_escape_string($y), '%'.$this->db->real_escape_string($value).'%', $this->db->real_escape_string($start), ($per_page + 1)));
				}
			}
		} else {
			if($qt == 1) {
				$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE `email` = '%s' LIMIT 1", $this->db->real_escape_string($value)));
			} else {
				$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE (`username` LIKE '%s' OR concat_ws(' ', `first_name`, `last_name`) LIKE '%s') AND `suspended` = 0 ORDER BY `verified` DESC, `idu` DESC LIMIT %s, %s", '%'.$this->db->real_escape_string($value).'%', '%'.$this->db->real_escape_string($value).'%', $this->db->real_escape_string($start), ($per_page + 1)));
				
				// Sometimes the query might fail due to the fact that utf8 characters are being passed and the `username` sql field does not allow special chars
				if(!$query) {
					$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE concat_ws(' ', `first_name`, `last_name`) LIKE '%s' AND `suspended` = 0 ORDER BY `verified` DESC, `idu` DESC LIMIT %s, %s", '%'.$this->db->real_escape_string($value).'%', $this->db->real_escape_string($start), ($per_page + 1)));
				}
			}
		}

		while($row = $query->fetch_assoc()) {
			$rows[] = $row;
		}
		
		// If the query type is live, hide the load more button
		if(array_key_exists($per_page, $rows)) {
			$loadmore = 1;
			if($type) {
				$loadmore = 0;
			}
			
			// Unset the last array element because it's not needed, it's used only to predict if the Load More Messages should be displayed
			array_pop($rows);
		}
	
		// If the query type is live show the proper style
		if($type) {
			$output = '<div class="search-content"><div class="search-results"><div class="notification-inner"><a onclick="manageResults(1)"><strong>'.$LNG['view_all_results'].'</strong></a> <a onclick="manageResults(0)" title="'.$LNG['close_results'].'"><div class="delete_btn"></div></a></div>';
			// If there are no results
			if(empty($rows)) {
				$output .= '<div class="message-inner">'.$LNG['no_results'].'</div>';
			} else {
				foreach($rows as $row) {
					$output .= '<div class="message-inner">
									<div id="friend'.$row['idu'].'">'.$this->friendship(0, array('idu' => $row['idu'], 'username' => $row['username'], 'private' => $row['private']), 1).'</div>'.$this->chatButton($row['idu'], $row['username'], 1).'
									<div class="message-avatar" id="avatar'.$row['idu'].'">
										<a href="'.$this->url.'/index.php?a=profile&u='.$row['username'].'" rel="loadpage">
											<img src="'.$this->url.'/thumb.php?src='.$row['image'].'&t=a&w=50&h=50">
										</a>
									</div>
									<div class="message-top">
										<div class="message-author" id="author'.$row['idu'].'">
											<a href="'.$this->url.'/index.php?a=profile&u='.$row['username'].'" rel="loadpage">'.$row['username'].'</a>'.((!empty($row['verified'])) ? '<span class="verified-small"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/verified.png" title="'.$LNG['verified_user'].'"></span>' : '').'
										</div>
										<div class="message-time">
											'.realName(null, $row['first_name'], $row['last_name']).''.((!empty($row['location'])) ? ' ('.$row['location'].')' : '&nbsp;').' 
										</div>
									</div>
								</div>';
				}
			}
			$output .= '</div></div>';
		
		} else {
			// If there are no results
			if(empty($rows)) {
				$output .= '<div class="message-container"><div class="message-content"><div class="message-header">'.$LNG['search_title'].'</div><div class="message-inner">'.$LNG['no_results'].'</div></div></div>';
			} else {
				foreach($rows as $row) {
					$output .= '<div class="message-container">
									<div class="message-content">
										<div class="message-inner">
										<div id="friend'.$row['idu'].'">'.$this->friendship(0, array('idu' => $row['idu'], 'username' => $row['username'], 'private' => $row['private']), 1).'</div>'.$this->chatButton($row['idu'], $row['username'], 1).'
											<div class="message-avatar" id="avatar'.$row['idu'].'">
												<a href="'.$this->url.'/index.php?a=profile&u='.$row['username'].'" rel="loadpage">
													<img src="'.$this->url.'/thumb.php?src='.$row['image'].'&t=a&w=50&h=50">
												</a>
											</div>
											<div class="message-top">
												<div class="message-author" id="author'.$row['idu'].'">
													<a href="'.$this->url.'/index.php?a=profile&u='.$row['username'].'" rel="loadpage">'.$row['username'].'</a>'.((!empty($row['verified'])) ? '<span class="verified-small"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/verified.png" title="'.$LNG['verified_user'].'"></span>' : '').'
												</div>
												<div class="message-time">
													'.realName(null, $row['first_name'], $row['last_name']).''.((!empty($row['location'])) ? ' ('.$row['location'].')' : '&nbsp;').' 
												</div>
											</div>
										</div>
									</div>
								</div>';
				}
			}
		}
		if($loadmore) {
				$output .= '<div class="message-container" id="more_messages">
								<div class="load_more"><a onclick="loadPeople('.($start + $per_page).', \''.saniscape($value).'\', \''.saniscape($filter).'\', \''.saniscape($age).'\')" id="load-more">'.$LNG['view_more_messages'].'</a></div>
							</div>';
		}
		
		return $output;
	}
	
	function getGroups($start = null, $value = null, $live = null) {
		global $LNG, $CONF;

		$start = $this->db->real_escape_string($start);
		
		if(empty($value)) {
			// If the value is empty, return the values for the Admin Panel query
			if(empty($live)) {
				$query = $this->db->query(sprintf("SELECT * FROM `groups` ORDER BY `id` DESC LIMIT %s, %s", $this->db->real_escape_string($start), ($this->per_page + 1)));
				$class = 'users-container';
				$button = $LNG['edit'];
				$link = $this->url.'/index.php?a=admin&b=manage_groups&c=';
			} else {
				$this->per_page = 100;
				$query = $this->db->query(sprintf("SELECT * FROM `groups_users`, `groups` WHERE `user` = '%s' AND `status` = 1 AND `groups_users`.`group` = `groups`.`id` ORDER BY `permissions` DESC", $this->profile_data['idu']));
				$class = 'message-container';
				$button = $LNG['view'];
				$link = $this->url.'/index.php?a=group&name='.$row['name'];
				$live = null;
			}
		} else {
			$query = $this->db->query(sprintf("SELECT * FROM `groups` WHERE `name` = '%s' OR (`name` LIKE '%s' OR `title` LIKE '%s') LIMIT %s, %s", $this->db->real_escape_string($value), '%'.$this->db->real_escape_string($value).'%', '%'.$this->db->real_escape_string($value).'%', $this->db->real_escape_string($start), ($this->per_page + 1)));

			// Sometimes the query might fail due to the fact that utf8 characters are being passed and the `username` sql field does not allow special chars
			if(!$query) {
				$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE `title` = '%s' OR `title` LIKE '%s' LIMIT %s, %s", $this->db->real_escape_string($value), '%'.$this->db->real_escape_string($value).'%', ($this->per_page + 1)));
			}
			$class = 'message-container';
			$button = $LNG['view'];
			$link = $this->url.'/index.php?a=group&name='.$row['name'];
		}
		
		while($row = $query->fetch_assoc()) {
			$rows[] = $row;
		}
		
		if($live) {
			$output = '<div class="search-content"><div class="search-results"><div class="notification-inner"><a onclick="manageResults(3)"><strong>'.$LNG['view_all_results'].'</strong></a> <a onclick="manageResults(0)" title="'.$LNG['close_results'].'"><div class="delete_btn"></div></a></div>';
			// If there are no results
			if(empty($rows)) {
				$output .= '<div class="message-inner">'.$LNG['no_results'].'</div>';
			} else {
				foreach($rows as $row) {
					$output .= '<div class="message-inner" id="group'.$row['id'].'">
									<div class="users-button button-normal">
										<a href="'.$this->url.'/index.php?a=group&name='.$row['name'].'" rel="loadpage">'.$LNG['view'].'</a>
									</div>
									<div class="message-avatar">
										<a href="'.$this->url.'/index.php?a=group&name='.$row['name'].'" rel="loadpage">
											<img src="'.$this->url.'/thumb.php?src='.$row['cover'].'&t=c&w=48&h=48">
										</a>
									</div>
									<div class="message-top">
										<div class="message-author">
											<a href="'.$this->url.'/index.php?a=group&name='.$row['name'].'" rel="loadpage">'.$row['title'].'</a>
										</div>
										<div class="message-time">
										'.($row['privacy'] ? $LNG['private_group'] : $LNG['public_group']).' ('.sprintf($LNG['x_members'], $this->countGroupMembers($row['id'], 0)).')
										</div>
									</div>
								</div>';
				}
			}
			$output .= '</div></div>';
		} else {
			if($value) {
				// If there are no results
				if(empty($rows)) {
					$output .= '<div class="message-container"><div class="message-content"><div class="message-header">'.$LNG['search_title'].'</div><div class="message-inner">'.$LNG['no_results'].'</div></div></div>';
				}
			}
			if(array_key_exists($this->per_page, $rows)) {
				$loadmore = 1;
				
				// Unset the last array element because it's not needed, it's used only to predict if the Load More Messages should be displayed
				array_pop($rows);
			}
			
			foreach($rows as $row) {
				$output .= '<div class="'.$class.'" id="group'.$row['id'].'">
								<div class="message-content">
									<div class="message-inner">
										<div class="users-button button-normal">
											<a href="'.$link.$row['name'].'" rel="loadpage">'.$button.'</a>
										</div>
										<div class="message-avatar">
											<a href="'.$this->url.'/index.php?a=group&name='.$row['name'].'" rel="loadpage">
												<img src="'.$this->url.'/thumb.php?src='.$row['cover'].'&t=c&w=48&h=48">
											</a>
										</div>
										<div class="message-top">
											<div class="message-author">
												<a href="'.$this->url.'/index.php?a=group&name='.$row['name'].'" rel="loadpage">'.(empty($value) ? $row['name'] : $row['title']).'</a>
											</div>
											<div class="message-time">
											'.($row['privacy'] ? $LNG['private_group'] : $LNG['public_group']).' ('.sprintf($LNG['x_members'], $this->countGroupMembers($row['id'], 0)).')
											</div>
										</div>
									</div>
								</div>
							</div>';
			}
			if($loadmore) {
					$output .= '<div class="message-container" id="'.(empty($value) ? 'more_users' : 'more_messages').'">
									<div class="load_more"><a onclick="group('.(empty($value) ? 5 : 3).', \''.htmlspecialchars($value).'\', \''.$this->db->real_escape_string($start + $this->per_page).'\', \'\', \'\')" id="load-more">'.$LNG['view_more_messages'].'</a></div>
								</div>';
			}
		}
		return $output;
	}
	
	function listFriends($type = null) {
		global $LNG, $CONF;
		$rows = $this->listFriends[0];

		if(array_key_exists($this->s_per_page, $rows)) {
			$loadmore = 1;
			
			// Unset the last array element because it's not needed, it's used only to predict if the Load More Messages should be displayed
			array_pop($rows);
		}
		
		foreach($rows as $row) {
			$output .= '<div class="message-container">
							<div class="message-content">
								<div class="message-inner">
								<div id="friend'.$row['idu'].'">'.$this->friendship(0, array('idu' => $row['idu'], 'username' => $row['username'], 'private' => $row['private']), 1).'</div>'.$this->chatButton($row['idu'], $row['username'], 1).'
									<div class="message-avatar" id="avatar'.$row['idu'].'">
										<a href="'.$this->url.'/index.php?a=profile&u='.$row['username'].'" rel="loadpage">
											<img src="'.$this->url.'/thumb.php?src='.$row['image'].'&t=a&w=50&h=50">
										</a>
									</div>
									<div class="message-top">
										<div class="message-author" id="author'.$row['idu'].'">
											<a href="'.$this->url.'/index.php?a=profile&u='.$row['username'].'" rel="loadpage">'.$row['username'].'</a>'.((!empty($row['verified'])) ? '<span class="verified-small"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/verified.png" title="'.$LNG['verified_user'].'"></span>' : '').'
										</div>
										<div class="message-time">
											'.realName(null, $row['first_name'], $row['last_name']).''.((!empty($row['location'])) ? ' ('.$row['location'].')' : '&nbsp;').' 
										</div>
									</div>
								</div>
							</div>
						</div>';
			$last = $row['id'];
		}
		if($loadmore) {
				$output .= '<div class="message-container" id="more_messages">
								<div class="load_more"><a onclick="loadSubs('.$last.', '.$type.', '.$this->profile_data['idu'].')" id="load-more">'.$LNG['view_more_messages'].'</a></div>
							</div>';
		}
		return $output;
	}
	
	function getFriends($id, $start = null) {
		if(is_numeric($start)) {
			if($start == 0) {
				$start = '';
			} else {
				$start = 'AND `friendships`.`id` < \''.$this->db->real_escape_string($start).'\'';
			}
			$limit = 'LIMIT '.($this->s_per_page + 1);
		}
		$query = sprintf("
		SELECT * FROM `friendships`, `users` WHERE `user1` = '%s' AND `user2` = `users`.`idu` AND `friendships`.`status` = '1' $start UNION ALL SELECT * FROM `friendships`, `users` WHERE `user2` = '%s' AND `user1` = `users`.`idu` AND `friendships`.`status` = '1' $start ORDER BY `id` DESC %s", $this->db->real_escape_string($id), $this->db->real_escape_string($id), $limit);

		$result = $this->db->query($query);
		while($row = $result->fetch_assoc()) {
			$array [] = $row;
		}
		return array($array, $total = $result->num_rows);
	}
	
	function getActions($id, $likes = null) {
		global $LNG;
				
		// Verify the Like state
		$verify = $this->verifyLike($id);
		
		if($verify) {
			$state = $LNG['dislike'];
			$y = 2;
		} else {
			$state = $LNG['like'];
			$y = 1;
		}
		
		$likes = $this->getLikers($id, $likes);

		// Output variable
		$actions = '<a onclick="doLike('.$id.', 0)" id="doLike'.$id.'">'.$state.'</a> - <a onclick="focus_form('.$id.')">'.$LNG['comment'].'</a> - <a onclick="share('.$id.')">'.$LNG['share'].'</a> <div class="like_btn" id="like_btn'.$id.'"> '.$likes.'</div>';
		
		// If the current user is not empty
		if(empty($this->id)) {
			// Output variable
			$actions = '<a href="'.$this->url.'/index.php" rel="loadpage">'.$LNG['login_to_lcs'].'</a> <div class="like_btn"> '.$likes.'</div>';
		}
		
		return $actions;
	}
	
	function verifyLike($id, $type = null) {
		if($type == 1) {
			$result = $this->db->query(sprintf("SELECT * FROM `likes` WHERE `post` = '%s' AND `by` = '%s' AND `type` = 1", $this->db->real_escape_string($id), $this->db->real_escape_string($this->id)));
		} else {
			$result = $this->db->query(sprintf("SELECT * FROM `likes` WHERE `post` = '%s' AND `by` = '%s' AND `type` = 0", $this->db->real_escape_string($id), $this->db->real_escape_string($this->id)));
		}
		return ($result->num_rows) ? 1 : 0;
	}
	
	function getBlocked($id, $type = null) {
		// Type 0: Output the button state
		// Type 1: Block/Unblock a user
		// Type 2: Returns 1 if blocked
		
		$profile = $this->profileData(null, $id);
		
		// If the username does not exist, return nothing
		if(empty($profile)) {
			return false;
		} else {
			// Verify if there is any block issued for this username
			if($type == 2) {
				$checkBlocked = $this->db->query(sprintf("SELECT * FROM `blocked` WHERE ((`uid` = '%s' AND `by` = '%s') OR (`uid` = '%s' AND `by` = '%s'))", $this->db->real_escape_string($id), $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id), $this->db->real_escape_string($id)));
			} else {
				$checkBlocked = $this->db->query(sprintf("SELECT * FROM `blocked` WHERE `uid` = '%s' AND `by` = '%s'", $this->db->real_escape_string($id), $this->db->real_escape_string($this->id)));
			}

			// If the Message/Comment exists
			$state = $checkBlocked->num_rows;
			
			if($type == 2) {
				return $state;
			}
			
			// If type 1: Add/Remove
			if($type) {
				// If there is a block issued, remove the block
				if($state) {
					// Remove the block
					$this->db->query(sprintf("DELETE FROM `blocked` WHERE `uid` = '%s' AND `by` = '%s'", $this->db->real_escape_string($id), $this->db->real_escape_string($this->id)));
					
					// Block variable
					$y = 0;
				} else {
					// Insert the block
					$this->db->query(sprintf("INSERT INTO `blocked` (`uid`, `by`) VALUES ('%s', '%s')", $this->db->real_escape_string($id), $this->db->real_escape_string($this->id)));
					
					// Delete any friendships
					$this->db->query(sprintf("DELETE FROM `friendships` WHERE (`user1` = '%s' AND `user2` = '%s') OR (`user1` = '%s' AND `user2` = '%s')", $this->db->real_escape_string($this->id), $this->db->real_escape_string($id), $this->db->real_escape_string($id), $this->db->real_escape_string($this->id)));
					
					$this->db->query(sprintf("DELETE FROM `notifications` WHERE ((`from` = '%s' AND `to` = '%s') OR (`from` = '%s' AND `to` = '%s')) AND `type` IN (4,5)", $this->db->real_escape_string($this->id), $this->db->real_escape_string($id), $this->db->real_escape_string($id), $this->db->real_escape_string($this->id)));
					
					// Unblock variable
					$y = 1;
				}
				return $this->outputBlocked($id, $profile, $y);
			} else {
				return $this->outputBlocked($id, $profile, $state);
			}
		}
	}
	
	function outputBlocked($id, $profile, $state) {
		global $LNG;
		if($state) {
			$x = '<span class="unblock-link"><a onclick="doBlock('.$id.', 1)" title="Unblock '.realName($profile['username'], $profile['first_name'], $profile['last_name']).'">'.$LNG['unblock'].'</a></span>';
		} else {
			$x = '<a onclick="doBlock('.$id.', 1)" title="Block '.realName($profile['username'], $profile['first_name'], $profile['last_name']).'">'.$LNG['block'].'</a>';
		}
		return $x;
	}
	
	function postEdit($message, $id) {
		global $LNG;
		
		list($error, $content) = $this->validateMessage($message, null, null, null, 0);
		
		if($error) {
			return false;
		} else {
			// Escape thge message and trim it to remove any extra white spaces or consecutive new lines
			$message = $this->db->real_escape_string(htmlspecialchars(trim(nl2clean($message))));

			// Match the hashtags
			preg_match_all('/(#\w+)/u', str_replace(array('\r', '\n'), ' ', $message), $matchedHashtags);

			// For each hashtag, strip the '#' tag and add a comma after it
			if(!empty($matchedHashtags[0])) {
				foreach($matchedHashtags[0] as $match) {
					$hashtag .= str_replace('#', '', $match).',';
				}
			}
			
			// Update the message
			$result = $this->db->query(sprintf("UPDATE `messages` SET `message` = '%s', `tag` = '%s', `time` = `time` WHERE `id` = '%s' AND `uid` = '%s'", $message, $hashtag, $this->db->real_escape_string($id), $this->db->real_escape_string($this->id)));
			
			$select = $this->db->query(sprintf("SELECT `message` FROM `messages` WHERE `id` = '%s' AND `uid` = '%s'", $this->db->real_escape_string($id), $this->db->real_escape_string($this->id)));
			$result = $select->fetch_row();
			
			return trim(nl2br($this->parseMessage($result[0])));
			// Return the output
		}
	}

	function validateMessage($message, $image, $type, $value, $privacy, $group) {
		// If message is longer than admitted
		if(strlen($message) > $this->message_length) {
			$error = array('message_too_long', $this->message_length);
		}
		// Define the switch variable
		$x = 0;
		if($image['name'][0]) {
			// Set the variable value to 1 if at least one image name exists
			$x = 1;
		}
		if($x == 1) {
			// If the user selects more images than allowed
			if(count($image['name']) > $this->max_images) {
				$error = array('too_many_images', count($image['name']), $this->max_images);
			} else {
				// Define the array which holds the value names
				$value = array();
				$tmp_value = array();
				foreach($image['error'] as $key => $err) {
					$allowedExt = explode(',', $this->image_format);
					$ext = pathinfo($image['name'][$key], PATHINFO_EXTENSION);
					if(!empty($image['size'][$key]) && $image['size'][$key] > $this->max_size) {
						$error = array('file_too_big', fsize($this->max_size), $image['name'][$key]); // Error Code #004
						break;
					} elseif(!empty($ext) && !in_array(strtolower($ext), $allowedExt)) {
						$error = array('format_not_exist', $this->image_format, $image['name'][$key]); // Error Code #005
						break;
					} else {
						if(isset($image['name'][$key]) && $image['name'][$key] !== '' && $image['size'][$key] > 0) {
							$tmp_name = $image['tmp_name'][$key];
							$name = pathinfo($image['name'][$key], PATHINFO_FILENAME);
							$fullname = $image['name'][$key];
							$size = $image['size'][$key];
							$ext = pathinfo($image['name'][$key], PATHINFO_EXTENSION);
							// $finalName = str_replace(',', '', $rand.'.'.$this->db->real_escape_string($name).'.'.$this->db->real_escape_string($ext));
							$finalName = mt_rand().'_'.mt_rand().'_'.mt_rand().'.'.$this->db->real_escape_string($ext);
							
							// Define the type for picture
							$type = 'picture';
							
							// Store the values into arrays
							$tmp_value[] = $tmp_name;
							$value[] = $finalName;
							
							// Fix the image orientation if possible
							imageOrientation($tmp_name);
						}
					}
				}
				if(empty($error)) {
					foreach($value as $key => $finalName) {
						move_uploaded_file($tmp_value[$key], '../uploads/media/'.$finalName);
					}
				}
				// Implode the values
				$value = implode(',', $value);
			}
		} else {
			// Allowed types of evenets
			$allowedType = array('map', 'game', 'video', 'food', 'visited', 'music', 'plugin');
			
			// If the user doesn't select any event, at all.
			if(empty($type)) {
				// Empty the type & value
				$type = '';
				$value = '';
			} else {
				// Verify if the event exist
				if(in_array($type, $allowedType)) {
					// Empty the plugin type
					if($type == 'plugin') {
						$type = '';
					} else {
						if($type == 'video') {
							if(substr($value, 0, 20) == "https://youtube.com/" || substr($value, 0, 24) == "https://www.youtube.com/" || substr($value, 0, 16) == "www.youtube.com/" || substr($value, 0, 12) == "youtube.com/" || substr($value, 0, 19) == "http://youtube.com/" || substr($value, 0, 23) == "http://www.youtube.com/" || substr($value, 0, 16) == "http://youtu.be/" || substr($value, 0, 17) == "https://youtu.be/") {
								parse_str(parse_url($value, PHP_URL_QUERY), $my_array_of_vars);
								if(substr($value, 0, 16) == 'http://youtu.be/' || substr($value, 0, 17) == "https://youtu.be/") {
									$value = str_replace(array('http://youtu.be/', 'https://youtu.be'), 'yt:', $value);
								} else {
									$value = 'yt:'.$my_array_of_vars['v'];
								}
							} elseif(substr($value, 0, 17) == "http://vimeo.com/" || substr($value, 0, 21) == "http://www.vimeo.com/" || substr($value, 0, 18) == "https://vimeo.com/" || substr($value, 0, 22) == "https://www.vimeo.com/" || substr($value, 0, 14) == "www.vimeo.com/" || substr($value, 0, 10) == "vimeo.com/") {
								$value = 'vm:'.(int)substr(parse_url($value, PHP_URL_PATH), 1);
							}
						} elseif($type == 'music') {
							if(substr($value, 0, 23) == "https://soundcloud.com/" || substr($value, 0, 27) == "https://www.soundcloud.com/" || substr($value, 0, 22) == "http://soundcloud.com/" || substr($value, 0, 22) == "http://www.soundcloud.com/" || substr($value, 0, 15) == "soundcloud.com/" || substr($value, 0, 19) == "www.soundcloud.com/") {
								$value = 'sc:'.parse_url($value, PHP_URL_PATH);
							}
						}
					}
				} else {
					$error = array('event_not_exist'); // Error Code #002
				}
			}
		}

		// If the group is set, force the post to be public
		if($group) {
			// Verify if the user has access to the group
			$privacy = 1;
		}
		
		// Allowed types of privacy
		$allowedPrivacy = array(0, 1, 2);
		
		if(!in_array($privacy, $allowedPrivacy)) {
			$error = array('privacy_no_exist'); // Error Code #003
		}
		
		# #001 - The message is empty
		# #002 - The event does not exist
		# #003 - The privacy value is not valid
		# #004 - The selected file is too big
		# #005 - The selected file's format is invalid
		
		if($error) {
			// Return an error
			return array('1', $error);
		} else {
			foreach($this->plugins as $plugin) {
				if(array_intersect(array("1"), str_split($plugin['type']))) {
					$poerr = plugin($plugin['name'], array('message' => $message, 'type' => $type, 'value' => $value), 0);
					// If the plugin output is not an array (error)
					if(!is_array($poerr)) {
						// Store the result
						$po .= $poerr;
					} else {
						// Return the plugin error message
						return array('2', $poerr[0]); 
					}
				}
			}
			
			// If there's any plugin output
			if($po) {
				$value = $po;
			}
			
			// Escape thge message and trim it to remove any extra white spaces or consecutive new lines
			$message = $this->db->real_escape_string(htmlspecialchars(trim(nl2clean($message))));

			// Match the hashtags
			preg_match_all('/(#\w+)/u', str_replace(array('\r', '\n'), ' ', $message), $matchedHashtags);

			// For each hashtag, strip the '#' tag and add a comma after it
			if(!empty($matchedHashtags[0])) {
				foreach($matchedHashtags[0] as $match) {
					$hashtag .= str_replace('#', '', $match).',';
				}
			}
			
			// Create the query
			// Add the insert message				
			$query = sprintf("INSERT INTO `messages` (`uid`, `message`, `tag`, `type`, `value`, `group`, `time`, `public`) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', CURRENT_TIMESTAMP, '%s')", $this->db->real_escape_string($this->id), $message, $hashtag, $this->db->real_escape_string($type), $this->db->real_escape_string(strip_tags($value)), $this->db->real_escape_string($group), $this->db->real_escape_string($privacy));
			return array('0', $query);
		}
	}
	
	function postMessage($message, $image, $type, $value, $privacy, $group = null) {
		global $LNG;
		list($error, $content) = $this->validateMessage($message, $image, $type, $value, $privacy, $group);
		if($error) {
			// Randomize a number for the js function
			$rand = rand();
			if($error == 2) {
				$switch = $content;
			} else {
				$switch = ($content[2]) ? sprintf($LNG["{$content[0]}"], $content[2], $content[1]) : sprintf($LNG["{$content[0]}"], $content[1]);
			}
			return $this->db->real_escape_string('<div class="message-container" id="notification'.$rand.'"><div class="message-content"><div class="message-inner">'.$switch.'<div class="delete_btn" title="'.$LNG['close'].'" onclick="deleteNotification(0, \''.$rand.'\')"></div></div></div></div>');
		} else {
			// Add the insert message
			$stmt = $this->db->prepare("$content");

			// Execute the statement
			$stmt->execute();

			// Close the statement
			$stmt->close();
			
			// If the message was added, return 1
			return $this->db->real_escape_string($this->getLastMessage());
		}
	}
	
	function postShared($id) {
		global $LNG;
		// Check if the post ID exists and it's public
		$query = $this->db->query(sprintf("SELECT * FROM `messages`,`users` WHERE `messages`.`id` = '%s' AND `messages`.`public` IN (1, 2) AND `messages`.`uid` = `users`.`idu`", $this->db->real_escape_string($id)));
		$result = $query->fetch_assoc();
		
		// If a message is found
		if($result) {
			// Insert the shared message
			
			// Check if the message was already shared [avoid mirror in mirror effect]
			if($result['type'] == 'shared') {
				$insert = $this->db->query(sprintf("INSERT INTO `messages` (`uid`, `type`, `value`, `time`, `public`) VALUES ('%s', 'shared', '%s', CURRENT_TIMESTAMP, '1');", $this->db->real_escape_string($this->id), $this->db->real_escape_string($result['value'])));
			} else {
				$insert = $this->db->query(sprintf("INSERT INTO `messages` (`uid`, `type`, `value`, `time`, `public`) VALUES ('%s', 'shared', '%s', CURRENT_TIMESTAMP, '1');", $this->db->real_escape_string($this->id), $this->db->real_escape_string($result['id'])));
			}
			
			// Do the INSERT notification
			$selectShared = $this->db->query(sprintf("SELECT * FROM `messages`,`users` WHERE `messages`.`uid` = '%s' AND `messages`.`type` = 'shared' AND `messages`.`uid` = `users`.`idu` ORDER BY `messages`.`id` DESC", $this->db->real_escape_string($this->id)));
			$resultShared = $selectShared->fetch_assoc();
			
			$getOriginal = $this->db->query(sprintf("SELECT * FROM `messages`, `users` WHERE `messages`.`id` = '%s' AND `messages`.`uid` = `users`.`idu`", $result['value']));
			$shared = $getOriginal->fetch_assoc();

			if($this->id !== $shared['uid']) {
				$insertNotification = $this->db->query(sprintf("INSERT INTO `notifications` (`from`, `to`, `parent`, `child`, `type`, `read`) VALUES ('%s', '%s', '%s', '%s', '3', '0')", $this->db->real_escape_string($this->id), $result['uid'], ($result['type'] == 'shared' ? $result['value'] : $result['id']), $resultShared['id']));
			}
			
			return sprintf($LNG['shared_success'], $this->url.'/index.php?a=feed');
		} else {
			return $LNG['no_shared'];
		}
	}
	
	function groupActivity($type, $message = null, $group = null) {
		// Type 0: Get the latest viewed message from the group
		// Type 1: Add or update the notifications with the last viewed message
		// Type 2: Get the new messages count since last group visit
		
		if($type == 2) {
			// Select the current group users
			$users = $this->getGroupUsers($group, 0);
			
			// Check if there is a last message in the notifications
			$last = $this->groupActivity(0, 0, $group);
			
			// If there is any last message
			if($last) {
				$query = $this->db->query(sprintf("SELECT count(`id`) FROM `messages` WHERE `group` = '%s' AND `id` > '%s' AND `uid` IN (%s)", $group, $last, $users));
			
				$result = $query->fetch_array();
				return $result[0];
			}
		} elseif($type == 1) {
			// Check if there is a last message in the notifications
			$last = $this->groupActivity(0);
			
			// If there is any last message
			if($last) {
				// Check if the last message is higher than the current loaded one (prevents adding lower values when Loading Page on groups)
				if($message > $last) {
					// Update the last record with the new one
					$query = $this->db->query(sprintf("UPDATE `notifications` SET `child` = '%s' WHERE `from` = '%s' AND `parent` = '%s' AND `type` = '7'", $message, $this->id, $this->group_data['id']));
				}
			} else {
				// Insert into notifications
				$query = $this->db->query(sprintf("INSERT INTO `notifications` (`from`, `parent`, `child`, `type`) VALUES ('%s', '%s', '%s', '7')", $this->id, $this->group_data['id'], $message));
			}
		} else {
			$query = $this->db->query(sprintf("SELECT `child` FROM `notifications` WHERE `from` = '%s' AND `parent` = '%s' AND `type` = '7'", $this->id, ($group ? $group : $this->group_data['id'])));

			$result = $query->fetch_assoc();
			return $result['child'];
		}
	}
	
	function groupMember($type, $user) {
		// Type 2: Block the member
		// Type 1: Accept the user
		// Type 0: Decline the user
		
		if($type == 1) {
			// Approve the user
			$this->db->query(sprintf("UPDATE `groups_users` SET `status` = '1', `permissions` = 0 WHERE `user` = '%s' AND `group` = '%s' AND `permissions` != '2' AND `user` != '%s'", $this->db->real_escape_string($user), $this->group_data['id'], $this->id));
		} elseif($type == 2) {
			// Block the member and remove any permissions
			$this->db->query(sprintf("UPDATE `groups_users` SET `status` = '2', `permissions` = 0 WHERE `user` = '%s' AND `group` = '%s' AND `permissions` != '2' AND `user` != '%s'", $this->db->real_escape_string($user), $this->group_data['id'], $this->id));
		} elseif($type == 3) {
			// Unblock the member and remove any permissions
			$this->db->query(sprintf("UPDATE `groups_users` SET `status` = '1', `permissions` = 0 WHERE `user` = '%s' AND `group` = '%s' AND `permissions` != '2' AND `user` != '%s'", $this->db->real_escape_string($user), $this->group_data['id'], $this->id));
		} elseif($type == 4) {
			// Promote a group member to Admin status
			$this->db->query(sprintf("UPDATE `groups_users` SET `status` = '1', `permissions` = 1, `time` = `time` WHERE `user` = '%s' AND `group` = '%s' AND `permissions` != '2' AND `user` != '%s'", $this->db->real_escape_string($user), $this->group_data['id'], $this->id));
		} elseif($type == 5) {
			// Remove the Admin status of a member
			$this->db->query(sprintf("UPDATE `groups_users` SET `permissions` = 0, `time` = `time` WHERE `user` = '%s' AND `group` = '%s' AND `permissions` != '2' AND `user` != '%s'", $this->db->real_escape_string($user), $this->group_data['id'], $this->id));
		} else {
			// Delete a group member
			$stmt = $this->db->prepare("DELETE FROM `groups_users` WHERE `user` = ? AND `group` = ? AND `permissions` != '2' AND `user` != ?");		
			
			$stmt->bind_param('sss', $this->db->real_escape_string($user), $this->group_data['id'], $this->id);
			$stmt->execute();
			$affected = $stmt->affected_rows;
			$stmt->close();
			
			if($affected) {
				// Delete the message images posted in the group
				$this->deleteMessagesImages($user, $this->group_data['id']);
				
				// Get the messages id of that user
				$mids = $this->getMessagesIds($user, $this->group_data['id']);	

				$sids = $this->getMessagesIds(null, null, null, $mids);
				
				// If there are any messages shared
				if($sids) {
					$this->deleteShared($sids);
				}
				
				// Delete the shared messages by other users
				$this->db->query(sprintf("DELETE FROM `messages` WHERE `type` = 'shared' AND `value` IN (%s)", $mids));
				
				// Delete all the messages posted in the group
				$this->db->query(sprintf("DELETE FROM `messages` WHERE `uid` = '%s' AND `group` = '%s'", $this->db->real_escape_string($user), $this->group_data['id']));
				
				// Delete all the messages posted in the group
				$this->db->query(sprintf("DELETE FROM `notifications` WHERE `type` = '7' AND `from` = '%s' AND `parent` = '%s'", $this->db->real_escape_string($user), $this->group_data['id']));
				
				// Delete all the comments made to the messages
				$this->db->query(sprintf("DELETE FROM `comments` WHERE `mid` IN (%s)", $mids));
				
				// Delete all the likes from messages
				$this->db->query(sprintf("DELETE FROM `likes` WHERE `post` IN (%s) AND `type` = 0", $mids));
				
				// Remove all the reports of the message
				$this->db->query(sprintf("DELETE FROM `reports` WHERE `post` IN (%s)", $mids));
				
				// Remove the notifications of the message
				$this->db->query(sprintf("DELETE FROM `notifications` WHERE `parent` IN (%s)", $mids));
			}
		}
	}
	
	function createGroup($values, $type) {
		if($values['token_id'] != $_SESSION['token_id']) {
			return false;
		}
		// Type 1: Edit the group
		global $LNG;
		$values['group_name'] = ($type ? $this->group_data['name'] : strtolower($values['group_name']));
		$values['group_title'] = htmlspecialchars($values['group_title']);
		$values['group_desc'] = htmlspecialchars(trim(nl2clean($values['group_desc'])));
		
		$image = $_FILES['group_cover'];
		
		if(!empty($image['name'])) {
			foreach($image['error'] as $key => $err) {
				$allowedExt = explode(',', $this->image_format);
				$ext = pathinfo($image['name'][$key], PATHINFO_EXTENSION);
				if(!empty($image['size'][$key]) && $image['size'][$key] > $this->max_size) {
					$error = sprintf($LNG['file_too_big'], $image['name'][$key], fsize($this->max_size));
				} elseif(!empty($ext) && !in_array(strtolower($ext), $allowedExt)) {
					$error = sprintf($LNG['format_not_exist'], $image['name'][$key], $this->image_format);
				} else {
					if(isset($image['name'][$key]) && $image['name'][$key] !== '' && $image['size'][$key] > 0) {
						$rand = mt_rand();
						$tmp_name = $image['tmp_name'][$key];
						$name = pathinfo($image['name'][$key], PATHINFO_FILENAME);
						$fullname = $image['name'][$key];
						$size = $image['size'][$key];
						$ext = pathinfo($image['name'][$key], PATHINFO_EXTENSION);
						$cover = mt_rand().'_'.mt_rand().'_'.mt_rand().'.'.$this->db->real_escape_string($ext);

						// Fix the image orientation if possible
						imageOrientation($tmp_name);

						move_uploaded_file($tmp_name, 'uploads/covers/'.$cover);
						
						// If the cover is not the default one, and the user edits the group
						if($type) {
							deleteImages(array($this->group_data['cover']), 0);
						}
						$values['group_cover'] = $cover;
					} else {
						if($type) {
							$values['group_cover'] = $this->group_data['cover'];
						} else {
							$values['group_cover'] = 'default.png';
						}
					}
				}
			}
		} else {
			if($type) {
				$values['group_cover'] = $this->group_data['cover'];
			} else {
				$values['group_cover'] = 'default.png';
			}
		}
		
		if(!ctype_alnum($values['group_name'])) {
			$error = $LNG['group_name_consist'];
		}
		
		$desc_length = 10000;
		if(strlen($values['group_desc']) > $desc_length) {
			$error = sprintf($LNG['group_desc_less'], $desc_length);
		}
		
		$name_length = 32;
		if(strlen($values['group_name']) > $name_length) {
			$error = sprintf($LNG['group_name_less'], $name_length);
		}
		
		$title_length = 32;
		if(strlen($values['group_title']) > $title_length) {
			$error = sprintf($LNG['group_title_less'], $title_length);
		}
		
		if(!$type) {
			// Check if the group name exists
			$query = $this->db->query(sprintf("SELECT `name` FROM `groups` WHERE `name` = '%s'", $this->db->real_escape_string($values['group_name'])));
			
			if($query->num_rows > 0) {
				$error = $LNG['group_name_taken'];
			}
		}
		
		if(empty($values['group_name']) || empty($values['group_title']) || empty($values['group_desc'])) {
			$error = $LNG['all_fields'];
		}
		
		if(!in_array($values['group_privacy'], array(0, 1, 2))) {
			$values['group_privacy'] = 0;
		}
		
		if(!in_array($values['group_posts'], array(0, 1))) {
			$values['group_posts'] = 0;
		}
		
		if($error) {
			return array(1, $error);
		}
		
		if($type) {
			// Prepare the statement
			$stmt = $this->db->prepare("UPDATE `groups` SET `title` = ?, `description` = ?, `cover` = ?, `privacy` = ?, `posts` = ?, `time` = `time` WHERE `name` = ?");		
			$stmt->bind_param('ssssss', $values['group_title'], $values['group_desc'], $values['group_cover'], $values['group_privacy'], $values['group_posts'], $values['group_name']);
			
			// Execute the statement
			$stmt->execute();
			
			// Save the affected rows
			$affected = $stmt->affected_rows;
			
			$stmt->close();
			
			return array(0, ($affected ? 1 : 0));
		} else {
			// Create the Group
			$createGroup = $this->db->query(sprintf("INSERT INTO `groups` (`name`, `title`, `description`, `cover`, `privacy`, `posts`) VALUES ('%s', '%s', '%s', '%s', '%s', '%s');", $this->db->real_escape_string($values['group_name']), $this->db->real_escape_string($values['group_title']), $this->db->real_escape_string($values['group_desc']), $this->db->real_escape_string($values['group_cover']), $this->db->real_escape_string($values['group_privacy']), $this->db->real_escape_string($values['group_posts'])));
			
			// Get the Group's ID
			$getGroup = $this->db->query(sprintf("SELECT `id` FROM `groups` WHERE `name` = '%s'", $this->db->real_escape_string($values['group_name'])));
			$fetchGroup = $getGroup->fetch_assoc();

			// Create the Admin of the Group
			$addAdmin = $this->db->query(sprintf("INSERT INTO `groups_users` (`group`, `user`, `status`, `permissions`) VALUES ('%s', '%s', '1', '2')", $this->db->real_escape_string($fetchGroup['id']), $this->db->real_escape_string($this->id)));

			return array(0, $values['group_name']);
		}
	}
	
	function deleteGroup($id, $type = null, $from = null) {
		// From: The request is being made from the Admin Panel
		if($_GET['token_id'] == $_SESSION['token_id']) {
			global $LNG;
			// Verify the group owner
			$query = $this->db->query(sprintf("SELECT * FROM `groups`, `groups_users` WHERE `group` = '%s' AND `user` = '%s' AND `permissions` = '2' AND `groups_users`.`group` = `groups`.`id`", $this->db->real_escape_string($id), $this->db->real_escape_string($this->id)));
			
			$result = $query->fetch_assoc();
			
			if($query->num_rows) {
				// Delete the Group
				$query = $this->db->query(sprintf("DELETE FROM `groups` WHERE `id` = '%s'", $this->db->real_escape_string($id)));
				
				// Delete all the group members
				$query = $this->db->query(sprintf("DELETE FROM `groups_users` WHERE `group` = '%s'", $this->db->real_escape_string($id)));
				
				// Delete all the images from messages
				$this->deleteMessagesImages(null, $id);
				
				// Delete the group's cover
				deleteImages(array($result['cover']), 0);
				
				$mids = $this->getMessagesIds(null, null, $id);
				
				$sids = $this->getMessagesIds(null, null, null, $mids);
				
				// If there are any messages shared
				if($sids) {
					$this->deleteShared($sids);
				}
				
				// Delete all the shared messages of the group
				$this->db->query(sprintf("DELETE FROM `messages` WHERE `type` = 'shared' AND `value` IN (%s)", $mids));
				
				// Delete all the messages posted in the group
				$query = $this->db->query(sprintf("DELETE FROM `messages` WHERE `group` = '%s'", $this->db->real_escape_string($id)));
				
				// Delete the notifications (both group invite and the last message viewed)
				$query = $this->db->query(sprintf("DELETE FROM `notifications` WHERE `parent` = '%s' AND `type` = '6' OR `type` = '7'", $this->db->real_escape_string($id)));
				
				// Delete all the comments made to the messages of the group
				$this->db->query(sprintf("DELETE FROM `comments` WHERE `mid` IN (%s)", $mids));
				
				// Delete all the likes from messages of the group
				$this->db->query(sprintf("DELETE FROM `likes` WHERE `post` IN (%s)", $mids));
				
				// Remove all the reports of the message of the group
				$this->db->query(sprintf("DELETE FROM `reports` WHERE `post` IN (%s)", $mids));
				
				// Remove the notifications of the message of the group
				$this->db->query(sprintf("DELETE FROM `notifications` WHERE `parent` IN (%s)", $mids));
				
				if(!$type) {
					header("Location: ".$this->url."/index.php?a=".($from ? "admin&b=manage_groups" : "group")."&deleted=".$result['name']);
				}
			}
		}
	}
}
function nl2clean($text) {
	// Replace two or more new lines with two new rows [blank space between them]
	return preg_replace("/(\r?\n){2,}/", "\n\n", $text);
}
function sendMail($to, $subject, $message, $from) {
	$headers  = 'MIME-Version: 1.0' . "\r\n";
	$headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
	$headers .= 'From: '.$from.'' . "\r\n" .
		'Reply-To: '.$from . "\r\n" .
		'X-Mailer: PHP/' . phpversion();
		return @mail($to, $subject, $message, $headers);

}
function strip_tags_array($value) {
	return strip_tags($value);
}
function admin_stats($db, $type, $values = null) {
	if($type == 1) {
		$query = "SELECT
		(SELECT count(id) FROM `reports`) as total_reports,
		(SELECT count(id) FROM `reports` WHERE `state` = 0) as pending_reports,
		(SELECT count(id) FROM `reports` WHERE `state` = 1) as safe_reports,
		(SELECT count(id) FROM `reports` WHERE `state` = 2) as deleted_reports,
		(SELECT count(id) FROM `reports` WHERE `type` = 1) as total_message_reports,
		(SELECT count(id) FROM `reports` WHERE `state` = 0 AND `type` = 1) as pending_message_reports,
		(SELECT count(id) FROM `reports` WHERE `state` = 1 AND `type` = 1) as safe_message_reports,
		(SELECT count(id) FROM `reports` WHERE `state` = 2 AND `type` = 1) as deleted_message_reports,
		(SELECT count(id) FROM `reports` WHERE `type` = 0) as total_comment_reports,
		(SELECT count(id) FROM `reports` WHERE `state` = 0 AND `type` = 0) as pending_comment_reports,
		(SELECT count(id) FROM `reports` WHERE `state` = 1 AND `type` = 0) as safe_comment_reports,
		(SELECT count(id) FROM `reports` WHERE `state` = 2 AND `type` = 0) as deleted_comment_reports";
	} elseif($type == 2) {
		$query = sprintf("SELECT
		(SELECT count(idu) FROM users WHERE CURDATE() = `date`) as users_today,
		(SELECT count(idu) FROM users WHERE DATE_SUB(CURDATE(), INTERVAL 1 DAY) = `date`) as users_yesterday,
		(SELECT count(idu) FROM users WHERE DATE_SUB(CURDATE(), INTERVAL 2 DAY) = `date`) as users_two_days,
		(SELECT count(idu) FROM users WHERE DATE_SUB(CURDATE(), INTERVAL 3 DAY) = `date`) as users_three_days,
		(SELECT count(idu) FROM users WHERE DATE_SUB(CURDATE(), INTERVAL 4 DAY) = `date`) as users_four_days,
		(SELECT count(idu) FROM users WHERE DATE_SUB(CURDATE(), INTERVAL 5 DAY) = `date`) as users_five_days,
		(SELECT count(idu) FROM users WHERE DATE_SUB(CURDATE(), INTERVAL 6 DAY) = `date`) as users_six_days,
		(SELECT COUNT(id) FROM groups WHERE CURDATE() = date(`time`)) AS groups_today,
		(SELECT COUNT(id) FROM groups WHERE DATE_SUB(CURDATE(), INTERVAL 1 DAY) = date(`time`)) AS groups_yesterday,
		(SELECT COUNT(id) FROM groups WHERE DATE_SUB(CURDATE(), INTERVAL 2 DAY) = date(`time`)) AS groups_two_days,
		(SELECT COUNT(id) FROM groups WHERE DATE_SUB(CURDATE(), INTERVAL 3 DAY) = date(`time`)) AS groups_three_days,
		(SELECT COUNT(id) FROM groups WHERE DATE_SUB(CURDATE(), INTERVAL 4 DAY) = date(`time`)) AS groups_four_days,
		(SELECT COUNT(id) FROM groups WHERE DATE_SUB(CURDATE(), INTERVAL 5 DAY) = date(`time`)) AS groups_five_days,
		(SELECT COUNT(id) FROM groups WHERE DATE_SUB(CURDATE(), INTERVAL 6 DAY) = date(`time`)) AS groups_six_days,
		(SELECT COUNT(id) FROM messages WHERE `type` != 'shared' AND CURDATE() = date(`time`)) AS messages_today,
		(SELECT COUNT(id) FROM messages WHERE `type` != 'shared' AND DATE_SUB(CURDATE(), INTERVAL 1 DAY) = date(`time`)) AS messages_yesterday,
		(SELECT COUNT(id) FROM messages WHERE `type` != 'shared' AND DATE_SUB(CURDATE(), INTERVAL 2 DAY) = date(`time`)) AS messages_two_days,
		(SELECT COUNT(id) FROM messages WHERE `type` != 'shared' AND DATE_SUB(CURDATE(), INTERVAL 3 DAY) = date(`time`)) AS messages_three_days,
		(SELECT COUNT(id) FROM messages WHERE `type` != 'shared' AND DATE_SUB(CURDATE(), INTERVAL 4 DAY) = date(`time`)) AS messages_four_days,
		(SELECT COUNT(id) FROM messages WHERE `type` != 'shared' AND DATE_SUB(CURDATE(), INTERVAL 5 DAY) = date(`time`)) AS messages_five_days,
		(SELECT COUNT(id) FROM messages WHERE `type` != 'shared' AND DATE_SUB(CURDATE(), INTERVAL 6 DAY) = date(`time`)) AS messages_six_days,
		(SELECT COUNT(id) FROM comments WHERE CURDATE() = date(`time`)) AS comments_today,
		(SELECT COUNT(id) FROM comments WHERE DATE_SUB(CURDATE(), INTERVAL 1 DAY) = date(`time`)) AS comments_yesterday,
		(SELECT COUNT(id) FROM comments WHERE DATE_SUB(CURDATE(), INTERVAL 2 DAY) = date(`time`)) AS comments_two_days,
		(SELECT COUNT(id) FROM comments WHERE DATE_SUB(CURDATE(), INTERVAL 3 DAY) = date(`time`)) AS comments_three_days,
		(SELECT COUNT(id) FROM comments WHERE DATE_SUB(CURDATE(), INTERVAL 4 DAY) = date(`time`)) AS comments_four_days,
		(SELECT COUNT(id) FROM comments WHERE DATE_SUB(CURDATE(), INTERVAL 5 DAY) = date(`time`)) AS comments_five_days,
		(SELECT COUNT(id) FROM comments WHERE DATE_SUB(CURDATE(), INTERVAL 6 DAY) = date(`time`)) AS comments_six_days,
		(SELECT COUNT(id) FROM messages WHERE `type` = 'shared' AND CURDATE() = date(`time`)) AS shared_today,
		(SELECT COUNT(id) FROM messages WHERE `type` = 'shared' AND DATE_SUB(CURDATE(), INTERVAL 1 DAY) = date(`time`)) AS shared_yesterday,
		(SELECT COUNT(id) FROM messages WHERE `type` = 'shared' AND DATE_SUB(CURDATE(), INTERVAL 2 DAY) = date(`time`)) AS shared_two_days,
		(SELECT COUNT(id) FROM messages WHERE `type` = 'shared' AND DATE_SUB(CURDATE(), INTERVAL 3 DAY) = date(`time`)) AS shared_three_days,
		(SELECT COUNT(id) FROM messages WHERE `type` = 'shared' AND DATE_SUB(CURDATE(), INTERVAL 4 DAY) = date(`time`)) AS shared_four_days,
		(SELECT COUNT(id) FROM messages WHERE `type` = 'shared' AND DATE_SUB(CURDATE(), INTERVAL 5 DAY) = date(`time`)) AS shared_five_days,
		(SELECT COUNT(id) FROM messages WHERE `type` = 'shared' AND DATE_SUB(CURDATE(), INTERVAL 6 DAY) = date(`time`)) AS shared_six_days,
		(SELECT count(id) FROM `likes` WHERE CURDATE() = date(`time`)) as likes_today,
		(SELECT count(id) FROM `likes` WHERE DATE_SUB(CURDATE(), INTERVAL 1 DAY) = date(`time`)) as likes_yesterday,
		(SELECT count(id) FROM `likes` WHERE DATE_SUB(CURDATE(), INTERVAL 2 DAY) = date(`time`)) as likes_two_days,
		(SELECT count(id) FROM `likes` WHERE DATE_SUB(CURDATE(), INTERVAL 3 DAY) = date(`time`)) as likes_three_days,
		(SELECT count(id) FROM `likes` WHERE DATE_SUB(CURDATE(), INTERVAL 4 DAY) = date(`time`)) as likes_four_days,
		(SELECT count(id) FROM `likes` WHERE DATE_SUB(CURDATE(), INTERVAL 5 DAY) = date(`time`)) as likes_five_days,
		(SELECT count(id) FROM `likes` WHERE DATE_SUB(CURDATE(), INTERVAL 6 DAY) = date(`time`)) as likes_six_days,
		(SELECT count(idu) FROM `users` WHERE `online` > '%s'-'%s') AS online_users", time(), $values['conline']);
	} else {
		$query = "SELECT 
		(SELECT COUNT(id) FROM messages WHERE `type` != 'shared') AS messages_total,
		(SELECT COUNT(id) FROM messages WHERE `type` != 'shared' AND CURDATE() = date(`time`)) AS messages_today,
		(SELECT COUNT(id) FROM messages WHERE `type` != 'shared' AND MONTH(CURDATE()) = MONTH(date(`time`)) AND YEAR(CURDATE()) = YEAR(date(`time`))) as messages_month,
		(SELECT COUNT(id) FROM messages WHERE `type` != 'shared' AND YEAR(CURDATE()) = YEAR(date(`time`))) as messages_year,
		(SELECT COUNT(id) FROM comments) as comments_total,
		(SELECT COUNT(id) FROM comments WHERE CURDATE() = date(`time`)) AS comments_today,
		(SELECT COUNT(id) FROM comments WHERE MONTH(CURDATE()) = MONTH(date(`time`)) AND YEAR(CURDATE()) = YEAR(date(`time`))) as comments__month,
		(SELECT COUNT(id) FROM comments WHERE YEAR(CURDATE()) = YEAR(date(`time`))) as comments__year,
		(SELECT COUNT(id) FROM messages WHERE `type` = 'shared') AS shared_total,
		(SELECT COUNT(id) FROM messages WHERE `type` = 'shared' AND CURDATE() = date(`time`)) AS shared_today,
		(SELECT COUNT(id) FROM messages WHERE `type` = 'shared' AND MONTH(CURDATE()) = MONTH(date(`time`)) AND YEAR(CURDATE()) = YEAR(date(`time`))) as shared_month,
		(SELECT COUNT(id) FROM messages WHERE `type` = 'shared' AND YEAR(CURDATE()) = YEAR(date(`time`))) as shared_year,
		(SELECT COUNT(id) FROM groups) AS groups_total,
		(SELECT COUNT(id) FROM groups WHERE CURDATE() = date(`time`)) AS groups_today,
		(SELECT COUNT(id) FROM groups WHERE MONTH(CURDATE()) = MONTH(date(`time`)) AND YEAR(CURDATE()) = YEAR(date(`time`))) as groups_month,
		(SELECT COUNT(id) FROM groups WHERE YEAR(CURDATE()) = YEAR(date(`time`))) as groups_year,
		(SELECT count(idu) FROM users WHERE CURDATE() = `date`) as users_today,
		(SELECT count(idu) FROM users WHERE MONTH(CURDATE()) = MONTH(`date`) AND YEAR(CURDATE()) = YEAR(`date`)) as users_month,
		(SELECT count(idu) FROM users WHERE YEAR(CURDATE()) = YEAR(`date`)) as users_year,
		(SELECT count(idu) FROM users) as users_total,
		(SELECT count(id) FROM `likes`) as total_likes,
		(SELECT count(id) FROM `likes` WHERE CURDATE() = date(`time`)) as likes_today,
		(SELECT count(id) FROM `likes` WHERE MONTH(CURDATE()) = MONTH(date(`time`)) AND YEAR(CURDATE()) = YEAR(date(`time`))) as likes_month,
		(SELECT count(id) FROM `likes` WHERE YEAR(CURDATE()) = YEAR(date(`time`))) as likes_year";
	}
	$result = $db->query($query);
	while($row = $result->fetch_assoc()) {
		$rows[] = $row;
	}
	$stats = array();
	foreach($rows[0] as $value) {
		$stats[] = $value;
	}
	return $stats;
}
function percentage($current, $old) {
    $result = number_format((($current - $old) / $old * 100), 0);
	if($result < 0) {
		return '<span class="negative">'.$result.'%</span>';
	} elseif($result > 0) {
		return '<span class="positive">+'.$result.'%</span>';
	} else {
		return '<span class="neutral">'.$result.'%</span>';
	}
}
function smiles() {
	// Define smiles
	$smiles = array(
		':)'	=> 'smile.png',
		':('	=> 'sad.png',
		';)'	=> 'wink.png',
		'xD'	=> 'devil.png',
		'x('	=> 'angry.png',
		'=('	=> 'cry.png',
		':*'	=> 'kiss.png',
		':D'	=> 'laugh.png',
		':x'	=> 'love.png',
		'(:|'	=> 'sleepy.png',
		'B)'	=> 'cool.png',
		':P'	=> 'cheeky.png',
		':\\'	=> 'confused.png',
		':o'	=> 'surprised.png',
		'&lt;3'	=> 'heart.png',
		'(y)'	=> 'like.png',
	);
	return $smiles;
}
function fsize($bytes) { #Determine the size of the file, and print a human readable value
   if ($bytes < 1024) return $bytes.' B';
   elseif ($bytes < 1048576) return round($bytes / 1024, 2).' KiB';
   elseif ($bytes < 1073741824) return round($bytes / 1048576, 2).' MiB';
   elseif ($bytes < 1099511627776) return round($bytes / 1073741824, 2).' GiB';
   else return round($bytes / 1099511627776, 2).' TiB';
}
function audioContainer($type, $sound) {
	global $CONF;
	if($sound) {
		$output = '<audio id="soundNew'.$type.'"><source src="'.$CONF['url'].'/'.$CONF['theme_url'].'/sounds/sound'.$type.'.ogg" type="audio/ogg"><source src="'.$CONF['url'].'/'.$CONF['theme_url'].'/sounds/sound'.$type.'.mp3" type="audio/mpeg"><source src="'.$CONF['url'].'/'.$CONF['theme_url'].'/sounds/sound'.$type.'.wav" type="audio/wav"></audio>';
	} else {
		$output = '<audio id="soundNew'.$type.'"></audio>';
	}
	return $output;
}
function realName($username, $first = null, $last = null, $fullname = null) {
	if($fullname) {
		if($first && $last) {
			return $first.' '.$last;
		} else {
			return $username;
		}
	}
	if($first && $last) {
		return $first.' '.$last;
	} elseif($first) {
		return $first;
	} elseif($last) {
		return $last;
	} elseif($username) { // If username is not set, return empty (example: the real-name under the subscriptions)
		return $username;
	}
}
function showUsers($users, $url) {
	foreach($users as $user) {
		$x .= '<div class="welcome-user"><a href="'.$url.'/index.php?a=profile&u='.$user['username'].'" rel="loadpage"><img src="'.$url.'/thumb.php?src='.$user['image'].'&t=a&w=112&h=112"></a></div>';
	}
	return $x;
}
function parseCallback($matches) {
	// If match www. at the beginning of the string, add http before
	if(substr($matches[1], 0, 4) == 'www.') {
		$url = 'http://'.$matches[1];
	} else {
		$url = $matches[1];
	}
	return '<a href="'.$url.'" target="_blank" rel="nofollow">'.$matches[1].'</a>';
}
function generateDateForm($type, $current) {
	global $LNG;
	$rows = '';
	if($type == 0) {
		$rows .= '<option value="">'.$LNG['year'].'</option>';
		for ($i = date('Y'); $i >= (date('Y') - 100); $i--) {
			if($i == $current) {
				$selected = ' selected="selected"';
			} else {
				$selected = '';
			}
			$rows .= '<option value="'.$i.'"'.$selected.'>'.$i.'</option>';
		}
	} elseif($type == 1) {
		$rows .= '<option value="">'.$LNG['month'].'</option>';
		for ($i = 1; $i <= 12; $i++) {
			if($i == $current) {
				$selected = ' selected="selected"';
			} else {
				$selected = '';
			}
			$rows .= '<option value="'.$i.'"'.$selected.'>'.$LNG["month_$i"].'</option>';
		}
	} elseif($type == 2) {
		$rows .= '<option value="">'.$LNG['day'].'</option>';
		for ($i = 1; $i <= 31; $i++) {
			if($i == $current) {
				$selected = ' selected="selected"';
			} else {
				$selected = '';
			}
			$rows .= '<option value="'.$i.'"'.$selected.'>'.$i.'</option>';
		}
	}
	return $rows;
}
function generateAd($content) {
	global $LNG;
	if(empty($content)) {
		return false;
	}
	$ad = '<div class="sidebar-container widget-ad"><div class="sidebar-content"><div class="sidebar-header">'.$LNG['sponsored'].'</div>'.$content.'</div></div>';
	return $ad;
}
function sortDateDesc($a, $b) {
	// Convert the array value into a UNIX timestamp
	strtotime($a['time']);
	strtotime($b['time']);
	
	return strcmp($a['time'], $b['time']);
}
function sortDateAsc($a, $b) {
	// Convert the array value into a UNIX timestamp
	strtotime($a['time']); 
	strtotime($b['time']);
	
	if ($a['time'] == $b['time']) {
		return 0;
	}
	return ($a['time'] > $b['time']) ? -1 : 1;  
}
function sortOnlineUsers($a, $b) {
	// Convert the array value into a UNIX timestamp
	strtotime($a['online']);
	strtotime($b['online']);
	
	if ($a['online'] == $b['online']) {
		return 0;
	}
	return ($a['online'] > $b['online']) ? -1 : 1;  
}
function getLanguage($url, $ln = null, $type = null) {
	// Type 1: Output the available languages
	// Type 2: Change the path for the /requests/ folder location
	// Set the directory location
	if($type == 2) {
		$languagesDir = '../languages/';
	} else {
		$languagesDir = './languages/';
	}
	// Search for pathnames matching the .png pattern
	$language = glob($languagesDir . '*.php', GLOB_BRACE);

	if($type == 1) {
		// Add to array the available languages
		foreach($language as $lang) {
			// The path to be parsed
			$path = pathinfo($lang);
			
			// Add the filename into $available array
			$available .= '<a href="'.$url.'/index.php?lang='.$path['filename'].'">'.ucfirst(strtolower($path['filename'])).'</a> - ';
		}
		return substr($available, 0, -3);
	} else {
		// If get is set, set the cookie and stuff
		$lang = 'english'; // Default Language
		if($type == 2) {
			$path = '../languages/';
		} else {
			$path = './languages/';
		}
		if(isset($_GET['lang'])) {
			if(in_array($path.$_GET['lang'].'.php', $language)) {
				$lang = $_GET['lang'];
				setcookie('lang', $lang, time() +  (10 * 365 * 24 * 60 * 60)); // Expire in one month
			} else {
				setcookie('lang', $lang, time() +  (10 * 365 * 24 * 60 * 60)); // Expire in one month
			}
		} elseif(isset($_COOKIE['lang'])) {
			if(in_array($path.$_COOKIE['lang'].'.php', $language)) {
				$lang = $_COOKIE['lang'];
			}
		} else {
			setcookie('lang', $lang, time() +  (10 * 365 * 24 * 60 * 60)); // Expire in one month
		}

		if(in_array($path.$lang.'.php', $language)) {
			return $path.$lang.'.php';
		}
	}
}
function saniscape($value) {
	return htmlspecialchars(addslashes($value), ENT_QUOTES, 'UTF-8');
}
function generateToken($type = null) {
	if($type) {
		return '<input type="hidden" name="token_id" value="'.$_SESSION['token_id'].'">';
	} else {
		if(!isset($_SESSION['token_id'])) {
			$token_id = md5(substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10));
			$_SESSION['token_id'] = $token_id;
			return $_SESSION['token_id'];
		}
		return $_SESSION['token_id'];
	}
}
function getUserIp() {
	if($_SERVER['REMOTE_ADDR']) {
		return $_SERVER['REMOTE_ADDR'];
	} else {
		return false;
	}
}
function adminMenuCounts($db, $type) {
	// Type 0: Return the reports number
	
	if($type == 0) {
		$query = $db->query('SELECT COUNT(`id`) as `count` FROM `reports` WHERE `state` = 0');
	}
	$result = $query->fetch_assoc();
	
	return $result['count'];
}
function imageOrientation($filename) {
	if(function_exists('exif_read_data')) {
		// Read the image exif data
		$exif = exif_read_data($filename);
		
		// Store the image exif orientation data
		$orientation = $exif['Orientation'];
		
		// Check whether the image has an orientation, and if the orientation is 3, 6, 8
		if(!empty($orientation) && in_array($orientation, array(3, 6, 8))) {
			$image = imagecreatefromjpeg($filename);
			if($orientation == 3) {
				$image = imagerotate($image, 180, 0);
			} elseif($orientation == 6) {
				$image = imagerotate($image, -90, 0);
			} elseif($orientation == 8) {
				$image = imagerotate($image, 90, 0);
			}
			
			// Save the new rotated image
			imagejpeg($image, $filename, 90);
		}
	}
}
function deletePhotos($type, $value) {
	// If the message type is picture
	if($type == 'picture') {		
		// Explode the images string value
		$images = explode(',', $value);
		
		// Remove any empty array elements
		$images = array_filter($images);
		
		// Delete each image
		foreach($images as $image) {
			unlink(__DIR__ .'/../uploads/media/'.$image);
		}
	}
}
function deleteImages($image, $type) {
	// Type 0: Delete covers
	// Type 1: Delete avatars
	
	$path = ($type ? 'avatars' : 'covers');
	foreach($image as $name) {
		if($name !== 'default.png') {
			unlink(__DIR__ .'/../uploads/'.$path.'/'.$name);
		}
	}
}
function fetch($url) {
	if(function_exists('curl_exec')) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2490.80 Safari/537.36');
		$response = curl_exec($ch);
	}
	if(empty($response)) {
		$response = file_get_contents($url);
	}
	return $response;
}
function plugin($event, $values = null, $type = null) {
   /*
	*
	* @param string 			$events		Function name to be loaded and executed
	* @param string(array)		$values		The values to be passed to the function
	* @param string				$type		The type of the request, 1 to append _output, 2 to append _sidebar
	*
	*/
	if($type == 1) {
		$suffix = '_output';
	} elseif($type == 2) {
		$suffix = '_sidebar';
	} elseif($type == 3) {
		$suffix = '_event';
	} elseif($type == 4) {
		$suffix = '_delete';
	} else {
		$suffix = ''; 
	}
	$fn = ($type) ? $event.$suffix : $event;

	// Define the path of the plugin
	$path = __DIR__ .'/../plugins/'.$event.'/'.$fn.'.php';

	// Check if the file exists and open it
	if(file_exists($path)) {
		require_once($path);
	} else {
		return false;
	}
	
	$plugin = call_user_func($fn, $values);

	// If there is an output and the $type is for Messages event or Sidebar
	if($plugin && ($type == 1 || $type == 2)) {
		return $plugin;
	}
	// Else if there is an output with no $type
	else {
		$output = $plugin;
	}
	return $output;
}
function loadPlugins($db) {
	if($type == 0) {
		$query = $db->query('SELECT * FROM `plugins` ORDER BY `id` DESC');
	}
	while($column = $query->fetch_assoc()) {
		$result[] = array('name' => $column['name'], 'type' => $column['type']);
	}
	return $result;
}
?>