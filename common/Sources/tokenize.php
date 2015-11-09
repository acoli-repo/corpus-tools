<?php
	// Script to tokenize an XML file
	// Most of this script is PHP tokenization, which is depricated
	// In practice this just calls the xmltokenize.pl Perl script
	// (c) Maarten Janssen, 2015

	check_login();
	header('Content-type: text/html; charset=utf-8');
	// mb_internal_encoding("UTF-8");

	// Character-level tags can be defined within each project, but this is the default list
	if ( !$settings['defaults']['chartags'] ) $chartags = Array ( "add", "del", "supplied", "expan", "abbr", "hi", "lb", "pb", "cb", "ex" ); 
	
	$fileid = $_POST['id'] or $fileid = $_GET['id'];
	
	if ( $fileid && $_GET['php'] ) { 
	
		if ( !file_exists("$xmlfolder/$fileid") ) { print "No such XML File"; exit; };
		
		$file = file_get_contents("$xmlfolder/$fileid"); 
		if ( !$file ) { print "Failing to read $fileid<hr>"; print $file; exit; };
		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($file);
		if ( !$xml ) { 
			print "Failing to parse $fileid<hr>"; 
			foreach(libxml_get_errors() as $error) {
				echo "<p>&nbsp; &nbsp; ", $error->message;
			}
			print "<hr>".$file; exit; 
		};

		$result = $xml->xpath("//title"); 
		$title = $result[0];
				
		$maintext .= "<h2>$fileid</h2><h1>$title </h1>";

		$result = $xml->xpath("//tok"); 
		$txtxml = $result[0]; 
		if ( $txtxml ) {
			print "<p>This XML has already been tokenized - proceeding automatically to edit";
			header("location:index.php?action=edit&id=$fileid");
			exit;
		};
		$mtxtelement = $xml->xpath($mtxtelement); 
		if ( !$mtxtelement ) { "<p>Error. There is no element $mtxtelement in this XML file"; exit; };
		$rawishtext = $mtxtelement[0]->asXML(); 

		// The ampersand contains a punctuation mark that is code, and should be protected
		// $rawishtext = preg_replace("/&amp/", "&", $rawishtext );
		// In fact, it holds for all HTML code
		$rawishtext = preg_replace("/&([a-z]+);/", "|&$1;|", $rawishtext );
		
// 		$maintext .= "<p>Before tokenization: <br>$rawishtext";

		# Protect spaces inside tags
		while ( preg_match("/<([^>]+) ([^>]+)>/", $rawishtext ) )  {
			$rawishtext = preg_replace("/<([^>]+) ([^>]+)>/", "<$1%%$2>", $rawishtext );
		};
 
		# Remove notes completely to put them back later
		$notecnt = 0;
		while ( preg_match("/<note.+?<\/note>/smi", $rawishtext, $matches ) )  {
			$notetxt = $matches[0];
			$notes[$notecnt] = $notetxt;
			if ( $oldtxt == $notetxt ) { print "Oops - not changing."; exit; };
			$oldtxt = $notetxt;
			$rawishtext = preg_replace("/<note.+?<\/note>/smi", "<ntn$notecnt>", $rawishtext, 1 );
			$notecnt++;
		};	
		# print $rawishtext; exit;
		
		## Just start by checking if there is any tag inside a SSI that is not a chartag
		## And if so, provide feedback and exit tokenization
		# We should also check for chartags outside toktags
		# And we should check for empty word
		$temp = explode ( " ", preg_replace( "/\s+/smi", " ", $rawishtext ) );
		foreach ( $temp as $ssi ) { 
			$txtval = preg_replace ( "/^(<[^>]+>|\pP)+/", "", $ssi );
			$txtval = preg_replace ( "/(<[^>]+>|\pP)+$/", "", $txtval );
			if ( $txtval != "" ) {
				preg_match_all ( "/<\/?([a-z_]+)/", $txtval, $matches );  
				foreach ( $matches[1] as $temp2 ) {
					if ( !in_array($temp2, $chartags ) && substr($temp2, 0, 2) != "c_" ) {
						$ssit = htmlentities(preg_replace ("/%%/", " ", $txtval));
						$errors .= "<p>- invalid tag &lt;$temp2&gt; inside a token: <b>$ssit</b>"; #.substr($temp2, 0,2);
					};
				};
				if ( preg_match( "/<([^> \/]+)[^>\/]*>$/", $ssi, $matches ) ) {
					$temp2 = $matches[1];
					if ( in_array($temp2, $chartags) ) {
						$ssit = htmlentities(preg_replace ("/%%/", " ", $ssi));
						$errors .= "<p>- chartag &lt;$temp2&gt; at the end of a token: <b>$ssit</b>";
					};
				};
			};
		};
		
		if ( $errors ) {
			$maintext .= "<p>This XML file will not tokenize because there are
					 elements in the file that are not allowing in a tokenized
					 version that need to be addressed manually (<a href='index.php?action=xmlreqs'>read more</a>). <hr>
				$errors
				<hr>
				<p>Click <a href='index.php?action=rawedit&id=$fileid'>here</a> to open the raw XML to solve the issue
				<hr>
				\n\n\n$rawishtext
				";
		} else {
		
			# Character-based tags cannot span words
			# So copy them around every whitespace
			foreach ( $chartags as $tag ) { 
				preg_match_all ( "/\s*((<$tag%[^>/]+>|<$tag>).*?<\/$tag>)(<lb\/>)?\s*/smi", $rawishtext, $matches );
				# This is complicated / not working with linebreaks inside the tag span
				foreach ( $matches[1] as $key => $val ) {
					if ( preg_match ( "/(\s+)/", $val ) ) {
						$newval = preg_replace ( "/(\s+)/smi", "</$tag>$1{$matches[2][$key]}", $val );
						$val = preg_quote ( $val, '/' );
						$rawishtext = preg_replace("/$val/smi", "$newval", $rawishtext );
					};
				};
			}; 
		
			# Put a token around every whitespace
			$rawishtext = "<tok>".preg_replace("/(\s+)/", "</tok>$1<tok>", $rawishtext )."</tok>";

			# Move the <tok> just around the word, jumping the tags
			while ( preg_match("/<tok>(<[^>]+>)/", $rawishtext ) ) {
				$rawishtext = preg_replace("/<tok>(<[^>]+>)/", "$1<tok>", $rawishtext );
			};
			while ( preg_match("/(<[^>]+>)<\/tok>/", $rawishtext ) ) {
				$rawishtext = preg_replace("/(<[^>]+>)<\/tok>/", "</tok>$1", $rawishtext );
			};

			# Split off the punctuation marks
			$rawishtext = preg_replace("/(\pP+)<\/tok>/u", "</tok><tok>$1</tok>", $rawishtext );
			$rawishtext = preg_replace("/<tok>(\pP+)/u", "<tok>$1</tok><tok>", $rawishtext );

			# If we ended up with empty toks, remove them
			$rawishtext = preg_replace("/<tok><\/tok>/", "$1", $rawishtext );

			# print $rawishtext; exit;

			# Repeat jumping (can that be avoided?)
			while ( preg_match("/<tok>(<[^>]+>)/", $rawishtext ) ) {
				$rawishtext = preg_replace("/<tok>(<[^>]+>)/", "$1<tok>", $rawishtext );
			};
			while ( preg_match("/(<[^>]+>)<\/tok>/", $rawishtext ) ) {
				$rawishtext = preg_replace("/(<[^>]+>)<\/tok>/", "</tok>$1", $rawishtext );
			};

			# The character-based tags should be inside the <tok>
			$sfld = "<c_[^>]+>"; $efld = "<\/c_[^>]+>"; $mfld = "<\/c_[^>]+>|<c_[^>]+\/>"; # any c_xxx tag is a chartag
			foreach ( $chartags as $ct ) {
				$sfld .= "|<$ct\/?>|<$ct%[^>]+\/?>";
				$efld .= "|<\/$ct>"; 
				$mfld .= "|<$ct\/>|<$ct%[^>]+\/>|<\/$ct>"; 
			}; # print "\n\n$sfld\n\n\n$efld\n\n\n$rawishtext";  exit;
			$rawishtext = preg_replace("/(($sfld)+)<tok>/", "<tok>$1", $rawishtext );
			$rawishtext = preg_replace("/<\/tok>(($mfld)+)/", "$1</tok>", $rawishtext );
			
			# Also double them around punctuation marks 
			$rawishtext = preg_replace("/<\/tok><tok>(\pP+)(($efld)+)/", "$2</tok><tok>@@$2$1$2", $rawishtext );
			$rawishtext = preg_replace("/<\/tok><tok>(\pP+<lb[^>]*\/>)(($efld)+)/", "$2</tok><tok>@@$2$1$2", $rawishtext );
			$rawishtext = preg_replace("/@@<\/?/", "<", $rawishtext );
			# $rawishtext = preg_replace("/(($sfld)+)(\pP+)<\/tok><tok>/", "$1$2$1</tok><tok>$1", $rawishtext );

			# Move <lb> and <pb> at the end of a word outside the tok (end-tag stretch)
			$rawishtext = preg_replace("/(<lb[^>]*\/>)((<\/[a-z]+>)+)/", "$2$1", $rawishtext );

			# Kill toks that seem not to be part of a word
			$rawishtext = preg_replace("/(\s)<\/tok>/", "$1", $rawishtext );
			$rawishtext = preg_replace("/<tok>(\s)/", "$1", $rawishtext );	
			$rawishtext = preg_replace("/<tok>(<[^>]+>\s)/", "$1", $rawishtext ); # A beginning <tok> with only XML after it
			$rawishtext = preg_replace("/^<\/tok>/", "", $rawishtext );
			$rawishtext = preg_replace("/<tok>$/", "", $rawishtext );	

			# $maintext .= "<hr><p>After putting notes back:<br>".$rawishtext;

			# Put the notes back that we removed
			while ( preg_match("/<ntn(\d+)>/smi", $rawishtext, $matches ) )  {
				$notecnt = $matches[1];
				$notetxt = $notes[$notecnt]; 
				$rawishtext = preg_replace("/<ntn$notecnt>/", "$notetxt", $rawishtext );
			};		

			# Put back spaces inside tags
			$rawishtext = preg_replace("/%%/", " ", $rawishtext );


			# Protect the & since it is not valid XML
			#$rawishtext = preg_replace("/&/", "&amp", $rawishtext );
			// In fact, unprotect all HTML code
			$rawishtext = preg_replace("/\|&([a-z]+);\|/", "&$1;", $rawishtext );

			# $maintext .= "<hr><p>After tokenization:<br>".$rawishtext;

		
			# Remove the root node and insert into the XML
			$mtxtelement[0][0] = "#!XMLHERE!#";
			$newfile = preg_replace( "/<[^>]+>#!XMLHERE!#<\/[^>]+>/", $rawishtext, $xml->asXML() );
		
			# print $newfile; exit;

			# Check whether we generated valid XML before proceeding to numbering		
			libxml_use_internal_errors(true);
			$newxml = simplexml_load_string($newfile);
			if ( $newxml === false || $rawishtext == "" ) {
				
				# The input is not XML (anymore)
				$maintext .= "<h1>Oops</h1> <p>An unforeseen error occurred while tokenizing your XML and the result will not be saved. 
						The raw XML error messages are shows below. 
						More info can be found by looking at the source code of this page.
						Click <a href='index.php?action=rawedit&id=$fileid&display=raw'>here</a> to edit the XML, or edit the incorrect XML
						shown on the bottom of this page.
						</p><hr>";

				foreach(libxml_get_errors() as $error) {
					$maintext .= "<p>&nbsp; &nbsp; ". $error->message;
					if ( preg_match("/ line (\d+) /", $error->message, $matches) ) { 
						$linenr = $matches[1] - 1;
						if ( $linenr != 0 ) $markers .= "editor.resize(true); editor.scrollToLine($linenr, true, true, function () {}); editor.getSession().addMarker(new Range($linenr, 0, $linenr, 2000), 'warning', 'line', true);";
					}
				}

				$xmltxt = htmlentities($newfile);
				if ( $markers ) $maintext .= "<p>The (first) conflicting line has been highlighted";
	
				$maintext .= "<p>Click <a href='index.php?action=edit&cid=$filename'>here</a> to go (back) to view mode";
	
				$maintext .= "
					<div id=\"editor\" style='width: 100%; height: 300px;'>".$xmltxt."</div>
			
					<script src=\"ace/ace.js\" type=\"text/javascript\" charset=\"utf-8\"></script>
					<style>.warning
					{
						background: rgba(255, 255, 50, 0.1);
						position: absolute;
						width: 100% !important;
						left: 0 !important;
					}</style>
					<script>
						var editor = ace.edit(\"editor\");
						editor.setTheme(\"ace/theme/chrome\");
						editor.getSession().setMode(\"ace/mode/xml\");
						editor.setReadOnly(true);
						var Range = ace.require(\"ace/range\").Range;
						$markers
					</script>
						";
			
				# $maintext .= "<hr><textarea name=''>$rawishtext</textarea>";
// 				$maintext .= "<hr><p><form action='index.php?action=rawsave&cid=$fileid' method=post>
// 				<textarea name=rawxml style='width: 100%; height: 400px;'>".$rawishtext."</textarea>
// 				<input type=submit value=Save> 
// 				</form>";
				
			} else {
		
				# Number the tokens
				$wcnt = 1;
				$result = $newxml->xpath("//tok"); 
				foreach ( $result as $node ) {
					$word = $node->asXML(); 
					$xmlword = $word; $xmlword = preg_replace("/<\/?tok>/", "", $xmlword);

					$clean = $word;
					$clean = preg_replace("/<del [^>]+>.*?<\/del>/", "", $clean);
					$clean = preg_replace("/<del>.*?<\/del>/", "", $clean);
					$clean = preg_replace("/-<lb/", "<lb", $clean);
					$fwrd = preg_replace("/<expan>.*?<\/expan>/", "", $clean); # This is wrong in PS
					$fwrd = preg_replace("/<ex>.*?<\/ex>/", "", $fwrd);
					$clean = preg_replace("/<[^>]+>/", "", $clean);
					$fwrd = preg_replace("/<[^>]+>/", "", $fwrd);
					print "\n<p>$wcnt. $word / $xmlword / $fwrd / $clean ";
					$node->addAttribute('id', "w-$wcnt"); $wcnt++;
			
					if ( $fwrd != $xmlword ) { 
						if ( $fwrd == "" ) { $fwrd = "--"; };
						$node->addAttribute('form', $fwrd); 
					};
					if ( $clean != $xmlword && $clean != $fwrd ) { 
						$node->addAttribute('fform', $clean); 
					};
				};

				saveMyXML($newxml->asXML(), $fileid);

				$maintext .= "<hr><p>Your text has been tokenized - reloading to <a href='index.php?action=edit&id=$fileid'>the tokenized page</a>";
				$maintext .= "<script langauge=Javasript>top.location='index.php?action=edit&id=$fileid';</script>";
		
			};
		
		};
				
	} else if ( $fileid ) { 


		if ( preg_match("/\/([a-z0-9]+)$/i", $mtxtelement, $matches ) ) {
			$mtxtelm = $matches[1];
		} else {
			$mtxtelm = "text";
		};

		if ( $settings['xmlfile']['linebreaks'] ) { $lbcmd = " --linebreaks "; };

		# Build the UNIX command
		$cmd = "/usr/bin/perl {$thisdir}/../common/Scripts/xmltokenize.pl --mtxtelm=$mtxtelm --filename='xmlfiles/$fileid' $lbcmd ";
		# print $cmd; exit;
		$res = shell_exec($cmd);
		for ( $i=0; $i<1000; $i++ ) { $n = $n+(($i+$n)/$i); }; # Force a bit of waiting...
		
		if ( $_GET['tid'] )
			$nexturl = "index.php?action=tokedit&cid=$fileid&tid={$_GET['tid']}";
		else 
			$nexturl = "index.php?action=edit&id=$fileid";
		$maintext .= "<hr><p>Your text has been renumbered - reloading to <a href='$nexturl'>the edit page</a>";
		$maintext .= "<script langauge=Javasript>top.location='$nexturl';</script>";
	
	} else {
	
		print "oops"; exit;
	
	};

?>