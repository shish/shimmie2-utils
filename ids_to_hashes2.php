<?php
print("Buidling dirs:\n");

if(!file_exists("images")) mkdir("images");
if(!file_exists("thumbs")) mkdir("thumbs");

mkdir("images/old");
mkdir("thumbs/old");

if(!file_exists("images/ff")) {
	for($i=0; $i<256; $i++) {
		printf("%02x", $i);
		mkdir(sprintf("images/%02x", $i));
		mkdir(sprintf("thumbs/%02x", $i));
		print "\n";
	}
	print("\n");
}

dothings("jpg");
dothings("png");
dothings("gif");

function dothings($ext) {
	foreach(glob("images/*.$ext") as $fname) {
		$id = preg_replace("#images/(\d+)\.$ext$#", "$1", $fname);
		$hash = md5_file($fname);
		$ab = substr($hash, 0, 2);
		print("$id -> $ab/$hash\n");
		copy("thumbs/$id.jpg", "thumbs/$ab/$hash");
		copy("images/$id.$ext", "images/$ab/$hash");
		rename("thumbs/$id.jpg", "thumbs/old/$id.jpg");
		rename("images/$id.$ext", "images/old/$id.$ext");
	}
}
?>
