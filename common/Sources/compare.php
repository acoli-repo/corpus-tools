<?php

	$cid1 = $_GET['cid1'];
	$cid2 = $_GET['cid2'];

	require ("../common/Sources/ttxml.php");
	$ttxml1 = new TTXML($cid1, false);
	$ttxml2 = new TTXML($cid2, false);
	
	$maintext .= "<table>
		   <colgroup width='50%'> <colgroup width='50%'>";
	
	$maintext .= "<tr><td valign=top>";
	$maintext .= "<h2>".$ttxml1->title()."</h2>"; 
	$maintext .= "<td valign=top>";
	$maintext .= "<h2>".$ttxml2->title()."</h2>"; 

	$maintext .= "<tr><td valign=top>";
	$maintext .= $ttxml1->tableheader(); 
	$maintext .= "<td valign=top>";
	$maintext .= $ttxml2->tableheader(); 

	$maintext .= "<tr><td valign=top>";
	$maintext .= $ttxml1->viewheader(); 
	$maintext .= "<td valign=top>";
	$maintext .= $ttxml2->viewheader(); 

	$maintext .= "<tr><td valign=top>";
	$maintext .= $ttxml1->mtxt(); 
	$maintext .= "<td valign=top>";
	$maintext .= $ttxml2->mtxt(); 

	$maintext .= "</table>";

?>