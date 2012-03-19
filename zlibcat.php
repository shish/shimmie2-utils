<?php
$all_files = unserialize(gzinflate(file_get_contents($argv[1])));
var_dump(
$all_files["2.Xm/core/default_config.inc.php"]
);
