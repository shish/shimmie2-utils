<?php
private function reset_image_ids() {
	global $database;

	//This might be a bit laggy on boards with lots of images (?)
	//Seems to work fine with 1.2k~ images though.
	$i = 0;
	$image = $database->get_all("SELECT * FROM images ORDER BY images.id ASC");
	/*$score_log = $database->get_all("SELECT message FROM score_log");*/
	foreach($image as $img){
		$xid = $img[0];
		$i = $i + 1;
		$table = array( //Might be missing some tables?
			"image_tags", "tag_histories", "image_reports", "comments", "user_favorites", "tag_histories",
			"numeric_score_votes", "pool_images", "slext_progress_cache", "notes");

		$sql =
			"SET FOREIGN_KEY_CHECKS=0;
			UPDATE images
			SET id=".$i.
			" WHERE id=".$xid.";"; //id for images

		foreach($table as $tbl){
			$sql .= "
				UPDATE ".$tbl."
				SET image_id=".$i."
				WHERE image_id=".$xid.";";
		}

		/*foreach($score_log as $sl){
			//This seems like a bad idea.
			//TODO: Might be better for log_info to have an $id option (which would then affix the id to the table?)
			preg_replace(".Image \\#[0-9]+.", "Image #".$i, $sl);
		}*/
		$sql .= " SET FOREIGN_KEY_CHECKS=1;";
		$database->execute($sql);
	}
	$count = (count($image)) + 1;
	$database->execute("ALTER TABLE images AUTO_INCREMENT=".$count);
	return true;
}
?>
