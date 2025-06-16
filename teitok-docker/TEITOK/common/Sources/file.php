<?php

	// Viewport hub - determine which view to use to display a file
	// (c) Maarten Janssen, 2018

	$viewlist = array ( 
			"text" => "Text view",
			"facsview" => "Facsimile view",
			"lineview" => "Facsimile lines",
			"igt" => "Interlinear Glossed Text",
			"wavesurfer" => "Waveform view",
			"block" => "Block view (sentence)",
		);

	if ( $_GET['view'] ) $viewaction = $_GET['view'];
	else $viewaction = getset('defaults/fileview', "text");

	$action = $viewaction;
	if ( file_exists("Sources/$viewaction.php") ) $viewphp = "Sources/$viewaction.php";
	else if ( file_exists("$sharedfolder/Sources/$viewaction.php") ) $viewphp = "$sharedfolder/Sources/$viewaction.php";
	else if ( file_exists("$ttroot/common/Sources/$viewaction.php") ) $viewphp = "$ttroot/common/Sources/$viewaction.php";
	else fatal("No such action: $viewaction");
	
	include($viewphp);

?>