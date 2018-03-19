<?php
	# Script to merge two <tok>
	# Collects <dtok>
	# Merges text values
	# Checks for material in the middle  
	# (c) Maarten Janssen, 2015
	
	check_login();

	$fileid = $_POST['cid'] or $fileid = $_GET['cid'] or $fileid = $_GET['id'] or $fileid = $_GET['fileid'];
	$oid = $fileid;
	$tokid1 = $_POST['tid1'] or $tokid1 = $_GET['tid1'];
	$tokid2 = $_POST['tid2'] or $tokid2 = $_GET['tid2'];
	
	if ( !strstr( $fileid, '.xml') ) { $fileid .= ".xml"; };
	
	if ( $fileid ) { 
	
		if ( !file_exists("$xmlfolder/$fileid") ) { 
	
			$fileid = preg_replace("/^.*\//", "", $fileid);
			$test = array_merge(glob("$xmlfolder/**/$fileid")); 
			if ( !$test ) 
				$test = array_merge(glob("$xmlfolder/$fileid"), glob("$xmlfolder/*/$fileid"), glob("$xmlfolder/*/*/$fileid")); 
			$temp = array_pop($test); 
			$fileid = preg_replace("/^".preg_quote($xmlfolder, '/')."\/?/", "", $temp);
	
			if ( $fileid == "" ) {
				fatal("No such XML File: {$oid}"); 
			};
		};
		
		$file = file_get_contents("$xmlfolder/$fileid"); 
		$xml = simplexml_load_string($file);

		if ($_POST['newxml']) {

		
			$fullxml = $_POST['fullxml'];
			$newxml = $_POST['newxml'];

			$file = str_replace($fullxml, $newxml, $file);
			
			# Check whether we actually changed anything
			if ( strpos($file, $fullxml) !== false ) {
				fatal("Replacement seems to have failed - XML still contains: <pre>".htmlentities($fullxml)."</pre>");
			};
			
			saveMyXML($file, $fileid);
		
			$maintext .= "<hr><p>Your text has been modified - reloading";
			header("location:index.php?action=tokedit&id=$fileid&tid={$_POST['newid']}");
			exit;

		} else {
		
			$result = $xml->xpath("//tok[@id='$tokid1']"); 
			$token1 = $result[0]; # print_r($token); exit;
			if ( !$token1 ) { print "Token 1 not found: $tokid1<hr>"; print $file; exit; };
			if ( strtolower($token1->getName()) != "tok" ) { print "Token 1 is not a &lt;tok&gt;: $tokid1<hr>"; print $file; exit; };

			$result = $xml->xpath("//tok[@id='$tokid2']"); 
			$token2 = $result[0]; # print_r($token); exit;
			if ( !$token2 ) { print "Token 2 not found: $tokid1<hr>"; print $file; exit; };
			if ( strtolower($token2->getName()) != "tok" ) { print "Token 2 is not a &lt;tok&gt;: $tokid2<hr>"; print $file; exit; };

			if ( preg_match("/(<tok[^>]*id=\"$tokid1\"[^>]*>)(.*?)<\/tok>(.*?)(<tok[^>]*id=\"$tokid2\"[^>]*>)(.*?)<\/tok>/si", $file, $matches) )  { 
				$fullxml = $matches[0];
				$tok1 = $matches[1];
				$innerxml1 = $matches[2];
				$intertext = $matches[3];
				$tok2 = $matches[4];
				$innerxml2 = $matches[5];
			};
		
			$maintext .= "<h1>Merging Tokens</h1>
				<p>In case a token has accidentially been split in two (for instance around a &lt;pb/&gt; or &lt;lb/&gt; here the two can be merged
				together. The original and the token after merge are shown below. If there are attributes on the tokens, the resulting merged token
				will typically have to be correted manually, and will start out by having the merge of the values of the two tokens. 
				<p>It is possible to join tokens that are separate by whitespaces in the original, but bear 
				in mind that TEITOK works best if tokens with spaces inside are kept to a minimum (<a href='http://teitok.corpuswiki.org/site/index.php?action=help&id=mwe'>read more</a>).
				<hr>
				<h2>Before merge</h2>
				<p>Token 1: ".htmlentities($token1->asXML())."</p>
				<p>Token 2: ".htmlentities($token2->asXML())."</p>";
		
	# Display the best context
	// See if there is a <s> or <l> or <p> around or token
	$tmp = $token1->xpath("ancestor::s | ancestor::l | ancestor::p");
	if ( $tmp ) {	
		$sent = $tmp[0];
		$contextxml = $sent->asXML();
		$context .= "<hr>";
		$context .= "<h2>Current context</h2><div id=mtxt>".$contextxml."</div>";
	} else {
		# Just don't show if we have nothing easy to show
	};
		
			if ( preg_match("/\S/", $intertext) ) $maintext .= "
				<p>Intertext: ".htmlentities($intertext)."</p>
				";
			
			if ( preg_match("/<tok /", $intertext) ) {
				$maintext .= "<hr>Impossible to merge non-adjacent tokens"; 
			} else {

				# If both tokens have a @bbox, move them back down to bbox elements
				if ( $token1['bbox'] && $token2['bbox'] ) {
					if ( !$token1['form'] ) { $token1['form'] = preg_replace("/<[^>]+>/", "", $innerxml1); };
					if ( !$token2['form'] ) { $token2['form'] = preg_replace("/<[^>]+>/", "", $innerxml2); };
					$innerxml1 = "<gtok id=\"{$token1['id']}.1\" bbox=\"{$token1['bbox']}\">$innerxml1</gtok>";
					$innerxml2 = "<gtok id=\"{$token1['id']}.2\" bbox=\"{$token2['bbox']}\">$innerxml2</gtok>";
					unset($token1['bbox']); unset($token2['bbox']);
					unset($token1['x_wconf']); unset($token2['x_wconf']);
				};
		
				foreach ( $token2->attributes() as $key=>$val) {
					if ( $key == "id" ) continue;
					if ( $token1[$key] && $token1[$key] != "--" && $val && $val != "--" ) {
						$token1[$key] .= "+".$val;
					} else if ( $val != "--" ) {
						$token1[$key] = $val;
					}; 
				};
			
				if ( $token1['form'] && !$token2['form'] ) {
					$token1['form'] .= preg_replace("/<[^>]+>/", "", $intertext.$innerxml2);
				};
		
				if ( preg_match("/(<tok[^>]*>)(.*?)<\/tok>/si", $token1->asXML(), $matches) )  { 
					$newtok1 = $matches[1];
				};
			
				# Check if there are any unclosed tag openers in the intertext
				preg_match_all("/<([a-z0-9A-Z]+) [^\/]+>/", $intertext, $matches);
				foreach ( $matches[1] as $key => $val ) {
					if ( $val && !preg_match("/<\/$val>/", $intertext) ) {
						$closetext = "</$val>$closetext";
						$outertext = "$outertext<$val rpt=\"1\">";
					};
				};
			
				$maintext .= "<hr>";


				# If only one of the toks has dtoks, create them for the other
				if ( preg_match("/<dtok/", $innerxml1) && !preg_match("/<dtok/", $innerxml2) ) {
					if ( !$token2['form'] ) { $token2['form'] = preg_replace("/<[^>]+>/", "", $innerxml2); };
					$newdtok = preg_replace("/<tok([^>]+)>.*/", "<dtok\\1/>", $token2->asXML());
					$innerxml2 .= $newdtok;
				} else if ( preg_match("/<dtok/", $innerxml2) && !preg_match("/<dtok/", $innerxml1) ) {
					if ( !$token1['form'] ) { $token1['form'] = preg_replace("/<[^>]+>/", "", $innerxml1); };
					$newdtok = preg_replace("/<tok([^>]+)>.*/", "<dtok\\1/>", $token1->asXML());
					$innerxml1 .= $newdtok;
				};
								
				$innerxml = $innerxml1.$intertext.$innerxml2;

				# Move all dtok to the end
				preg_match_all("/<dtok[^>]*\/>/", $innerxml, $matches);
				$innerxml = preg_replace("/<dtok[^>]*\/>/", "", $innerxml);
				$closetext .= join ( "", $matches[0] );
			
				$newtok = $newtok1.$innerxml.$closetext."</tok>";
			
				$xmltest = simplexml_load_string($newtok);
				if ( !$xmltest ) { 
					$maintext .= "<p style='background-color: #ffbbbb; padding: 5px;'>Warning: invalid XML in merge, please revise manually"; 
					$_GET['manual'] = 1;				
				};
			
				$newxml = $newtok.$outertext;
				$maintext .= "<h2>After merge</h2>
					<form action=\"index.php?action=$action&act=save&cid=$fileid\" method=post>
					<p>Raw XML</p>
					<textarea name='fullxml' style='width: 100%; height: 50px; display: none'>".$fullxml."</textarea>
					<input type=hidden name=newid value=\"$tokid1\">";
				if ( $_GET['manual'] ) {
					$maintext .= "<textarea name='newxml' style='width: 100%; height: 50px;'>".$newxml."</textarea>";
				} else {
					$maintext .= "<textarea name='newxml' style='width: 100%; height: 50px; display: none'>".$newxml."</textarea>";
					$maintext .= "<div style='background-color: #ffffaa; padding: 5px;'>".htmlentitieS($newxml)."</div>";
				};
				$maintext .= "<p>Pre-visualization (token only)</p>
					<script language=Javascript src='$jsurl/tokedit.js'></script>
					<script language=Javascript src='$jsurl/tokview.js'></script>
					<div id='tokinfo' style='display: block; position: absolute; right: 5px; top: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa;'></div>
					<div id='mtxt'>$newxml</div>
					<hr>
					<input type=submit value=Save> 
					<a href='index.php?action=tokedit&cid=$fileid&tid=$tokid1'>cancel</a> ";
				if (!$_GET['manual']) $maintext .= "&bull; <a href='index.php?action=mergetoks&cid=$fileid&tid1=$tokid1&tid2=$tokid2&manual=1'>edit rawxml</a>";
				$maintext .= "$context</form>"; 
		
		
			};
		};
		
	};
	
?>