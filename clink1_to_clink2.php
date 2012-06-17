<?php
function get_db() {
	include "config.php";

	$matches = array(); $db_user=null; $db_pass=null;
	if(preg_match("/user=([^;]*)/", DATABASE_DSN, $matches)) $db_user=$matches[1];
	if(preg_match("/password=([^;]*)/", DATABASE_DSN, $matches)) $db_pass=$matches[1];

	$db_params = array(
		PDO::ATTR_PERSISTENT => true,
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);
	$db = new PDO(DATABASE_DSN, $db_user, $db_pass, $db_params);
	return $db;
}

function parse_rows($db) {
	$stmt = $db->prepare("SELECT * FROM comments WHERE comment ~ '.*>>[0-9]+#c?[0-9]+.*' AND posted > now() - interval '3 months'");
	$stmt->execute();

	while($row = $stmt->fetch()) {
		$matches = array();
		if(preg_match_all("/>>(\d+)(#c?)(\d+)/", $row['comment'], $matches)) {
			for($i=0; $i<count($matches[0]); $i++) {
				$image_id = $matches[1][$i];
				$sep = $matches[2][$i];
				$comment_id = $matches[3][$i];
				$user = get_user_from_comment($db, $comment_id);
				$base = $row['comment'];
				if(!is_null($user)) {
					$new = str_replace(">>$image_id$sep$comment_id", "[url=site://post/view/{$image_id}#c{$comment_id}]@{$user}[/url]", $base);
					print "\n\n----\n{$row['id']}\n--\n$base\n--\n$new";
					update_comment($db, $row['id'], $new);
				}
			}
		}
	}
}

function get_user_from_comment($db, $comment_id) {
	$stmt = $db->prepare("SELECT name FROM users WHERE id=(SELECT owner_id FROM comments WHERE id=?)");
	$stmt->execute(array($comment_id));
	$row = $stmt->fetch();
	if($row) {
		return $row[0];
	}
	return null;
}

function update_comment($db, $comment_id, $text) {
	$stmt = $db->prepare("UPDATE comments SET comment=? WHERE id=?");
	$stmt->execute(array($text, $comment_id));
}

parse_rows(get_db());
?>
