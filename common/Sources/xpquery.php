<?php

	// XML File Search by XPath

	$txtq = $_POST['xpf']; 
	$xpftxt = str_replace("'", "&#039", $txtq);
	$qt = str_replace("'", '"', $txtq);
	
	$xprtxt = str_replace("'", "&#039", $_POST['xpr']);
	$qr = str_replace("'", '"', $_POST['xpr']);
	
	if ( $username ) {
		$restq = "<p>{%Xpath restriction}: &nbsp; <input name=xpr size=80 value='{$xprtxt}'  $chareqfn> 
			<input type=checkbox value=1 name=xx class=adminpart> include non-indexed files
		";
		$hq = "<input type=checkbox value=1 name=hh class=adminpart> (also) look in the teiHeader<p>";
	};
	
	$maintext .= "<h1>XPath Search</h1>
			<form action='' method=post id=cqp name=cqp>
				$restq
				<p>{%Xpath query}: &nbsp; <input name=xpf size=80 value='{$xpftxt}'  $chareqfn> 
				$hq
				<input type=submit value=\"Search\"> <a href='index.php?action=$action&act=advanced'>{%advanced}</a></form>
			";

	$app = findapp("tt-xpath");
	if ( !$app ) fatal ("This function relies on tt-xpath, which is not installed on the server");

	if ( $qr ) { $qrest = " --xprest='$qr' "; };

	if ( $_POST['xx'] ) { $opts .= " --folder='xmlfiles' "; };
	if ( $_POST['hh'] ) { $opts .= " --header "; };

	if ( $txtq ) {

		$jsonforms = array2json($settings['xmlfile']['pattributes']['forms']);
		#Build the view options	
		foreach ( $settings['xmlfile']['pattributes']['forms'] as $key => $item ) {
			$attlisttxt .= $alsep."\"$key\""; $alsep = ",";
			$attnamelist .= "\nattributenames['$key'] = \"{%".$item['display']."}\"; ";
		};
		foreach ( $settings['xmlfile']['pattributes']['tags'] as $key => $item ) {
			$attlisttxt .= $alsep."\"$key\""; $alsep = ",";		
			$attnamelist .= "\nattributenames['$key'] = \"{%".$item['display']."}\"; ";
		};


		$cmd = "/usr/local/bin/tt-xpath $opts --xpquery='$qt' $qrest"; 
		// print $cmd; exit; 
		$tmp = shell_exec($cmd);
	
		$results = simplexml_load_string($tmp);
		if ( !$results ) fatal("Failed to load XML results");
	
		foreach ( $results->children() as $resnode ) {
			$resx = $resnode->asXML();
			if ( ( $settings['xmlfile']['paged'] || ( $settings['xmlfile']['restriction'] && !$this->xml->xpath($settings['xmlfile']['restriction']) ) ) && !$username ) {
				// Prevent this function from being used to get a larger context in restricted XML files
				$resx = substr($resx, 0, 1000);
			};
			$editxml .= "<tr><td><a target=view style='font-size: 10pt; padding-right: 5px;' href='index.php?action=file&id={$resnode['fileid']}&jmp={$resnode['id']}'>context</a><td>$resx</td>";
		};

		if ( $editxml == "" ) {
			$editxml = "<i>{%No results found}</i>";
			if ( $username ) $editxml .= "<p>Query: $cmd";
		};

		$maintext .= "
			<script language=Javascript src='$jsurl/tokedit.js'></script>
			<script language=Javascript src='$jsurl/tokview.js'></script>
			<div id='tokinfo' style='display: block; position: absolute; right: 5px; top: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa;'></div>
			<div>$nav</div>
			<table id='mtxt'>$editxml</table>
			<script language='Javascript'>
				var formdef = $jsonforms;
				var attributelist = Array($attlisttxt);
				$attnamelist
				var orgtoks = new Object();
				$moreactions
				formify();
			</script>";
	
	};

?>