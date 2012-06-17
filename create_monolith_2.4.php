<?php

define("THEME", "default");
define("RECURSE_INCLUDE", false);

require_once "core/sys_config.inc.php";
require_once "core/util.inc.php";

$text = '<'."?php\n";
$text .= manual_include("core/sys_config.inc.php");
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
';

$text .= '
try {
	// load base files
	ctx_log_start("Opening files");
';

	$files = array_merge(zglob("core/*.php"), zglob("ext/{".ENABLED_EXTS."}/main.php"));
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

	// start the page generation waterfall
	ctx_log_start("Page generation");
	$page = class_exists("CustomPage") ? new CustomPage() : new Page();
	$user = _get_user();
	send_event(new InitExtEvent());
	if(!is_cli()) { // web request
		send_event(new PageRequestEvent(@$_GET["q"]));
		$page->display();
	}
	else { // command line request
		send_event(new CommandEvent($argv));
	}
	ctx_log_endok("Page generation");

	// saving cache data and profiling data to disk can happen later
	if(function_exists("fastcgi_finish_request")) fastcgi_finish_request();
	$database->db->commit();
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
