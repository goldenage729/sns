<?php
require_once('./includes/countries.php');
function PageMain() {
	global $TMPL, $LNG, $CONF, $db, $settings;
	
	if(isset($_POST['login'])) {
		$logInAdmin = new logInAdmin();
		$logInAdmin->db = $db;
		$logInAdmin->url = $CONF['url'];
		$logInAdmin->username = $_POST['username'];
		$logInAdmin->password = $_POST['password'];
		
		$TMPL['message'] = $logInAdmin->in();
	}
	if(isset($_SESSION['usernameAdmin']) && isset($_SESSION['passwordAdmin'])) {
		$loggedInAdmin = new loggedInAdmin();
		$loggedInAdmin->db = $db;
		$loggedInAdmin->url = $CONF['url'];
		$loggedInAdmin->username = $_SESSION['usernameAdmin'];
		$loggedInAdmin->password = $_SESSION['passwordAdmin'];
		$loggedIn = $loggedInAdmin->verify();

		if($loggedIn['username']) {
			// Set the content to true, change the $skin to content
			$content = true;
			
			$TMPL_old = $TMPL; $TMPL = array();
			
			$TMPL['url'] = $CONF['url'];
			$TMPL['token_input'] = generateToken($_SESSION['token_id']);
			$TMPL['token_id'] = $_SESSION['token_id'];
			
			if($_GET['b'] == 'security') { // Security Admin Tab
				$skin = new skin('admin/security'); $page = '';

				if(!empty($_POST)) {
					$updateSettings = new updateSettings();
					$updateSettings->db = $db;
					$updated = $updateSettings->query_array('admin', $_POST);
					
					header("Location: ".$CONF['url']."/index.php?a=admin&b=security&m=".$updated);
				}
				
				if($_GET['m'] == 1) {
					$TMPL['message'] = notificationBox('success', $LNG['password_changed']);
				} elseif($_GET['m'] == 2) {
					$TMPL['message'] = notificationBox('error', $LNG['wrong_current_password']);
				} elseif($_GET['m'] == 3) {
					$TMPL['message'] = notificationBox('error', $LNG['password_not_match']);
				} elseif($_GET['m'] == 4) {
					$TMPL['message'] = notificationBox('error', $LNG['password_too_short']);
				} elseif($_GET['m'] == 0 && isset($_GET['m'])) {
					$TMPL['message'] = notificationBox('info', $LNG['password_not_changed']);
				}
			} elseif($_GET['b'] == 'social') { // Security Admin Tab
				$skin = new skin('admin/social'); $page = '';
				
				if(!extension_loaded('openssl')) {
					$TMPL['message'] .= notificationBox('error', $LNG['openssl_error']);
				}
				if(!function_exists('curl_exec')) {
					$TMPL['message'] .= notificationBox('info', $LNG['curl_error']);
				}

				$TMPL['fbappid'] = $settings['fbappid']; $TMPL['fbappsecret'] = $settings['fbappsecret'];

				if(empty($settings['fbapp'])) {
					$TMPL['fbappoff'] = ' selected="selected"';
				} else {
					$TMPL['fbappon'] = ' selected="selected"';
				}
				
				if(!empty($_POST)) {
					$updateSettings = new updateSettings();
					$updateSettings->db = $db;
					$updated = $updateSettings->query_array('settings', $_POST);
					if($updated == 1) {
						header("Location: ".$CONF['url']."/index.php?a=admin&b=social&m=s");
					} else {
						header("Location: ".$CONF['url']."/index.php?a=admin&b=social&m=i");
					}
				}
				
				if($_GET['m'] == 's') {
					$TMPL['message'] .= notificationBox('success', $LNG['settings_saved']);
				} elseif($_GET['m'] == 'i') {
					$TMPL['message'] .= notificationBox('info', $LNG['nothing_changed']);
				}
			} elseif($_GET['b'] == 'stats') { // Security Admin Tab
				$skin = new skin('admin/stats'); $page = '';

				list($TMPL['messages_total'], $TMPL['messages_today'], $TMPL['messages_this_month'], $TMPL['messages_this_year'], $TMPL['comments_total'], $TMPL['comments_today'], $TMPL['comments_this_month'], $TMPL['comments_this_year'], $TMPL['shared_total'], $TMPL['shared_today'], $TMPL['shared_this_month'], $TMPL['shared_this_year'], $TMPL['groups_total'], $TMPL['groups_today'], $TMPL['groups_this_month'], $TMPL['groups_this_year'], $TMPL['users_today'], $TMPL['users_this_month'], $TMPL['users_this_year'], $TMPL['users_total'], $TMPL['total_likes'], $TMPL['likes_today'], $TMPL['likes_this_month'], $TMPL['likes_this_year']) = admin_stats($db, 0);
			} elseif($_GET['b'] == 'themes') {
				$skin = new skin('admin/themes'); $page = '';
				$updateSettings = new updateSettings();
				$updateSettings->db = $db;
				
				$themes = $updateSettings->getThemes();
				
				$TMPL['themes_list'] = $themes[0];
				
				if(isset($_GET['theme'])) {
					// If theme is in array
					if(in_array($_GET['theme'], $themes[1])) {
						$updated = $updateSettings->query_array('settings', array('theme' => $_GET['theme'], 'token_id' => $_GET['token_id']));
						header("Location: ".$CONF['url']."/index.php?a=admin&b=themes");
					}
				}
			} elseif($_GET['b'] == 'plugins') {
				$skin = new skin('admin/plugins'); $page = '';
				$updateSettings = new updateSettings();
				$updateSettings->db = $db;
				
				if(isset($_GET['plugin']) && isset($_GET['plugin_type'])) {
					$updateSettings->activatePlugin($_GET['plugin'], $_GET['plugin_type']);
					header("Location: ".$CONF['url']."/index.php?a=admin&b=plugins");
				}
				
				$plugins = $updateSettings->getPlugins();
				
				$TMPL['plugins_list'] = $plugins[0];
			} elseif($_GET['b'] == 'manage_reports') {
				list($TMPL['total_reports'], $TMPL['pending_reports'], $TMPL['safe_reports'], $TMPL['deleted_reports'], $TMPL['total_message_reports'], $TMPL['pending_message_reports'], $TMPL['safe_message_reports'], $TMPL['deleted_message_reports'], $TMPL['total_comment_reports'], $TMPL['pending_comment_reports'], $TMPL['safe_comment_reports'], $TMPL['deleted_comment_reports']) = admin_stats($db, 1);
				$skin = new skin('admin/manage_reports'); $page = '';
				
				$manageReports = new manageReports();
				$manageReports->db = $db;
				$manageReports->url = $CONF['url'];
				$manageReports->per_page = $settings['uperpage'];
				
				// Save the array returned into a list
				$TMPL['reports'] = $manageReports->getReports(0);				
			} elseif($_GET['b'] == 'manage_groups') {
				$feed = new feed();
				$feed->db = $db;
				$feed->url = $CONF['url'];

				if(isset($_GET['deleted'])) {
					$TMPL['message'] = notificationBox('success', sprintf($LNG['group_deleted'], $_GET['deleted']));
				}
				
				if(!empty($_GET['c'])) {
					$skin = new skin('admin/edit_group'); $page = '';
					$feed->group_data = $feed->groupData($_GET['c']);
					if(!empty($_POST)) {
						$message = $feed->createGroup($_POST, 1);
						$feed->group_data = $feed->groupData($_GET['c']);
					
						// If there's an error during group validation
						if($message[0]) {
							$TMPL['message'] = notificationBox('error', $message[1]);
						} else {
							if($message[1]) {
								$TMPL['message'] = notificationBox('success', $LNG['settings_saved']);
							} else {
								$TMPL['message'] = notificationBox('info', $LNG['nothing_changed']);
							}
						}
					}
					// The disabled attribute for inputs
					$TMPL['disabled'] = ' disabled';
					$TMPL['id'] = $feed->group_data['id'];
					$TMPL['current_name'] = $feed->group_data['name'];
					$TMPL['current_title'] = $feed->group_data['title'];
					$TMPL['current_desc'] = $feed->group_data['description'];
					if($feed->group_data['privacy'] == 1) {
						$TMPL['privacy_private'] = ' selected="selected"';
					} else {
						$TMPL['privacy_public'] = ' selected="selected"';
					}
					if($feed->group_data['posts'] == 1) {
						$TMPL['posts_admins'] = ' selected="selected"';
					} else {
						$TMPL['posts_members'] = ' selected="selected"';
					}
					$TMPL['group'] = '<div class="message-avatar"><a href="'.$CONF['url'].'/index.php?a=group&name='.$feed->group_data['name'].'" rel="loadpage"><img src="'.$CONF['url'].'/thumb.php?src='.$feed->group_data['cover'].'&t=c&w=48&h=48"></a></div><div class="message-top"><div class="message-author" rel="loadpage"><a href="'.$CONF['url'].'/index.php?a=group&name='.$feed->group_data['name'].'" rel="loadpage">'.$feed->group_data['name'].'</a></div><div class="message-time">'.sprintf($LNG['x_members'], $feed->countGroupMembers($feed->group_data['id'], 0)).'</div></div>';
				} else {
					$skin = new skin('admin/manage_groups'); $page = '';
					
					// Remove a group
					if(!empty($_GET['delete'])) {
						$group = $feed->groupOwner($_GET['delete']);
						$feed->id = $group['user'];
						$TMPL['message'] = $feed->deleteGroup($_GET['delete'], null, 1);
					}
					
					$feed->per_page = $settings['uperpage'];
					$TMPL['groups'] = $feed->getGroups(0, 0);
				}
			} elseif($_GET['b'] == 'users_settings') {
				$skin = new skin('admin/users_settings'); $page = '';
					
				if($settings['mprivacy'] == '1') {
					$TMPL['pon'] = 'selected="selected"';
				} else {
					$TMPL['poff'] = 'selected="selected"';
				}
				
				if($settings['notificationl'] == '0') {
					$TMPL['loff'] = 'selected="selected"';
				} else {
					$TMPL['lon'] = 'selected="selected"';
				}
				
				if($settings['notificationc'] == '0') {
					$TMPL['coff'] = 'selected="selected"';
				} else {
					$TMPL['con'] = 'selected="selected"';
				}
				
				if($settings['sound_new_notification'] == '0') {
					$TMPL['snnoff'] = 'selected="selected"';
				} else {
					$TMPL['snnon'] = 'selected="selected"';
				}
				
				if($settings['sound_new_chat'] == '0') {
					$TMPL['sncoff'] = 'selected="selected"';
				} else {
					$TMPL['sncon'] = 'selected="selected"';
				}
				
				if($settings['email_comment'] == '0') {
					$TMPL['ecoff'] = 'selected="selected"';
				} else {
					$TMPL['econ'] = 'selected="selected"';
				}
				
				if($settings['email_like'] == '0') {
					$TMPL['eloff'] = 'selected="selected"';
				} else {
					$TMPL['elon'] = 'selected="selected"';
				}
				
				if($settings['email_new_friend'] == '0') {
					$TMPL['enfoff'] = 'selected="selected"';
				} else {
					$TMPL['enfon'] = 'selected="selected"';
				}
				
				if($settings['email_group_invite'] == '0') {
					$TMPL['egioff'] = 'selected="selected"';
				} else {
					$TMPL['egion'] = 'selected="selected"';
				}
				
				if($settings['notifications'] == '0') {
					$TMPL['soff'] = 'selected="selected"';
				} else {
					$TMPL['son'] = 'selected="selected"';
				}
				
				if($settings['notificationd'] == '0') {
					$TMPL['doff'] = 'selected="selected"';
				} else {
					$TMPL['don'] = 'selected="selected"';
				}
				
				if($settings['notificationf'] == '0') {
					$TMPL['foff'] = 'selected="selected"';
				} else {
					$TMPL['fon'] = 'selected="selected"';
				}
				
				if($settings['notificationg'] == '0') {
					$TMPL['goff'] = 'selected="selected"';
				} else {
					$TMPL['gon'] = 'selected="selected"';
				}
				
				if($settings['ilimit'] == '1') {
					$TMPL['ione'] = 'selected="selected"';
				} elseif($settings['ilimit'] == '3') {
					$TMPL['ithree'] = 'selected="selected"';
				} elseif($settings['ilimit'] == '6') {
					$TMPL['isix'] = 'selected="selected"';
				} else {
					$TMPL['inine'] = 'selected="selected"';
				}
				
				if($settings['nperwidget'] == '5') {
					$TMPL['none'] = 'selected="selected"';
				} elseif($settings['nperwidget'] == '10') {
					$TMPL['ntwo'] = 'selected="selected"';
				} elseif($settings['nperwidget'] == '20') {
					$TMPL['nthree'] = 'selected="selected"';
				} else {
					$TMPL['nfour'] = 'selected="selected"';
				}
				
				if($settings['lperpost'] == '3') {
					$TMPL['likesone'] = 'selected="selected"';
				} elseif($settings['lperpost'] == '5') {
					$TMPL['likestwo'] = 'selected="selected"';
				} else {
					$TMPL['likesnone'] = 'selected="selected"';
				}
								
				if($settings['verified'] == 0) {
					$TMPL['off_v'] = 'selected="selected"';
				} else {
					$TMPL['on_v'] = 'selected="selected"';
				}

				if(!empty($_POST)) {
					$updateSettings = new updateSettings();
					$updateSettings->db = $db;
					$updated = $updateSettings->query_array('settings', $_POST);
					if($updated == 1) {
						header("Location: ".$CONF['url']."/index.php?a=admin&b=users_settings&m=s");
					} else {
						header("Location: ".$CONF['url']."/index.php?a=admin&b=users_settings&m=i");
					}
				}
				
				if($_GET['m'] == 's') {
					$TMPL['message'] = notificationBox('success', $LNG['settings_saved']);
				} elseif($_GET['m'] == 'i') {
					$TMPL['message'] = notificationBox('info', $LNG['nothing_saved']);
				}
			} elseif($_GET['b'] == 'users') {
				$manageUsers = new manageUsers();
				$manageUsers->db = $db;
				$manageUsers->url = $CONF['url'];
				$manageUsers->title = $settings['title'];
				$manageUsers->per_page = $settings['uperpage'];
				
				if(!isset($_GET['e'])) {
					$skin = new skin('admin/manage_users'); $page = '';
					
					// Save the array returned into a list
					$TMPL['users'] = $manageUsers->getUsers(0);
				} else {
					$skin = new skin('admin/edit_user'); $page = '';
					$getUser = $manageUsers->getUser($_GET['e'], $_GET['ef']);
					if(!$getUser) {
						header("Location: ".$CONF['url']."/index.php?a=admin&b=users&m=un");
					}
					// Create the class instance
					$updateUserSettings = new updateUserSettings();
					$updateUserSettings->db = $db;
					$updateUserSettings->id = $getUser['idu'];
					
					if(!empty($_POST)) {
						$TMPL['message'] = $updateUserSettings->query_array('users', array_map("strip_tags_array", $_POST));
					}
					
					$feed = new feed();
					$feed->db = $db;
					$feed->id = $updateUserSettings->id;
					
					if(isset($_GET['suspend'])) {
						$manageUsers->suspendUser($feed->id, $_GET['suspend']);
						header("Location: ".$CONF['url']."/index.php?a=admin&b=users&e=".$_GET['e']);
					}
					
					$userSettings = $updateUserSettings->getSettings();
					
					$TMPL['countries'] = countries(1, $userSettings['country']);
					$date = explode('-', $userSettings['born']);
					
					$TMPL['years'] = generateDateForm(0, $date[0]);
					$TMPL['months'] = generateDateForm(1, $date[1]);
					$TMPL['days'] = generateDateForm(2, $date[2]);
					
					$TMPL['username'] = $userSettings['username']; $TMPL['idu'] = $userSettings['idu']; $TMPL['currentFirstName'] = $userSettings['first_name']; $TMPL['currentLastName'] = $userSettings['last_name']; $TMPL['currentEmail'] = $userSettings['email']; $TMPL['currentLocation'] = $userSettings['location']; $TMPL['currentWebsite'] = $userSettings['website']; $TMPL['currentBio'] = $userSettings['bio']; $TMPL['currentFacebook'] = $userSettings['facebook']; $TMPL['currentTwitter'] = $userSettings['twitter'];  $TMPL['currentGplus'] = $userSettings['gplus']; $TMPL['join_date'] = $userSettings['date'];  $TMPL['currentAddress'] = $userSettings['address']; $TMPL['currentWork'] = $userSettings['work']; $TMPL['currentSchool'] = $userSettings['school'];
					
					if($userSettings['verified'] == 0) {
						$TMPL['off_v'] = 'selected="selected"';
					} else {
						$TMPL['on_v'] = 'selected="selected"';
					}
					
					if($userSettings['gender'] == '0') {
						$TMPL['ngender'] = 'selected="selected"';
					} elseif($userSettings['gender'] == '1') {
						$TMPL['mgender'] = 'selected="selected"';
					} else {
						$TMPL['fgender'] = 'selected="selected"';
					}
					
					if($userSettings['interests'] == '0') {
						$TMPL['ninterests'] = 'selected="selected"';
					} elseif($userSettings['interests'] == '1') {
						$TMPL['minterests'] = 'selected="selected"';
					} else {
						$TMPL['winterests'] = 'selected="selected"';
					}
					
					$TMPL['user'] = '<div class="message-avatar" id="avatar'.$userSettings['idu'].'"><a href="'.$CONF['url'].'/index.php?a=profile&u='.$userSettings['username'].'" rel="loadpage"><img src="'.$CONF['url'].'/thumb.php?src='.$userSettings['image'].'&t=a&w=50&h=50"></a></div><div class="message-top"><div class="message-author" rel="loadpage"><a href="'.$CONF['url'].'/index.php?a=profile&u='.$userSettings['username'].'" rel="loadpage">'.$userSettings['username'].'</a></div><div class="message-time">'.$userSettings['email'].'</div></div>'; 
					
					// Suspend variable for the suspend url
					$TMPL['suspend'] = ($userSettings['suspended'] ? '0' : '1');
					
					$TMPL['message'] .= ($userSettings['suspended'] ? notificationBox('error', $LNG['account_suspended']) : '');
					
					if($userSettings['suspended']) {
						$TMPL['suspended'] = $LNG['restore'];
					} else {
						$TMPL['suspended'] = $LNG['suspend'];
					}
				}
				
				// If GET delete is set, delete the user
				if($_GET['delete'] && $_GET['token_id'] == $_SESSION['token_id']) {
					// Create the class instance
					$updateUserSettings = new updateUserSettings();
					$updateUserSettings->db = $db;
					$updateUserSettings->id = $_GET['delete'];
					$userSettings = $updateUserSettings->getSettings();
					
					// Delete the profile images
					deleteImages(array($userSettings['image']), 1);
					deleteImages(array($userSettings['cover']), 0);
					
					$manageUsers->deleteUser($_GET['delete']);
					header("Location: ".$CONF['url']."/index.php?a=admin&b=users&m=".$_GET['deleted']);
				}
				
				if($_GET['m'] == 'un') {
					$TMPL['message'] = notificationBox('error', $LNG['user_not_exist']);
				} elseif(!empty($_GET['m'])) {
					$TMPL['message'] = notificationBox('success', sprintf($LNG['user_has_been_deleted'], $_GET['m']));
				}
			} elseif($_GET['b'] == 'manage_ads') {
				$skin = new skin('admin/manage_ads'); $page = '';
				
				$TMPL['ad1'] = $settings['ad1']; $TMPL['ad2'] = $settings['ad2']; $TMPL['ad3'] = $settings['ad3']; $TMPL['ad4'] = $settings['ad4']; $TMPL['ad5'] = $settings['ad5']; $TMPL['ad6'] = $settings['ad6']; $TMPL['ad7'] = $settings['ad7'];
				if(!empty($_POST)) {
					// Unset the submit array element
					$updateSettings = new updateSettings();
					$updateSettings->db = $db;
					$updated = $updateSettings->query_array('settings', $_POST);
					if($updated == 1) {
						header("Location: ".$CONF['url']."/index.php?a=admin&b=manage_ads&m=s");
					} else {
						header("Location: ".$CONF['url']."/index.php?a=admin&b=manage_ads&m=i");
					}
				}
				if($_GET['m'] == 's') {
					$TMPL['message'] = notificationBox('success', $LNG['settings_saved']);
				} elseif($_GET['m'] == 'i') {
					$TMPL['message'] = notificationBox('info', $LNG['nothing_saved']);
				}
			} elseif($_GET['b'] == 'general') {
				$skin = new skin('admin/general'); $page = '';
				
				$TMPL['currentTitle'] = $settings['title']; $TMPL['currentFormat'] = $settings['format']; $TMPL['currentCensor'] = $settings['censor']; $TMPL['currentFormatMsg'] = $settings['formatmsg'];
						
				if($settings['captcha'] == '1') {
					$TMPL['on'] = 'selected="selected"';
				} else {
					$TMPL['off'] = 'selected="selected"';
				}
				
				if($settings['smiles'] == '1') {
					$TMPL['son'] = 'selected="selected"';
				} else {
					$TMPL['soff'] = 'selected="selected"';
				}
				
				if($settings['time'] == '0') {
					$TMPL['one'] = 'selected="selected"';
				} elseif($settings['time'] == '1') {
					$TMPL['two'] = 'selected="selected"';
				} elseif($settings['time'] == '2') {
					$TMPL['three'] = 'selected="selected"';
				} else {
					$TMPL['four'] = 'selected="selected"';
				}
				
				if($settings['conline'] == '60') {
					$TMPL['conone'] = 'selected="selected"';
				} elseif($settings['conline'] == '300') {
					$TMPL['contwo'] = 'selected="selected"';
				} else {
					$TMPL['conthree'] = 'selected="selected"';
				}
				
				if($settings['perpage'] == '10') {
					$TMPL['ten'] = 'selected="selected"';
				} elseif($settings['perpage'] == '20') {
					$TMPL['twenty'] = 'selected="selected"';
				} elseif($settings['perpage'] == '25') {
					$TMPL['twentyfive'] = 'selected="selected"';
				} else {
					$TMPL['fifty'] = 'selected="selected"';
				}
				
				if($settings['nperpage'] == '10') {
					$TMPL['nten'] = 'selected="selected"';
				} elseif($settings['nperpage'] == '25') {
					$TMPL['ntwentyfive'] = 'selected="selected"';
				} elseif($settings['nperpage'] == '50') {
					$TMPL['nfifty'] = 'selected="selected"';
				} else {
					$TMPL['nonehundred'] = 'selected="selected"';
				}
				
				if($settings['mperpage'] == '5') {
					$TMPL['mone'] = 'selected="selected"';
				} elseif($settings['mperpage'] == '10') {
					$TMPL['mtwo'] = 'selected="selected"';
				} elseif($settings['mperpage'] == '25') {
					$TMPL['mthree'] = 'selected="selected"';
				} else {
					$TMPL['mfour'] = 'selected="selected"';
				}
				
				if($settings['climit'] == '500') {
					$TMPL['cone'] = 'selected="selected"';
				} elseif($settings['climit'] == '1000') {
					$TMPL['ctwo'] = 'selected="selected"';
				} elseif($settings['climit'] == '2500') {
					$TMPL['cthree'] = 'selected="selected"';
				} else {
					$TMPL['cfour'] = 'selected="selected"';
				}
				
				if($settings['chatr'] == '1') {
					$TMPL['crone'] = 'selected="selected"';
				} elseif($settings['chatr'] == '2') {
					$TMPL['crtwo'] = 'selected="selected"';
				} elseif($settings['chatr'] == '3') {
					$TMPL['crthree'] = 'selected="selected"';
				} elseif($settings['chatr'] == '5') {
					$TMPL['crfive'] = 'selected="selected"';
				} elseif($settings['chatr'] == '10') {
					$TMPL['crten'] = 'selected="selected"';
				} elseif($settings['chatr'] == '30') {
					$TMPL['crthirty'] = 'selected="selected"';
				} elseif($settings['chatr'] == '60') {
					$TMPL['crsixty'] = 'selected="selected"';
				} else {
					$TMPL['croff'] = 'selected="selected"';
				}
				
				if($settings['qperpage'] == '10') {
					$TMPL['qten'] = 'selected="selected"';
				} elseif($settings['qperpage'] == '25') {
					$TMPL['qtwentyfive'] = 'selected="selected"';
				} elseif($settings['qperpage'] == '50') {
					$TMPL['qfifty'] = 'selected="selected"';
				} else {
					$TMPL['qonehundred'] = 'selected="selected"';
				}
				
				if($settings['cperpage'] == '3') {
					$TMPL['ctrei'] = 'selected="selected"';
				} elseif($settings['cperpage'] == '5') {
					$TMPL['ccinci'] = 'selected="selected"';
				} elseif($settings['cperpage'] == '10') {
					$TMPL['czece'] = 'selected="selected"';
				} else {
					$TMPL['ccinspe'] = 'selected="selected"';
				}
				
				if($settings['message'] == '500') {
					$TMPL['unu'] = 'selected="selected"';
				} elseif($settings['message'] == '1000') {
					$TMPL['doi'] = 'selected="selected"';
				} elseif($settings['message'] == '2500') {
					$TMPL['trei'] = 'selected="selected"';
				} else {
					$TMPL['patru'] = 'selected="selected"';
				}
				
				if($settings['size'] == '1048576') {
					$TMPL['onemb'] = 'selected="selected"';
				} elseif($settings['size'] == '2097152') {
					$TMPL['twomb'] = 'selected="selected"';
				} elseif($settings['size'] == '3145728') {
					$TMPL['threemb'] = 'selected="selected"';
				} else {
					$TMPL['tenmb'] = 'selected="selected"';
				}
				
				if($settings['mail'] == '1') {
					$TMPL['mailon'] = 'selected="selected"';
				} else {
					$TMPL['mailoff'] = 'selected="selected"';
				}
				
				if($settings['intervalm'] == '10000') {
					$TMPL['intone'] = 'selected="selected"';
				} elseif($settings['intervalm'] == '30000') {
					$TMPL['inttwo'] = 'selected="selected"';
				} elseif($settings['intervalm'] == '60000') {
					$TMPL['intthree'] = 'selected="selected"';
				}  elseif($settings['intervalm'] == '120000') {
					$TMPL['intfour'] = 'selected="selected"';
				} elseif($settings['intervalm'] == '300000') {
					$TMPL['intfive'] = 'selected="selected"';
				} else {
					$TMPL['intsix'] = 'selected="selected"';
				}
				
				if($settings['intervaln'] == '10000') {
					$TMPL['intonen'] = 'selected="selected"';
				} elseif($settings['intervaln'] == '30000') {
					$TMPL['inttwon'] = 'selected="selected"';
				} elseif($settings['intervaln'] == '60000') {
					$TMPL['intthreen'] = 'selected="selected"';
				}  elseif($settings['intervaln'] == '120000') {
					$TMPL['intfourn'] = 'selected="selected"';
				} elseif($settings['intervaln'] == '300000') {
					$TMPL['intfiven'] = 'selected="selected"';
				} else {
					$TMPL['intsixn'] = 'selected="selected"';
				}
				
				if($settings['sizemsg'] == '1048576') {
					$TMPL['onembMsg'] = 'selected="selected"';
				} elseif($settings['sizemsg'] == '2097152') {
					$TMPL['twombMsg'] = 'selected="selected"';
				} elseif($settings['sizemsg'] == '3145728') {
					$TMPL['threembMsg'] = 'selected="selected"';
				} else {
					$TMPL['tenmbMsg'] = 'selected="selected"';
				}
				
				if($settings['uperpage'] == '10') {
					$TMPL['upone'] = 'selected="selected"';
				} elseif($settings['uperpage'] == '20') {
					$TMPL['uptwo'] = 'selected="selected"';
				} elseif($settings['uperpage'] == '50') {
					$TMPL['upthree'] = 'selected="selected"';
				} else {
					$TMPL['upfour'] = 'selected="selected"';
				}
				
				if($settings['sperpage'] == '10') {
					$TMPL['sone'] = 'selected="selected"';
				} elseif($settings['sperpage'] == '20') {
					$TMPL['stwo'] = 'selected="selected"';
				} elseif($settings['sperpage'] == '25') {
					$TMPL['sthree'] = 'selected="selected"';
				} else {
					$TMPL['sfour'] = 'selected="selected"';
				}
				
				if($settings['aperip'] == '1') {
					$TMPL['ipone'] = 'selected="selected"';
				} elseif($settings['aperip'] == '3') {
					$TMPL['iptwo'] = 'selected="selected"';
				} elseif($settings['aperip'] == '5') {
					$TMPL['ipthree'] = 'selected="selected"';
				} elseif($settings['aperip'] == '10') {
					$TMPL['ipfour'] = 'selected="selected"';
				} else {
					$TMPL['ipoff'] = 'selected="selected"';
				}
				
				if(isset($_POST['submit'])) {
					// Unset the submit array element
					unset($_POST['submit']);
					$updateSettings = new updateSettings();
					$updateSettings->db = $db;
					$updated = $updateSettings->query_array('settings', $_POST);
					if($updated == 1) {
						header("Location: ".$CONF['url']."/index.php?a=admin&b=general&m=s");
					} else {
						header("Location: ".$CONF['url']."/index.php?a=admin&b=general&m=i");
					}
				}
				
				if($_GET['m'] == 's') {
					$TMPL['message'] = notificationBox('success', $LNG['settings_saved']);
				} elseif($_GET['m'] == 'i') {
					$TMPL['message'] = notificationBox('info', $LNG['nothing_saved']);
				}
			} else {
				$skin = new skin('admin/dashboard'); $page = '';

				// Get the Today's Activity
				list(
				$TMPL['users_today'], $TMPL['users_yesterday'], $TMPL['users_two_days'], $TMPL['users_three_days'], $TMPL['users_four_days'], $TMPL['users_five_days'], $TMPL['users_six_days'],
				$TMPL['groups_today'], $TMPL['groups_yesterday'], $TMPL['groups_two_days'], $TMPL['groups_three_days'], $TMPL['groups_four_days'], $TMPL['groups_five_days'], $TMPL['groups_six_days'],
				$TMPL['messages_today'], $TMPL['messages_yesterday'], $TMPL['messages_two_days'], $TMPL['messages_three_days'], $TMPL['messages_four_days'], $TMPL['messages_five_days'], $TMPL['messages_six_days'],
				$TMPL['comments_today'], $TMPL['comments_yesterday'], $TMPL['comments_two_days'], $TMPL['comments_three_days'], $TMPL['comments_four_days'], $TMPL['comments_five_days'], $TMPL['comments_six_days'],
				$TMPL['shares_today'], $TMPL['shares_yesterday'], $TMPL['shares_two_days'], $TMPL['shares_three_days'], $TMPL['shares_four_days'], $TMPL['shares_five_days'], $TMPL['shares_six_days'],
				$TMPL['likes_today'], $TMPL['likes_yesterday'], $TMPL['likes_two_days'], $TMPL['likes_three_days'], $TMPL['likes_four_days'], $TMPL['likes_five_days'], $TMPL['likes_six_days'],
				$TMPL['online_users']) = admin_stats($db, 2, array('conline' => $settings['conline']));
				
				// Stats to generate the graps for
				$stats = array('users', 'groups', 'messages', 'comments', 'shares', 'likes');
				
				foreach($stats as $val) {
					// Get the stats values
					$stats_days = array($TMPL[$val.'_today'], $TMPL[$val.'_yesterday'], $TMPL[$val.'_two_days'], $TMPL[$val.'_three_days'], $TMPL[$val.'_four_days'], $TMPL[$val.'_five_days'], $TMPL[$val.'_six_days']);
					
					// Get the maximum value in a day
					$val_max = max($stats_days);
					
					$i = 0;
					foreach($stats_days as $value) {
						// Get the dates
						$date = date('Y-m-d', strtotime("-$i days", strtotime(date('Y-m-d'))));
						$date = explode('-', $date);
						
						$month = intval($date[1]);
						
						// Calculate the percentage
						$percentage = ($value * 100) / $val_max;
						$TMPL[$val.'_stats'] .= '<li title="'.$LNG["month_$month"].' '.$date[2].': '.$value.' '.$LNG[$val].'"><span style="height:'.$percentage.'%"></span></li>';
						$i++;
					}
				}
				
				$TMPL['users_percentage'] = percentage($TMPL['users_today'], $TMPL['users_yesterday']);
				$TMPL['groups_percentage'] = percentage($TMPL['groups_today'], $TMPL['groups_yesterday']);
				$TMPL['messages_percentage'] = percentage($TMPL['messages_today'], $TMPL['messages_yesterday']);
				$TMPL['comments_percentage'] = percentage($TMPL['comments_today'], $TMPL['comments_yesterday']);
				$TMPL['shares_percentage'] = percentage($TMPL['shares_today'], $TMPL['shares_yesterday']);
				$TMPL['likes_percentage'] = percentage($TMPL['likes_today'], $TMPL['likes_yesterday']);
				
				// Count the enabled plugins
				$countPlugins = $db->query("SELECT * FROM `plugins`");
				
				// Get the current theme's info
				include(__DIR__ .'/../'.$CONF['theme_path'].'/'.$CONF['theme_name'].'/info.php');
				$TMPL['site_loaded'] = sprintf($LNG['site_loaded'], $CONF['url'].'/index.php?a=admin&b=themes', $name, $CONF['url'].'/index.php?a=admin&b=plugins', $countPlugins->num_rows);
				
				// Get the software's info
				include(__DIR__ .'/../info.php');
				$TMPL['site_version'] = sprintf($LNG['site_version'], $url, $name, $version);
				$TMPL['soft_url'] = $url;
			}
			
			$page .= $skin->make();
			$TMPL = $TMPL_old; unset($TMPL_old);
			$TMPL['settings'] = $page;
			
			if(isset($_GET['logout']) == 1 && $_GET['token_id'] == $_SESSION['token_id']) {
				$loggedInAdmin->logOut();
				header("Location: ".$CONF['url']."/index.php?a=admin");
			}
		} else {
			// Set the content to false, change the $skin to log-in.
			$content = false;
		}
	}
	
	// Bold the current link
	if(isset($_GET['b'])) {
		$LNG["admin_menu_{$_GET['b']}"] = $LNG["admin_menu_{$_GET['b']}"];
		$TMPL['welcome'] = $LNG["admin_ttl_{$_GET['b']}"];
	} else {
		$LNG["admin_menu_dashboard"] = $LNG["admin_menu_dashboard"];
		$TMPL['welcome'] = $LNG["admin_ttl_dashboard"];
	}

	$adminMenu = array(	''											=> array('admin_menu_dashboard', ''),
						'&b=general'								=> array('admin_menu_general', ''),
						'&b=users_settings' 						=> array('admin_menu_users_settings', ''),
						'&b=social'									=> array('admin_menu_social', ''),
						'&b=themes' 								=> array('admin_menu_themes', ''),
						'&b=plugins'								=> array('admin_menu_plugins', ''),
						'&b=stats'									=> array('admin_menu_stats', ''),
						'&b=users'									=> array('admin_menu_users', ''),
						'&b=manage_groups'							=> array('admin_menu_manage_groups', ''),
						'&b=manage_reports'							=> array('admin_menu_manage_reports', adminMenuCounts($db, 0)),
						'&b=manage_ads'								=> array('admin_menu_manage_ads', ),
						'&b=security'								=> array('admin_menu_security', ''),
						'&logout=1&token_id='.$_SESSION['token_id']	=> array('admin_menu_logout', ''));
	
	foreach($adminMenu as $link => $title) {
		if($link == '&b='.$_GET['b'] || $link == $_GET['b']) {
			$TMPL['admin_menu'] .= '<strong>';
			$ttl = $LNG[$title[0]];
		}
		$TMPL['admin_menu'] .= '<a href="'.$CONF['url'].'/index.php?a=admin'.$link.'" '.(($title[0] == 'admin_menu_logout') ? '' : 'rel="loadpage"').'>'.$LNG[$title[0]].' '.($title[1] ? '<span class="admin-notifications-number">'.$title[1].'</span>' : '').'</a>';
		if($link == '&b='.$_GET['b'] || $link == $_GET['b']) {
			$TMPL['admin_menu'] .= '</strong>';
		}
	}
	
	$TMPL['url'] = $CONF['url'];
	$TMPL['localurl'] = $CONF['url'];
	$TMPL['title'] = $LNG['title_admin'].' - '.($loggedIn['username'] ? $ttl : $LNG['login']).' - '.$settings['title'];
	if($content) {
		$skin = new skin('admin/content');
	} else {
		$skin = new skin('admin/login');
	}
	return $skin->make();
}
?>