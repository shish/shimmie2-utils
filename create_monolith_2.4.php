<?php

define("THEME", "default");
define("RECURSE_INCLUDE", false);

require_once "core/util.inc.php";

$included = array();

function manual_include($fname) {
	global $included;
	if(in_array($fname, $included)) return;
	$included[] = $fname;
	print "$fname\n";
	$text = file_get_contents($fname);
	$text = preg_replace('/^<\?php/', '', $text);
	$text = preg_replace('/\?>$/', '', $text);
	// most requires are built-in, but we want /lib separately
	$text = str_replace('require_', '// require_', $text);
	$text = str_replace('function _d(', '// function _messed_d(', $text);
	$text = str_replace('// require_once "lib', 'require_once "lib', $text);
	if(RECURSE_INCLUDE) {
		#text = preg_replace('/require_once "(.*)";/e', "manual_include('$1')", $text);
	}
	#$text = preg_replace('/_d\(([^,]*), (.*)\);/', 'if(!defined(\1)) define(\1, \2);', $text);
	return $text;
}

$text = '<'."?php\n";
$text .= manual_include("config.php");
$text .= manual_include("core/default_config.inc.php");
$text .= manual_include("core/util.inc.php");
$text .= manual_include("lib/context.php");
$text .= '
if(CONTEXT) {
	ctx_set_log(CONTEXT);
}
ctx_log_start($_SERVER["REQUEST_URI"], true, true);
if(COVERAGE) {
	_start_coverage();
	register_shutdown_function("_end_coverage");
}
_version_check();
_sanitise_environment();
_start_cache();
';

$text .= '
try {
	// load base files
	ctx_log_start("Initialisation");
	ctx_log_start("Opening files");
';

	$files = array_merge(glob("core/*.php"), glob("ext/*/main.php"));
	foreach($files as $filename) {
		$text .= manual_include($filename);
	}

$text .= '
	ctx_log_endok();

	ctx_log_start("Connecting to DB");
	// connect to the database
	$database = new Database();
	$database->db->beginTransaction();
	$config = new DatabaseConfig($database);
	ctx_log_endok();
';

	foreach(_get_themelet_files(THEME) as $themelet) {
		$text .= manual_include($themelet);
	}


$text .= '
	_load_extensions();
	ctx_log_endok("Initialisation");

	ctx_log_start("Page generation");
	// start the page generation waterfall
	$page = class_exists("CustomPage") ? new CustomPage() : new Page();
	$user = _get_user();
	send_event(new InitExtEvent());
	send_event(_get_page_request());
	$page->display();
	ctx_log_endok("Page generation");

	$database->db->commit();
	_end_cache();
	ctx_log_endok();
}
catch(Exception $e) {
	if($database && $database->db) $database->db->rollback();
	_fatal_error($e);
	ctx_log_ender();
}
?'.'>
';
file_put_contents("index2.php", $text);
?>
