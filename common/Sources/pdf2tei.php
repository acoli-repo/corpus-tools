<?php

	$pdf = $_GET['pdf'];

	if ( $act == "create" ) {
	
		$cmd = "perl ../common/Scripts/pdf2tei.pl --pagestyle='{$_POST['pagestyle']}' --start='{$_POST['pagestart']}' --end='{$_POST['pageend']}' --pdf='{$_POST['pdf']}' --teiname='{$_POST['teiname']}'";
	
		print $cmd; exit;
	
	} else if ( $act == "upload" ) {
		
		if ( is_uploaded_file($_POST["pdffile"]) ) {
			$filename = basename($_FILES["fileToUpload"]["name"]);
			if ( move_uploaded_file($_FILES["pdffile"]["tmp_name"], "pdf/$filename") ) {
				print "Upload successfull
					<script language=Javascript>top.location='index.php?action=$action&pdf=$filename';</script>";
			} else {
				fatal ("file upload of $filename failed");
			};
		} else if ( $_POST['url'] ) {
			fatal ("no uploaded file or selected URL");
		};
		
	} else if ( $pdf ) {

		$maintext .= "
		<h1>Create TEI from PDF</h1>
			<script src='$jsurl/pdf.js'></script>
			<script language=Javascript>var pdffile = '$pdf';</script>
			<script src='$jsurl/pagerender.js'></script>

		<form id='frm' name='frm' action='index.php?action=$action&act=create' method=post>
		<input type=hidden name=pdf value=\"$pdf\">
		<div style=\"position: absolute; right: 10px; top: 110px; width: 600px;\">
			<table width='100%'><tr>
			<td style='text-align: left;'>
					<a onclick=\"topage(1)\">&lt;&lt;</a>
					<a onclick=\"topage('prev')\">&lt;</a>
			</td>
			<td style='text-align: center;'><div id=\"the-caption\" style='inline-block'>(pdf info)</div></td>
			<td style='text-align: right;'>
					<a onclick=\"topage('next')\">&gt;</a>
					<a onclick=\"topage('last')\">&gt;&gt;</a>
			</td>
			</table>
		</div>
		<canvas id=\"the-canvas\" style=\"position: absolute; right: 10px; top: 135px;\"></canvas>
		<div>
		<p>TEI ID: <input name='teiname' onchange=\"render();\"></p>
		<p>PDF Page style: 
			<br><input type=radio name=\"pagestyle\" value=\"1\" checked onclick=\"setstyle(1)\"> folio
			<br><input type=radio name=\"pagestyle\" value=\"2\" onclick=\"setstyle(2)\"> two-up
		</p>
		<p>Start page: <input name='pagestart' size=4 value='2' onchange=\"render();\"> <a onclick='setstart();'>start at current page</a></p>
		<p>End page: &nbsp; <input name='pageend' size=4 value='' onchange=\"render();\"> <a onclick='setend();'>end at current page</a></p>
		</div>
		<p><input type=submit value=\"Generate TEI\">
		</form>
			<script language=Javascript>
 				PDFJS.workerSrc = '$jsurl/pdf.worker.js';
 				initialize();
			</script>
		";
		
	} else {
	
		$maintext .= "<h1>Create TEI from PDF</h1>
			<p>Select a PDF file from the list below, or upload a new PDF either from file or from a
				web location
				<p><form action='index.php?action=$action&act=upload' method='post' enctype='multipart/form-data'>
				<p>From file: <input type=file name=pdffile id=pdffile>
				<p>From URL: <input name=url size=80>
				<p><input type=submit value='Upload PDF'>
				</form>
				<hr>
				<p>Existing PDF files:"; 
		
		foreach ( scandir("pdf/") as $file ) {
			if ( preg_match("/\.pdf/", $file ) ) {
				$maintext .= "<p><a href='index.php?action=$action&pdf=$file'>$file</a>";
			};
		};		
		
	};
	
?>