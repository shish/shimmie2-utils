<?php
require_once "config.php";
require_once "lib/adodb/adodb.inc.php";

$db = NewADOConnection($database_dsn);
$db->SetFetchMode(ADODB_FETCH_ASSOC);

$result = $db->Execute("SELECT * FROM images");
while(!$result->EOF) {
	$fields = $result->fields;
	
	$hash = $fields['hash'];
	$ext = $fields['ext'];
	$ab = substr($hash, 0, 2);
	$fname1 = "images/$ab/$hash.$ext";
	$fname2 = "images/$ab/$hash";
	$tname1 = "thumbs/$ab/$hash.jpg";
	$tname2 = "thumbs/$ab/$hash";

	print "$fname1 -> $fname2\n";
	rename($fname1, $fname2);
	rename($tname1, $tname2);
	
	$result->MoveNext();
}

?>
