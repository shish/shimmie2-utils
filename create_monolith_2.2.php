<?php

define("THEME", "default");
define("PHP4_COMPAT", false);
define("AGGRESSIVE", true);

if(!file_exists("config.php")) {die("config.php not found");}

function striphp($text) {
	$text = preg_replace('/^<\?php/', '', $text);
	$text = preg_replace('/\?>$/', '', $text);
	$text = preg_replace('#function send_event(.|\s)*?\n}#m', '
function send_event(Event $event) {
	global $_event_listeners, $_event_count, $_event_routes, $_event_listener_map;
	if(empty($_event_listener_map)) {
		$_event_listener_map = array();
		foreach($_event_listeners as $extension) {
			$_event_listener_map[get_class($extension)] = $extension;
		}
	}
	$my_event_listeners = $_event_routes[get_class($event)];
	ksort($my_event_listeners);
	foreach($my_event_listeners as $listener) {
		$_event_listener_map[$listener]->receive_event($event);
	}
	$_event_count++;
}
', $text);

	if(AGGRESSIVE) {
		$text = preg_replace('#\n\s*/\*(.|\s)*?\*/#m', '', $text); /* ... */
		// "//-->" is important
		//$text = preg_replace('#\n\s+//.*#', '', $text); // ...
		$text = preg_replace('/\n\s+#.*/', '', $text);  # ...
		$text = preg_replace('/\n\s*\n/', "\n", $text);
	}

	// most requires are built-in, but we want /lib separately
	$text = str_replace('require_', '// require_', $text);
	$text = str_replace('// require_once "lib', 'require_once "lib', $text);

	// php4 fails
	if(PHP4_COMPAT) {
		$text = str_replace('public function', 'function', $text);
		$text = str_replace('protected function', 'function', $text);
		$text = str_replace('private function', 'function', $text);
		$text = str_replace('$event->get_panel()->add_side_block($sb);',
		                    '$panel=$event->get_panel(); $panel->add_side_block($sb);', $text);
	}
	return $text;
}

function get_events($text) {
	$matches = array();
	preg_match_all("/\s([a-zA-Z]+Event)/", $text, $matches);
	$events = $matches[1];

	preg_match_all("/\son([a-zA-Z]+)\(/", $text, $matches);
	$handlers = array_unique($matches[1]);
	foreach($handlers as $handler) {
		$events[] = $handler."Event";
	}

	if(preg_match("/\sformat\(/", $text)) {
		$events[] = "TextFormattingEvent";
	}

	if(preg_match('#\nclass (.*) extends DataHandlerExtension#', $text, $matches)) {
		$events[] = "DataUploadEvent";
		$events[] = "ThumbnailGenerationEvent";
		$events[] = "DisplayingImageEvent";
	}

	return array_unique($events);
}

function get_prio($text) {
	$matches = array();
	if(preg_match('#\nclass (.*) extends SimpleExtension#m', $text, $matches)) {
		return 50;
	}
	if(preg_match('#add_event_listener\(new .*, (\d+)\);#', $text, $matches)) {
		return $matches[1];
	}
	return 50;
}

function get_ext($text) {
	$matches = array();
	preg_match('#\nclass (.*) (extends|implements) .*Extension#m', $text, $matches);
	if($matches) return $matches[1];
	else return null;
}

function invert_map($map) {
	$map2 = array();
	foreach($map as $ext_name => $info) {
		foreach($info["events"] as $event) {
			$pos = $info["prio"];
			while(isset($map2[$event][$pos])) {
				$pos++;
			}

			$map2[$event][$pos] = $ext_name;
		}
	}
	return $map2;
}


$text = "<?php
error_reporting(E_ALL);
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_BAIL, 1);
";

$files = array_merge(
	glob("core/*.php"),
	glob("ext/*/main.php"),
	glob("themes/".THEME."/*.class.php"),
	glob("ext/*/theme.php"),
	glob("themes/".THEME."/*.theme.php")
	);

$routes = array();

foreach($files as $filename) {
	print "Converting $filename...\n";
	$text .= "\n\n// Original: $filename\n";
	$data = striphp(file_get_contents($filename));
	$ext = get_ext($data);
	if($ext) {
		$prio = get_prio($data);
		$events = get_events($data);
		$routes[$ext] = array("prio"=>$prio, "events"=>$events);
	}
	$text .= $data;
}

//var_export(invert_map($routes));

$text .= '
define("DEBUG", true);
define("COVERAGE", true);
define("CACHE_MEMCACHE", false);
define("CACHE_DIR", false);
define("VERSION", "trunk");
define("SCORE_VERSION", "s2hack/".VERSION);
define("COOKIE_PREFIX", "shm");

'.striphp(file_get_contents("config.php")).'

$_event_routes = '.var_export(invert_map($routes), true).';

if(COVERAGE) {_start_coverage();}
_version_check();
_sanitise_environment();
_start_cache();

try {
	// connect to the database
	$database = new Database();
	$database->db->fnExecute = "_count_execs";
	$config = new DatabaseConfig($database);


	// initialise the extensions
	foreach(get_declared_classes() as $class) {
		if(is_subclass_of($class, "SimpleExtension")) {
			$c = new $class();
			$c->i_am($c);
			add_event_listener($c);
		}
	}


	// start the page generation waterfall
	$page = class_exists("CustomPage") ? new CustomPage() : new Page();
	$user = _get_user($config, $database);
	send_event(new InitExtEvent());
	send_event(_get_page_request());
	$page->display();


	// for databases which support transactions
	if($database->engine->name != "sqlite") {
		$database->db->CommitTrans(true);
	}

	_end_cache();
}
catch(Exception $e) {
	$version = VERSION;
	$message = $e->getMessage();
	header("HTTP/1.0 500 Internal Error");
	print <<<EOD
<html>
	<head>
		<title>Internal error - SCore-$version</title>
	</head>
	<body>
		<h1>Internal Error</h1>
		<p>$message
	</body>
</html>
EOD;
}
if(COVERAGE) {_end_coverage();}
?>';

print "Writing monolith.php\n";
file_put_contents("monolith.php", $text);
?>
