<?php
private function check_for_orphanned_images() {
	$orphans = array();
	foreach(glob("images/*") as $dir) {
		foreach(glob("$dir/*") as $file) {
			$hash = str_replace("$dir/", "", $file);
			if(!$this->db_has_hash($hash)) {
				$orphans[] = $hash;
			}
		}
	}
	return true;
}
?>
