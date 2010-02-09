<?php
require_once "config.php";
require_once "lib/adodb/adodb.inc.php";

$db = NewADOConnection($database_dsn);
$db->SetFetchMode(ADODB_FETCH_ASSOC);

$result = $db->Execute("SELECT * FROM images WHERE width=0 AND height=0");
while(!$result->EOF) {
	$fields = $result->fields;
	
	$id = $fields['id'];
	$hash = $fields['hash'];
	$ext = $fields['ext'];
	$ab = substr($hash, 0, 2);
	$fname = "images/$ab/$hash";

	$info = getimagesize($fname);
	if($info) {
		$width = $info[0];
		$height = $info[1];

		print "{$id} ($fname): {$width}x{$height}\n";
		$db->Execute("UPDATE images SET width=?, height=? WHERE id=?", array($width, $height, $id));
	}
	
	$result->MoveNext();
}

$result = $db->Execute("SELECT * FROM images WHERE filesize=0");
while(!$result->EOF) {
	$fields = $result->fields;
	
	$id = $fields['id'];
	$hash = $fields['hash'];
	$ext = $fields['ext'];
	$ab = substr($hash, 0, 2);
	$fname = "images/$ab/$hash";

	$size = filesize($fname);
	print "{$id} ($fname): {$size}\n";
	$db->Execute("UPDATE images SET filesize=? WHERE id=?", array($size, $id));
	
	$result->MoveNext();
}

?>
