<html>
<head>
	<title>TEITOK Configuration Check</title>
	<meta charset='utf-8'>
	<link href='Resources/htmlstyles.css' media='all' rel='stylesheet' />
</head>
<body>
<style>
	.wrong { color: #aa2000; } .wrong::before { content:'✘ ' }
	.warn { color: #aa8800; } .warn::before { content:'✣ ' }
	.right { color: #209900; } .right::before { content:'✔ ' }
</style>
<h2>TEITOK Configuration Check</h2>

<p>Welcome to your new TEITOK project. 
Below is a checklist to make sure the basic requirements for running TEITOK are in place; 
there are additional checks later that can be run from within TEITOK. 
If no checklist shows up at all then the PHP on the server is not working, or 
this folder is not set to execute PHP files.
<hr>

<div style='color: white;'>
<?php
	// Check the configuration of the server, the project, and TEITOK
	// This folder should be deleted when finished
				
	include("checklist.php");

?>
</div>
