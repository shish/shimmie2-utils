#!/usr/bin/php
<?php
function add_image($tmpname, $filename, $tags) {
	if(file_exists($tmpname)) {
		global $user;
		$pathinfo = pathinfo($filename);
		$metadata['filename'] = $pathinfo['basename'];
		$metadata['extension'] = $pathinfo['extension'];
		$metadata['tags'] = $tags;
		$metadata['source'] = null;
		$event = new DataUploadEvent($user, $tmpname, $metadata);
		send_event($event);
		if($event->vetoed) {
			return $event->veto_reason;
		}
	}
}

function add_dir($base, $subdir="") {
	global $page;
	
	if(!is_dir($base)) {
		print "Error: $base is not a directory\n";
		return;
	}

	print "Adding $subdir\n";
	
	$dir = opendir("$base/$subdir");
	while($filename = readdir($dir)) {
		$fullpath = "$base/$subdir/$filename";
	
		if(is_link($fullpath)) {
			// ignore
		}
		else if(is_dir($fullpath)) {
			if($filename[0] != ".") {
				add_dir($base, "$subdir/$filename");
			}
		}
		else {
			$tmpfile = $fullpath;
			$tags = $subdir;
			$tags = str_replace("/", " ", $tags);
			$tags = str_replace("__", " ", $tags);
			print "$subdir/$filename (".str_replace(" ", ",", $tags).")... ";
			$error = add_image($tmpfile, $filename, $tags);
			if(is_null($error)) {
				print "ok\n";
			}
			else {
				print "failed: $error\n";
			}
		}
	}
	closedir($dir);
}

// ===========================================================================

// set up and purify the environment
define("DEBUG", true);
define("VERSION", 'trunk');

require_once "core/util.inc.php";

version_check();
sanitise_environment();
check_cli();

// load base files
$files = array_merge(glob("core/*.php"), glob("ext/*/main.php"));
foreach($files as $filename) {
	require_once $filename;
}


// connect to database
$database = new Database();
$database->db->fnExecute = '_count_execs';
$config = new Config($database);


// load the theme parts
$_theme = $config->get_string("theme", "default");
if(!file_exists("themes/$_theme")) $_theme = "default";
require_once "themes/$_theme/page.class.php";
require_once "themes/$_theme/layout.class.php";
require_once "themes/$_theme/themelet.class.php";

$themelets = glob("ext/*/theme.php");
foreach($themelets as $filename) {
	require_once $filename;
}

$custom_themelets = glob("themes/$_theme/*.theme.php");
if($custom_themelets) {
	$m = array();
	foreach($custom_themelets as $filename) {
		if(preg_match("/themes\/$_theme\/(.*)\.theme\.php/",$filename,$m)
		   && array_contains($themelets, "ext/{$m[1]}/theme.php"))
		{
			require_once $filename;
		}
	}
}


// start the page generation waterfall
$page = new Page();
$user = _get_user();
$context = new RequestContext();
$context->page = $page;
$context->user = $user;
$context->database = $database;
$context->config = $config;
send_event(new InitExtEvent($context));

$argv = $_SERVER['argv'];
if(count($argv) == 2) {
	add_dir($argv[1]);
}
else {
	print "Usage: {$argv[0]} <directory to add>\n";
}
//$page->display();


// for databases which support transactions
$database->db->CommitTrans(true);
?>

