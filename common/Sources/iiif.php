<?php

	# {scheme}://{server}{/prefix}/{identifier}/{region}/{size}/{rotation}/{quality}.{format}
	$page = 0;
	if ( preg_match("/iiif\/(.*)\/(.*)\/(.*)\/(.*)\/(.*)\.(.*)/", $_SERVER['REQUEST_URI'], $matches ) ) {
		$id = $matches[1];
		$region = $matches[2];
		$size = $matches[3];
		$rotation = $matches[4];
		$quality = $matches[5];
		$format = $matches[6];
		$file = $id;
	} else if ( preg_match("/iiif\/(.*)/", $_SERVER['REQUEST_URI'], $matches ) ) {
		list (	$id, $region, $size, $rotation, $target ) = explode("/", $matches[1]);
		$file = $id;
	} else {
		$format = $_GET['format'] or $format = "jpg"; 
		$size = $_GET['size'];
		$file = $_GET['file'];
		$quality = $_GET['quality'];
		$rotation = $_GET['rotation'];
		$region = $_GET['region'];
		$page = $_GET['page'];
	};
	
	if ( preg_match("/^(.*):(\d+)$/", $file, $matches) ) {
		$file = $matches[1];
		$page = $matches[2];
	};

	$now = time();
	$jpgname = "tmp/$now.jpg";

	$file = "Facsimile/$file";
	list ( $intype, $informat ) = explode("/", mime_content_type($file));
	
	if ( !file_exists($file) ) fatal("No such image: $file");
		
	if ( $quality != "default" ) {
		if ( $quality == "grey") $conv['quality'] = "-colorspace Gray";
	};
	if ( $size && $size != "max" ) {
		list ( $sw, $sh ) = explode(",", $size );
		$conv['size'] = "-resize {$sw}x{$sh}";
	};
	if ( $rotation ) {
		$conv['rotation'] = "-rotate $rotation";
	};
	if ( $region && $region != "full" ) {
		list ( $cx1, $cy1, $cx2, $cy2 ) = explode(",", $region );
		$tw = $cx2-$cx1; $th = $cy2-$cy1;
		$conv['rotation'] = "-crop {$tw}x{$th}+{$cx1}+$cy1";
	};
	$convopts = join(" ", array_values($conv));

	$infile = $file;
	if ( $informat == "pdf" || $page ) {
		$infile = "{$file}[$page]";
	};

	if ( $convopts ) {
	
		$cex = shell_exec("which convert");
		if ( $cex == "" && 1==2 ) {
			if ( $username ) fatal("ImageMagick is not installed - cannot create images from PDF");
			exit;
		};
	
		$cmd = "/usr/local/bin/convert $infile $convopts $jpgname >> tmp/convert.log 2>&1";
		file_put_contents("tmp/convert.cmd", $cmd."\n");
		exec($cmd);
		
	} else {
		$jpgname = $file;
		$format = $informat;
	};
	
	# print "FILE: ".$jpgname; exit;
	header('Content-type: image/$format');
	header('Content-Disposition: inline; filename="filename.'.$format.'"');
	readfile($jpgname); 
	# if ( $file != $jpgname )  unlink($jpgname);

	exit;

?>