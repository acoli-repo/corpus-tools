<?php
	# Script to edit part of the XML file
	# Provides a graphical preview, but is not a graphical XML editor  
	# (c) Maarten Janssen, 2015
	
	check_login();

	$fileid = $_POST['cid'] or $fileid = $_GET['cid'] or $fileid = $_GET['id'] or $fileid = $_GET['fileid'];
	$oid = $fileid;
	$tokid = $_POST['tid'] or $tokid = $_GET['tid'];
	
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
		$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);

		if ( !$tokid ) { fatal("No token specified"); };

		if ($_POST['newxml']) {
			$levels = $_GET['levels'] or $levels = 1;
			$result = $xml->xpath("//tok[@id='$tokid']"); 
			$token = $result[0];
			if ( !$token ) { fatal("Token not found: $tokid"); };
				
			$context = $token;	
			for ( $i=0; $i<$levels; $i++ ) {
				$result = $context->xpath("parent::*");
				$context = $result[0];
			}; 
			
			$context[0] = "##INSERT##";
			
			$fullxml = $_POST['fullxml'];
			$newxml = $_POST['newxml'];
			
			$source = $xml->asXML();
			$file = preg_replace("/<[^>]+>##INSERT##<\/[^>]+>/", $newxml, $source);
			
			saveMyXML($file, $fileid);
		
			$maintext .= "<hr><p>Your text has been modified - reloading";
			header("location:index.php?action=renumber&cid=$fileid&tid=$tokid");
			exit;

		} else {
		
			$levels = $_GET['levels'] or $levels = 1;
		
			$result = $xml->xpath("//tok[@id='$tokid']"); 
			$token = $result[0];
			if ( !$token ) { print "Token not found: $tokid<hr>"; print $file; exit; };
				
			$context = $token;	
			for ( $i=0; $i<$levels; $i++ ) {
				$result = $context->xpath("parent::*");
				$context = $result[0];
			}; $ml = $levels +1;
			
			$fullxml = $context->asXML();

	$result = $xml->xpath("//title"); 
	$title = $result[0];
	if ( $title == "" ) $title = "<i>{%Without Title}</i>";
			
			
		$jsonforms = array2json(getset('xmlfile/pattributes/forms', array()));
		#Build the view options	
		foreach ( getset('xmlfile/pattributes/forms', array()) as $key => $item ) {
			$attlisttxt .= $alsep."\"$key\""; $alsep = ",";
			$attnamelist .= "\nattributenames['$key'] = \"{%".$item['display']."}\"; ";
		};
		foreach ( getset('xmlfile/pattributes/tags', array()) as $key => $item ) {
			$attlisttxt .= $alsep."\"$key\""; $alsep = ",";		
			$attnamelist .= "\nattributenames['$key'] = \"{%".$item['display']."}\"; ";
		};

	# Protect XML from &
	$fullxml = preg_replace("/&(?![a-z]+;)/", "&amp;", $fullxml);

			$elmid = $context['id'];
			if ( $elmid ) $elmedit = "<a href='index.php?action=xmllayout&cid=$fileid&elmid=$elmid'>XML layout</a> &bull;";
			$maintext .= "<h1>Context Edit</h1>
			
				<table>
				<tr><th>Filename</th><td>$fileid</td></tr>
				<tr><th>Title</th><td>$title</td></tr>
				</table>
				<hr>
				
				<p>Edit the partial XML below, the way it will look is shown in the preview below. 
					While there are errors in the XML, a warning will show, more info about the error can
					be obtained by hovering the mouse over the warning. Click on the word in the preview to 
					find it in the raw XML. To add or remove XML annotations, you can also use the XML Layout editor.
				<hr>
				<style>
					#warn parsererror { display:none; }
					#warn:hover parsererror { display:block; position: absolute; top: 10px; right: 10px; font-weight: normal; width: 300px; background-color: #ffffcc; color: #000000; text-align: left; font-size: 9pt; padding: 10px; }
				</style>
				<form action=\"index.php?action=$action&act=save&cid=$fileid&tid=$tokid\" method=post id=raw name=raw>
					<textarea name='fullxml' style='width: 100%; height: 50px; display: none'>".$fullxml."</textarea>
					<textarea name='newxml' id='newxml' style='width: 100%; height: 150px;' oninput='updatemtxt();'>".$fullxml."</textarea>
					<input type=submit value=Save> 
					<a href='index.php?action=tokedit&cid=$fileid&tid=$tokid'>cancel</a> &bull;
					$elmedit
					<a href='index.php?action=contextedit&cid=$fileid&tid=$tokid&levels=$ml'>more context</a>
				</form>
				<div id='warn' style='color: #992000; font-weight: bold; height: 30px; text-align: center; padding-top: 10px;'></div>
				<hr>
				<h2>Preview</h2>
					<script language=Javascript src='$jsurl/tokedit.js'></script>
					<script language=Javascript src='$jsurl/tokview.js'></script>
					<div id='tokinfo' style='display: block; position: absolute; right: 5px; top: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa;'></div>
				<div id='mtxt'>$fullxml</div><div style='height: 100px;'></div>
					<script language=Javascript>
						function updatemtxt() { 
							var rawxml = document.raw.newxml.value;
							var oParser = new DOMParser();
							var oDOM = oParser.parseFromString(rawxml, 'text/xml');
							if ( oDOM.documentElement.nodeName == 'parsererror' ) {
								var oSerializer = new XMLSerializer();
								var sXML = oSerializer.serializeToString(oDOM);
								document.getElementById('warn').innerHTML = 'Invalid XML! Revise' + sXML; 							
							} else {
								document.getElementById('warn').innerHTML = ''; 	
							};						
							document.getElementById('mtxt').innerHTML = rawxml; 
							formify();
						};
						document.getElementById('mtxt').onclick = function(e) { 
							var s = window.getSelection();
						    var range = s.getRangeAt(0);
						    var node = s.anchorNode.parentNode;	
						    var searchWord = node.outerHTML;
						    var text = document.getElementById('newxml').value;
						    var s1 = text.indexOf(searchWord.substring(0,15));
						    if (s1==-1) { console.log('Not found: '+searchWord.substring(0,15)); };	
						    document.getElementById('newxml').focus();				
						    document.getElementById('newxml').setSelectionRange(s1,s1+searchWord.length);					
						};
						// var username = '$username';	// This makes the words clickable, which we do not want					
						var formdef = $jsonforms;
						var attributelist = Array($attlisttxt);
						$attnamelist
						var tid = '$fileid'; var previd = '{$_GET['tid']}';
						var orgtoks = new Object();
						formify();
					</script>
				";

		};
		
	};
	
?>