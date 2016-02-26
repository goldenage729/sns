<?php
session_start();
require_once('./includes/config.php');
require_once('./includes/skins.php');
require_once('./includes/classes.php');
require_once(getLanguage(null, (!empty($_GET['lang']) ? $_GET['lang'] : $_COOKIE['lang']), null));
$db = new mysqli($CONF['host'], $CONF['user'], $CONF['pass'], $CONF['name']);
if ($db->connect_errno) {
    echo "Failed to connect to MySQL: (" . $db->connect_errno . ") " . $db->connect_error;
}
$db->set_charset("utf8");

if(isset($_GET['a']) && isset($action[$_GET['a']])) {
	$page_name = $action[$_GET['a']];
} else {
	$page_name = 'welcome';
}

// Extra class for the content [main and sidebar]
$TMPL['content_class'] = ' content-'.$page_name;

$resultSettings = $db->query(getSettings());
// Added to verify whether the user imported the database or not
if($resultSettings) {
	$settings = $resultSettings->fetch_assoc();
} else {
	echo "Error: ".$db->error;
}

require_once("./sources/{$page_name}.php");

// Store the theme path and theme name into the CONF and TMPL
$TMPL['theme_path'] = $CONF['theme_path'];
$TMPL['theme_name'] = $CONF['theme_name'] = $settings['theme'];
$TMPL['theme_url'] = $CONF['theme_url'] = $CONF['theme_path'].'/'.$CONF['theme_name'];

if(isset($_SESSION['username']) && isset($_SESSION['password']) || isset($_COOKIE['username']) && isset($_COOKIE['password'])) {
	$loggedIn = new loggedIn();
	$loggedIn->db = $db;
	$loggedIn->url = $CONF['url'];
	$loggedIn->username = (isset($_SESSION['username'])) ? $_SESSION['username'] : $_COOKIE['username'];
	$loggedIn->password = (isset($_SESSION['password'])) ? $_SESSION['password'] : $_COOKIE['password'];
	
	$verify = $loggedIn->verify();
}

$plugins = loadPlugins($db);

// Load the head plugins
foreach($plugins as $plugin) {
	if(array_intersect(array("8"), str_split($plugin['type']))) {
		$TMPL['styles'] .= "\n<link href=\"".$CONF['url']."/plugins/".$plugin['name']."/".$plugin['name'].".css\" rel=\"stylesheet\" type=\"text/css\">";
	}
}

foreach($plugins as $plugin) {
	if(array_intersect(array("9"), str_split($plugin['type']))) {
		$TMPL['scripts'] .= "\n<script type=\"text/javascript\" src=\"".$CONF['url']."/plugins/".$plugin['name']."/".$plugin['name'].".js\"></script>";
	}
}

echo json_encode(array('content' => PageMain(), 'title' => $TMPL['title']));
mysqli_close($db);
?>