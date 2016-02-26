<?php
function PageMain() {
	global $TMPL, $LNG, $CONF, $db, $loggedIn, $settings, $plugins;
	
	if($settings['captcha']) {
		$TMPL['captcha'] = '<input type="text" name="captcha" placeholder="'.$LNG['captcha'].'" style="background-image: url('.$CONF['url'].'/includes/captcha.php)" class="welcome-captcha">';
	}
	
	if($settings['fbapp']) {
		// Generate a session to prevent CSFR
		$_SESSION['state'] = md5(uniqid(rand(), TRUE));
		
		// Facebook Login Url
		$TMPL['fblogin'] = '<div class="facebook-button"><a href="https://www.facebook.com/dialog/oauth?client_id='.$settings['fbappid'].'&redirect_uri='.$CONF['url'].'/index.php?facebook=true&state='.$_SESSION['state'].'&scope=public_profile,email" class="facebook-button">Facebook</a></div>';
	}
	
	if(isset($_GET['facebook']) && $settings['fbappid']) {
		$reg = new register();
		$reg->db = $db;
		$reg->url = $CONF['url'];
		$reg->username = $_POST['username'];
		$reg->password = $_POST['password'];
		$reg->email = $_POST['email'];
		$reg->captcha = $_POST['captcha'];
		$reg->captcha_on = $settings['captcha'];
		$reg->message_privacy = $settings['mprivacy'];
		$reg->like_notification = $settings['notificationl'];
		$reg->comment_notification = $settings['notificationc'];
		$reg->shared_notification = $settings['notifications'];
		$reg->chat_notification = $settings['notificationd'];
		$reg->friend_notification = $settings['notificationf'];
		$reg->verified = $settings['verified'];
		$reg->email_like = $settings['email_like'];
		$reg->email_comment = $settings['email_comment'];
		$reg->email_new_friend = $settings['email_new_friend'];
		$reg->sound_new_notification = $settings['sound_new_notification'];
		$reg->sound_new_chat = $settings['sound_new_chat'];
		$reg->fbapp = $settings['fbapp'];
		$reg->fbappid = $settings['fbappid'];
		$reg->fbappsecret = $settings['fbappsecret'];
		$reg->fbcode = $_GET['code'];
		$reg->fbstate = $_GET['state'];
		$TMPL['registerMsg'] = $reg->facebook();

		if($TMPL['registerMsg'] == 1) {
			if($settings['mail']) {
				sendMail($reg->email, sprintf($LNG['welcome_mail'], $settings['title']), sprintf($LNG['user_created'], $settings['title'], $reg->username, $reg->password, $CONF['url'], $settings['title'], $CONF['url'], $settings['title']), $CONF['email']);
			}
			header("Location: ".$CONF['url']);
		}
	}
	
	if(isset($_POST['register'])) {
		// Register usage
		$reg = new register();
		$reg->db = $db;
		$reg->url = $CONF['url'];
		$reg->username = $_POST['username'];
		$reg->password = $_POST['password'];
		$reg->email = $_POST['email'];
		$reg->captcha = $_POST['captcha'];
		$reg->captcha_on = $settings['captcha'];
		$reg->message_privacy = $settings['mprivacy'];
		$reg->like_notification = $settings['notificationl'];
		$reg->comment_notification = $settings['notificationc'];
		$reg->shared_notification = $settings['notifications'];
		$reg->chat_notification = $settings['notificationd'];
		$reg->friend_notification = $settings['notificationf'];
		$reg->group_notification = $settings['notificationg'];
		$reg->verified = $settings['verified'];
		$reg->email_like = $settings['email_like'];
		$reg->email_comment = $settings['email_comment'];
		$reg->email_new_friend = $settings['email_new_friend'];
		$reg->email_group_invite = $settings['email_group_invite'];
		$reg->sound_new_notification = $settings['sound_new_notification'];
		$reg->sound_new_chat = $settings['sound_new_chat'];
		$reg->accounts_per_ip = $settings['aperip'];
		
		$TMPL['registerMsg'] = $reg->process();

		if($TMPL['registerMsg'] == 1) {
			if($settings['mail']) {
				sendMail($_POST['email'], sprintf($LNG['welcome_mail'], $settings['title']), sprintf($LNG['user_created'], $settings['title'], $_POST['username'], $_POST['password'], $CONF['url'], $settings['title']), $CONF['email']);
			}
			header("Location: ".$CONF['url']."/index.php?a=feed");
		}
	}
	
	if(isset($_POST['login'])) {
		// Log-in usage
		$log = new logIn();
		$log->db = $db;
		$log->url = $CONF['url'];
		$log->username = $_POST['username'];
		$log->password = $_POST['password'];
		$log->remember = $_POST['remember'];
		
		$TMPL['loginMsg'] = notificationBox('error', $log->in(), 1);
	}
	
	if(isset($_SESSION['username']) && isset($_SESSION['password']) || isset($_COOKIE['username']) && isset($_COOKIE['password'])) {
		
		$verify = $loggedIn->verify();

		if($verify['username']) {
			header("Location: ".$CONF['url']."/index.php?a=feed");
		}
	}
	
	// Start displaying the home-page users
	
	$result = $db->query("SELECT * FROM `users` WHERE `image` != 'default.png' ORDER BY `idu` DESC LIMIT 10 ");
	while($row = $result->fetch_assoc()) {
		$users[] = $row;
	}
	
	$TMPL['rows'] = showUsers($users, $CONF['url']);
	
	$TMPL['url'] = $CONF['url'];
	$TMPL['title'] = $LNG['welcome'].' - '.$settings['title'];
	
	$TMPL['ad'] = $settings['ad1'];
	
	// Load the welcome plugins
	foreach($plugins as $plugin) {
		if(array_intersect(array("4"), str_split($plugin['type']))) {
			$data['site_url'] = $CONF['url']; $data['site_title'] = $settings['title']; $data['site_email'] = $CONF['email'];
			$TMPL['plugins'] .= plugin($plugin['name'], $data, 0);
		}
	}
	
	$skin = new skin('welcome/content');
	return $skin->make();
}
?>