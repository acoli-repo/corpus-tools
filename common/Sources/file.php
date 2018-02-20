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
	else if ( $settings['defaults']['fileview'] ) $viewaction = $settings['defaults']['fileview'];
	else $viewaction = "text";

	include("$viewaction.php");

?>