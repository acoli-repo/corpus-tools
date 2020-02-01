<?php
	// Auto-fill one field based on another: normalize, error-code, etc.
	// (c) Maarten Janssen, 2020
	
	check_login();
	include("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();

	include ("$ttroot/common/Sources/cwcqp.php");
	$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
  
	$cqp = new CQP();
	$cqp->exec($cqpcorpus); // Select the corpus
	$cqp->exec("set PrettyPrint off");
	
	$maintext .= "<h2>Auto-Fill Module</h2>";
	
	$maintext .= "<h1>".$ttxml->title()."</h1>";
	$maintext .= $ttxml->tableheader();

	$ttcqp = findapp("tt-cqp");

	if ( $act == "save" ) {
	
		foreach ( $_POST['fills'] as $tokid => $tmp ) {
			foreach ( $tmp as $att => $val ) {
				if ( $val ) {
					print "<p> - Setting $att for $tokid to $val";
					$tmp = $ttxml->xml->xpath("//*[@id='$tokid']"); $token = $tmp[0];
					if ( !$token ) print "<p>No such token: $tokid";
					else  {
						$token[$att] = $val;
						print htmlentities($token->asXML());
					};
				};
			};
		};
		$ttxml->save();
		print "<p>Changes save - reloading<script>top.location='index.php?action=file&cid=$ttxml->xmlid';</script>";
		exit;
	
	} else if ( $_POST['from'] ) {
	
		$vals = array();
		# Read the tokens to check, with their current values
		foreach ( $ttxml->xml->xpath("//tok") as $tok ) {
			$tokid = $tok['id']."";
			$from = ""; $sep = ""; $skip = 1;
			foreach ( array_keys($_POST['from']) as $fkey ) { 
				$tmp = forminherit($tok, $fkey) or $tmp = $tok[$fkey];
				if ( $tmp != "" && $tmp != "--" ) $skip = 0;
				$tmp = preg_quote($tmp);
				$from .= $sep."$fkey=\"$tmp\""; $sep = " & "; 
			};
			if ( $skip ) continue;
			$skip = 0;
			foreach ( array_keys($_POST['fill']) as $fkey ) { 
				$tmp = forminherit($tok, $fkey) or $tmp = $tok[$fkey];
				if ( $tok[$fkey] != "" && !$_POST['force'] ) { $skip = 1; };
				$vals[$from][$tokid][$fkey] = $tmp;
				$toklist[$tokid] = $tok;
			};
			if ( $skip ) continue;
			$froms[$from]++;
		};
			
		# See if we need to restrict the query
		if ( $_POST['frest'] ) { $frest = " :: {$_POST['frest']}"; };	
			
		# Build the query
		$sep = ""; $sep2 = "";
		foreach ( array_keys($_POST['from']) as $fkey ) { 
			$fromtxt .= $sep2.pattname($fkey); $sep2 = " + ";
		};
		$sep = ""; $sep2 = "";
		foreach ( array_keys($_POST['fill']) as $fkey ) { 
			$mq .= $sep."match.$fkey"; $sep = " ";
			$totxt .= $sep2.pattname($fkey); $sep2 = " + ";
		};
			
		$maintext .= "<h2>Filling: $fromtxt 
			<br>From: $totxt</h2> 
			<form action='index.php?action=$action&act=save&cid=$ttxml->xmlid' method=post>
			<table id=toktable>
			<tr><th>TOKID<th>Context<th>Current<th>Autofill options";
		foreach ( $froms as $key => $val ) {
			$cmd = "/bin/echo 'Matches = [$key] $fr; group Matches $mq;' | $ttcqp";
			$result = shell_exec($cmd); $resarr = array(); $mainres = ""; $max = 0;
			foreach( explode("\n", $result) as $resline ) {
				if ( preg_match("/^(.*)\s+(\d+)$/", $resline, $matches) ) { 
					$frm = $matches[1]; $cnt = $matches[2];
					if ( $frm != "" && $frm != "--" ) {
						$resarr[$frm] = $cnt;
						if ( $cnt > $max ) { $mainres = $frm; $max = $cnt; };
					};
				};
			};
			arsort($resarr);
			
			foreach ( $vals[$key] as $tokid => $val2 ) { 
			
				foreach ( $val2 as $fkey => $curval ) {

					if ( $toklist[$tokid][$fkey] != "" && !$_POST['force'] ) continue;
				
					$fillopts = "";
					
					$tmp = "checked"; 
					foreach ( $resarr as $frm => $cnt ) { 	
						$frmtxt = $frm; $tmp2 = "";
						if ( $curval == $mainres && $curval == $frm ) { $tmp2 = " style='color: #aaaaaa;'"; $frm = ""; };
						$fillopts .= "<input type=radio name='fills[$tokid][$fkey]' $tmp value='$frm'> <span $tmp2>$frmtxt ($cnt)</span> "; 
						$tmp = ""; 
					};

					if ( $curval != $mainres && $fillopts != "" ) $tmp3 = "real"; else $tmp3 = "virtual";
					if ( $curval != $mainres ) $fillopts .= "<input type=radio name='' value=''> (none) "; 
					
					
					if ( $curval != $mainres || count($resarr) > 1 ) { 
						$context = $ttxml->context($tokid, 4, true);
						$maintext .= "<tr type='$tmp3'><td><a href='index.php?action=tokedit&tid=$tokid&cid=$ttxml->xmlid'>$tokid</a><td id=mtxt>$context<td><td>$curval<td>$fillopts";
					}; 
				
				};
				
			};
						
		};
		$maintext .= "</table>
			<p><input type=submit value='AutoFill'> <a href='index.php?action=$action&cid=$ttxml->xmlid'>cancel</a>
			</form>
			<hr>
			<script language=Javascript>
			var showopt = true;
			function toggleopt() {
				if ( showopt ) showopt = false; else showopt = true;
				var rows = document.getElementById('toktable').getElementsByTagName('tr');
				for ( var i=0; i<rows.length; i++) {
					var row = rows[i];
					if ( row.getAttribute('type') == 'real' || showopt )  row.style.display = '';
					else row.style.display = 'none';
				};
			};
			toggleopt();
			</script>
			<p><span onclick='toggleopt();' style='spanlink'>show/hide optional changaes</span>";
	
	
	} else {
		# Select what to fill, and from what
		
		foreach ( $settings['cqp']['pattributes'] as $key => $val ) {
			$displayname = pattname($key);
			$condlist .= "<p><input type=checkbox name='from[{$key}]' value='1'> $displayname";
			$filllist .= "<p><input type=checkbox name='fill[{$key}]' value='1'> $displayname";
		};
		
		$maintext .= "<p>In this module, you can automatically fill fields based on previous files; for instance, you can
			fill in the normalized form based on the written form. For this, the module solely relies on normalized forms in 
			the CQP corpus; So if we already have a <i>waht</i> in our corpus that got normalized to <i>what</i>, you can 
			repeat that normalization in a new text. You can fill in multiple forms at the same time, say both the expanded 
			form and the normalized form; and you can use multiple conditions, say fill in the error code based on the 
			written form and the normalized form. You can only fill in from and to pattributes in the CQP corpus.
			
			<hr>
			
			<form action='index.php?action=$action&cid=$ttxml->xmlid' method=post>
			<table>
			<tr><th>Conditions<th>Fill-in
			<tr><td>$condlist<td>$filllist
			</table>
			<p>Lookup conditions: <input name=frest size=40> p.e.: <i style='color: #bbbbbb'>match.text_normalized=\"1\"</i>
			<p><input name=force type=checkbox value=\"1\"> Also auto-fill words that already have a filled value
			<p><input type=submit value='Start'>
			</form>
			";
	};

?>