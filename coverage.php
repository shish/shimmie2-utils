<html>
<!--
Note:
this assumes that your install tree is like so

.../coverage.php
.../2.3m/<shimmie install for 2.3 using MySQL>
.../2.3m/data/coverage/<xdebug coverage logs>

this is the type of tree you should end up with if you use the
init_dev.sh script
-->
	<head>
		<title>XDebug Coverage Viewer</title>
		<style>
.none {
	color: #999;
	background: #CCC;
}
.miss {
	background: #FAA;
}
.near {
	background: #FA6;
}
.hit {
	background: #AFA;
}
		</style>
	</head>
<?php
set_time_limit(0);

function rel($path) {
	return str_replace(getcwd()."/", "", $path);
}

$base = isset($_GET["base"]) ? $_GET["base"] : "2.Xm";
$dir = "$base/data/coverage";
$verbose = false;

$files = array();

foreach(array_merge(glob("$dir/*.blog"), glob("$dir/*.log")) as $clog_file) {
	$clog_file = rel($clog_file);
	if($verbose) print "<p>Loading data from $clog_file...";
	$data = unserialize(gzinflate(file_get_contents($clog_file)));
	foreach($data as $filename => $coverage) {
		$filename = rel($filename);

		if(strpos($filename, "/config.php") !== FALSE) {
			continue;
		}
		if(strpos($filename, "/lib/") !== FALSE) {
			continue;
		}
		if(strpos($filename, "/simpletest/simpletest/") !== FALSE) {
			continue;
		}
		if($verbose) print "<br>- Reading coverage for $filename...";

		if(!isset($files[$filename])) {
			if($verbose) print "<br>-- $filename is new, adding";
			$files[$filename] = $coverage;
		}
		else {
			if($verbose) print "<br>-- $filename exists, merging";
			foreach($coverage as $line => $count) {
				# this line hasn't been recorded before
				if(!isset($files[$filename][$line])) {
					$files[$filename][$line] = $count;
				}

				# it has been recorded
				else if($count > 0) {
					# it was recorded as a miss (-1), reset and increment
					# (just increment would be "-1 + 1 = 0 = wrong")
					if($files[$filename][$line] < 0) {
						$files[$filename][$line] = $count;
					}

					# it was recorded as a hit, increment
					else {
						$files[$filename][$line] += $count;
					}
				}
			}
		}
	}
	unlink($clog_file);
	file_put_contents("$dir/archive.blog", gzdeflate(serialize($files)));
}

ksort($files);


$global_lines = 0;
$global_hits = 0;

function blank($line) {
	$t = trim($line);
	return ($t == "}" || $t == "EOD;");
}

function coverage_summary($file, $array) {
	global $global_lines, $global_hits;

	$lines = explode("\n", file_get_contents($file));
	foreach($array as $linenum => $hits) {
		if($hits == -1 && blank($lines[$linenum-1])) $array[$linenum] = 0;
	}

	$hit = 0;
	$miss = 0;
	$blank = 0;
	foreach($array as $value) {
		if($value == -2) $blank++;
		if($value == -1) $miss++;
		if($value ==  0) $blank++;
		if($value >=  1) $hit++;
	}

	$global_lines += $hit + $miss;
	$global_hits  += $hit;

	return $hit/($hit+$miss);
}

print "<h2>Summary</h2>";
print "<table>";
print "<tr><td>Filename</td><td>Coverage</td></tr>";
foreach($files as $file => $coverage) {
	$csum = coverage_summary($file, $coverage)*100;

	printf("<tr style='background: %s'><td><a href='#%s'>%s</a></td><td>%4.2f</td></tr>",
		"hsl(" . (max($csum-40, 0)*1.6) . ", 50%, 75%)",
		urlencode(rel($file)),
		rel($file),
		$csum);
}
if($global_lines > 0) {
	printf("<tr><td>Total</td><td><b>%4.2f</b></td></tr>", ($global_hits/$global_lines)*100);
}
print "</table>";

foreach($files as $file => $coverage) {
	print "<h2><a name='".urlencode(rel($file))."'>".rel($file)."</a></h2>";

	print "<pre>";
	$lines = explode("\n", file_get_contents($file));
	for($i=0; $i<count($lines); $i++) {
		$exes = isset($coverage[$i+1]) ? $coverage[$i+1] : -2;

		if($exes == -1 && blank($lines[$i])) $exes = 0;

		$col = "none";
		if($exes == -1) $col = "miss";
		if($exes >=  1) $col = "hit";
		printf("<span class='%s'>%4d %s</span>\n", $col, $exes, htmlentities($lines[$i]));
	}
	print "</pre>";
}
?>
