<?php

# A Class to read a TEITOK style TEI/XML file
# expects the filename to be given in URL
# (c) Maarten Janssen, 2015

class TTXML
{
	public $filename; # The name of the file wrt $xmlfolder
	public $xmlid; # The ID of the file
	public $fileid; # Full path of the file (within xmlfiles)
	public $xml; # The parsed XML
	
	var $xmlfolder;
	var $rawtext;
	var $title;
	
	public $pagnav; # The page navigation bar
	public $facsimg; # The facsimile image for the page

	function __construct($fileid = "", $fatal = 1, $options ) {	
		global $xmlfolder;
		
		# Use $_GET to find the file
		if ( !$xmlfolder ) $xmlfolder = "xmlfiles";
		if ( strstr($options, "pagetrans") != false ) {  $xmlfolder = "pagetrans"; };
		
		if (!$fileid) $fileid = $_POST['id'] or $fileid = $_GET['id'] or $fileid = $_GET['cid'];
		$this->fileid = $fileid;
		if ( !preg_match("/\./", $fileid) && $fileid ) $fileid .= ".xml";
		
		if ( !$this->fileid  && $fatal ) { 
			fatal ( "No XML file selected." );  
		};

		if ( !file_exists("$xmlfolder/".$this->fileid) && substr($this->fileid,-4) != ".xml" ) { 
			$this->fileid .= ".xml";
		};
	
		if ( !file_exists("$xmlfolder/$fileid") ) { 
			$fileid = preg_replace("/^.*\//", "", $fileid);
			$test = array_merge(glob("$xmlfolder/**/$fileid")); 
			if ( !$test ) 
				$test = array_merge(glob("$xmlfolder/$fileid"), glob("$xmlfolder/*/$fileid"), glob("$xmlfolder/*/*/$fileid"), glob("$xmlfolder/*/*/*/$fileid")); 
			$temp = array_pop($test); 
			$fileid = preg_replace("/^".preg_quote($xmlfolder, '/')."\/?/", "", $temp);
	
			if ( $fileid == "" && $fatal ) {
				fatal("No such XML File: {$oid}"); 
			};
			$this->fileid = $fileid;
		};

		if ( !file_exists("$xmlfolder/".$this->fileid) && substr($this->fileid,-4) != ".xml" ) { 
			$this->fileid .= ".xml";
		};

		$this->filename = preg_replace ( "/.*\//", "", $fileid );
		$this->xmlid = preg_replace ( "/\.xml/", "", $this->filename );

		$this->rawtext = file_get_contents("$xmlfolder/$fileid"); 

		if ( strstr($options, "keepns") == false ) {
			$this->rawtext = namespacemake($this->rawtext);
		};
			
		$this->xml = simplexml_load_string($this->rawtext);
		if ( !$this->xml && $fatal ) { fatal ( "Failing to read/parse $fileid" ); };
		
	}
	
	function title() {
		if (!$this->xml) return "";
		if ( !$this->title ) {
			$result = $this->xml->xpath("//title"); 
			$this->title = $result[0];
			if ( $this->title == "" ) $this->title = "<i>{%Without Title}</i>";
		};
		return $this->title;
	}

	function viewheader() {
		// Create necessary data for the view mode
		global $settings; global $jsurl;
		# Build the attribute names	
		foreach ( $settings['xmlfile']['pattributes']['forms'] as $key => $item ) {
			if ( $username || !$item['admin'] ) {
				if ( $key != "pform" ) { 
					if ( !$item['admin'] || $username ) $attlisttxt .= $alsep."\"$key\""; $alsep = ",";
					$attnamelist .= "\nattributenames['$key'] = \"{%".$item['display']."}\"; ";
				};
			};
		};
		foreach ( $settings['xmlfile']['pattributes']['tags'] as $key => $item ) {
			if ( !$item['admin'] || $username ) {
				$attlisttxt .= $alsep."\"$key\""; $alsep = ",";		
				$attnamelist .= "\nattributenames['$key'] = \"{%".$item['display']."}\"; ";
			};
		};
		$allatts = array_merge($settings['xmlfile']['pattributes']['forms'], $settings['xmlfile']['pattributes']['tags']);
		$jsonforms = array2json($allatts);
		$jsontrans = array2json($settings['transliteration']);
		$header = "
			<script language=Javascript src=\"$jsurl/tokview.js\"></script>
			<div id='tokinfo' style='display: block; position: absolute; right: 5px; top: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa; z-index: 3;'></div>
			<script language=Javascript>
				var formdef = $jsonforms;
				var attributelist = Array($attlisttxt);
				var transl = $jsontrans;
				$attnamelist
			</script>
			";
	
		return $header;
	}
		
	function tableheader() {
		if (!$this->xml) return "";
		// Create a header with information about the first from the teiHeader
		if ( $_GET['tpl'] && file_exists("Resources/teiHeader-{$_GET['tpl']}.tpl") ) {
			$header = file_get_contents("Resources/teiHeader-{$_GET['tpl']}.tpl");
			$tableheader .= xpathrun($header, $this->xml);
		} else if ( $_GET['headers'] == "full" && file_exists("Resources/teiHeader-long.tpl") ) {
			$header = file_get_contents("Resources/teiHeader-long.tpl");
			$tableheader .= xpathrun($header, $this->xml);
			if ( file_exists("Resources/teiHeader.tpl") ) $tableheader .= "<ul><li><a href='{$_SERVER['REQUEST_URI']}&headers=short'>{%less header data}</a></ul>";
			$tableheader .= "<hr>"; 
		} else if ( file_exists("Resources/teiHeader.tpl") ) {
			$header = file_get_contents("Resources/teiHeader.tpl");
			$tableheader .= xpathrun($header, $this->xml);
			$tableheader .= "<ul>"; 
			if ( !$_GET['cid'] && !$_GET['id'] ) $cidurl = "&cid=$fileid";
			if ( file_exists("Resources/teiHeader-long.tpl") ) $tableheader .= "<li><a href='{$_SERVER['REQUEST_URI']}$cidurl&headers=full'>{%more header data}</a>";
			if ( $settings['xmlfile']['teiHeader'] ) {
				foreach ( $settings['xmlfile']['teiHeader'] as $key => $item ) {
					$cond = $item['condition'];
					if ( $cond ) {
						$result = $this->xml->xpath($cond); 
						if ( !$result ) {
							continue; # Conditional header
						};
					};
					$tpl = $key;
					if ( $item['admin'] ) {
						if ($username) $tableheader .= " &bull; <a href='index.php?action=file&cid=$fileid&tpl=$tpl' class=adminpart>{$item['display']}</a>";
					} else if ( !$item['admin'] ) {
						$tableheader .= " &bull; <a href='index.php?action=file&cid=$fileid&tpl=$tpl'>{$item['display']}</a>";
					};
				};
			};
			if ( file_exists("Resources/teiHeader-edit.tpl") && $username ) $tableheader .= " &bull; <a href='index.php?action=header&act=edit&cid=$fileid&tpl=teiHeader-edit.tpl' class=adminpart>edit teiHeader</a>";
			$tableheader .= "</ul><hr>";
		} else {
			foreach ( $headershow as $hq => $hn ) {
				$result = $this->xml->xpath($hq); 
				$hv = $result[0];
				if ( $hv ) {
					$htxt = $hv->asXML();
					$tableheader .= "<h3>{%$hn}</h3><p>$htxt</p>";
				};
			}; 
			if ( $headershow ) $tableheader .= "<hr>";
		};
		return $tableheader;
	}
	
	function asXML() {
		global $mtxtelement;
		$result = $this->xml->xpath($mtxtelement);
		if ($result) {
			$xmltxt = $result[0]->asXML();
		} else {
			$xmltxt = "($mtxtelement not found)";
		};
		
		# Protect empty elements
		$xmltxt = preg_replace( "/<([^> ]+)([^>]*)\/>/", "<\\1\\2></\\1>", $xmltxt );
		
		return $xmltxt;
	}
	
	function mtxt($editable=1) {
		global $username, $settings; global $jsurl;
		if ( $editable ) { 
			$moreactions .= "\n\tvar username='$username'; ";
		};	
		
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


		$mtxt = "
			<script language=Javascript src='$jsurl/tokedit.js'></script>
			<script language=Javascript src='$jsurl/tokview.js'></script>
			<div id='tokinfo' style='display: block; position: absolute; right: 5px; top: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa;'></div>
			<div id='mtxt'>".$this->asXML()."</div>
			<script language='Javascript'>
				var formdef = $jsonforms;
				var attributelist = Array($attlisttxt);
				$attnamelist
				var tid = '".$this->fileid."'; var previd = '{$_GET['tid']}';
				var orgtoks = new Object();
				$moreactions
				formify();
			</script>";
		
			
		return $mtxt;
	}
	
	function context( $tokid ) {
		# Return a restricted part of the text
		global $settings;
		$tmp = $this->xml->xpath("//*[@id='$tokid']"); $token = $tmp[0];
		if ( !$token ) return "";
		$tmp = $token->xpath("ancestor::s | ancestor::l | ancestor::p");
		if ( $tmp ) {	
			$sent = $tmp[0];
			$editxml = $sent->asXML();
			$context = "<div id=mtxt>".$editxml."</div>";
			if ( is_array($settings['xmlfile']['sattributes']) ) 
			foreach ( $settings['xmlfile']['sattributes'] as $key => $val ) {
				if ( $val['color'] ) $style = " style=\"color: {$val['color']}\" ";
				if ( $sent[$key] ) $context .= "<p title=\"{$val['display']}\" $style>$sent[$key]</p>";
			};
		} else {
			# Show a reasonably sized context
			# Until defined, just show nothing
		};
		
		return $context;
	}
	
	function page ( $pagid, $id ) {
		global $action;
		
		$editxml = $this->rawtext;
	
		# Return the xml for a page of the text
		$pbelm = "pb";
		$titelm = "Page";
		$pbtype = "pb";
		$pbsel = "&pbtype={$_GET['pbtype']}";

		if ( !$pagid ) $pagid = $_GET['pageid'];
		if ( !$tid ) $tid = $_GET['tid'] or $tid = $_GET['jmp'];

		if ( $pagid ) {
			$pb = "<$pbelm id=\"$pagid\"";
			$pidx = strpos($editxml, $pb);
		} else if ( $tid ) {
			$tokidx = strpos($editxml, "id=\"$tid\"");
			$pb = "<$pbelm";
			$pidx = rstrpos($editxml, $pb, $tokidx);
		} else {
			$pb = "<$pbelm";
			$pidx = strpos($editxml, $pb);
		};
		
		if ( !$pidx || $pidx == -1 ) { 
			# When @n is not the first attribute, we cannot use strpos - try regexp instead (slower)
			if ( $pagid ) {
				preg_match("/<$pbelm [^>]*id=\"$pagid\"/", $editxml, $matches, PREG_OFFSET_CAPTURE, 0);
			} else {
				preg_match("/<$pbelm [^>]*n=\"{$_GET['page']}\"/", $editxml, $matches, PREG_OFFSET_CAPTURE, 0);
			};
			$pidx = $matches[0][1];
		};
		if ( !$pidx || $pidx == -1 ) { fatal ("No such $pbelm in XML: $pagid"); };

		
		# Find the next page/chapter (for navigation, and to cut off editXML)
		$nidx = strpos($editxml, "<$pbelm", $pidx+1); 
		if ( !$nidx || $nidx == -1 ) { 
			$nidx = strpos($editxml, "</text", $pidx+1); $nnav = "";
		} else {
			$nidy = strpos($editxml, ">", $nidx); 
			$tmp = substr($editxml, $nidx, $nidy-$nidx ); 
			 
			if ( preg_match("/id=\"(.*?)\"/", $tmp, $matches ) ) { $npid = $matches[1]; };
			if ( preg_match("/n=\"(.*?)\"/", $tmp, $matches ) ) { $npag = $matches[1]; };
			
			if ( $npid ) $nnav = "<a href='index.php?action=$action&cid=$this->fileid&pageid=$npid&pbtype=$pbtype'>> $npag</a>";
			else $nnav = "<a href='index.php?action=$action&cid=$this->fileid&pageid=$npag'>> $npag</a>";
		};
		
		# Find the previous page/chapter (for navigation)
		$bidx = rstrpos($editxml, "<$pbelm ", $pidx-1); 
		if ( !$bidx || $bidx == -1 ) { 
			$bidx = strpos($editxml, "<text", 0); $bnav = "<a href='index.php?action=pages&cid=$this->fileid$pbsel'>{%index}</a>";
		} else {
			$tmp = substr($editxml, $bidx, 150 ); 
			if ( preg_match("/id=\"(.*?)\"/", $tmp, $matches ) ) { $bpid = $matches[1]; };
			if ( preg_match("/n=\"(.*?)\"/", $tmp, $matches ) ) { $bpag = $matches[1]; } else { $bpag = ""; };
			if ( $bpid  )  $bnav = "<a href='index.php?action=$action&cid=$this->fileid&pageid=$bpid$pbsel'>$bpag <</a> ";
			else $bnav = "<a href='index.php?action=$action&cid=$this->fileid&page=$bpag'>$bpag <</a>";
			if ( !$firstpage ) { $bnav = "<a href='index.php?action=pages&cid=$this->fileid$pbsel'>{%index}</a> &nbsp; $bnav"; };
		};

		// when pbelm != pb, grab the <pb/> from just before the milestone
		if ( $pb && $pbelm != "pb") {
 			if ( strpos($editxml, "<tok", $pidx) < strpos($editxml, "<pb", $pidx) ) {
 				$bpb1 = rstrpos($editxml, "<pb ", $pidx-1); 
 				$bpb2 = strpos($editxml, ">", $bpb1);
 				$len = ($bpb2-$bpb1)+1;
				$facspb = substr($editxml, $bpb1, $len); 
 			};
		};		
		
		$span = $nidx-$pidx;
		$editxml = $facspb.substr($editxml, $pidx, $span); 

		$editxml = preg_replace("/<lb([^>]+)\/>/", "<lb\\1></lb>", $editxml);
		
		if ( $_GET['page'] ) $folionr = $_GET['page']; // deal with pageid
		else if ( $pagid ) {
			if ( preg_match("/<$pbelm [^>]*n=\"(.*?)\"[^>]*id=\"$pagid\"/", $editxml, $matches ) 
				|| preg_match("/<$pbelm [^>]*id=\"$pagid\"[^>]*n=\"([^\"]+)\"/", $editxml, $matches ) ) 
					$folionr = $matches[1];
		} else if ( preg_match("/<$pbelm [^>]*n=\"(.*?)\"/", $tmp, $matches ) ) {
			$folionr = $matches[1]; 
		};

		if ( preg_match("/<$pbelm [^>]*facs=\"(.*?)\"/", $editxml, $matches ) ) {
			$img = $matches[1];
			if ( !preg_match("/^(http|\/)/", $img) ) $img = "Facsimile/$img";
		};
		
		$this->facsimg = $img;
		
		if ( $pbelm == "pb" ) $foliotxt = "{%Folio}";
		
		# Build the page navigation
		$this->pagenav = "<table style='width: 100%'><tr> <!-- /<$pbelm [^>]*id=\"$pagid\"[^>]*n=\"(.*?)\"/ -->
						<td style='width: 33%' align=left>$bnav
						<td style='width: 33%' align=center>$foliotxt $folionr
						<td style='width: 33%' align=right>$nnav
						</table>
						<hr> 
						";
		
		return $editxml;
	}

	function save() {
		# First - make a once-a-day backup
		$date = date("Ymd"); 
		$buname = preg_replace ( "/\.xml/", "-$date.xml", $this->filename );
		$buname = preg_replace ( "/.*\//", "", $buname );
		if ( !file_exists("backups/$buname") ) {
			copy ( "xmlfiles/{$this->filename}", "backups/$buname");
		};
		# Now, make a safe XML text out of this and save it to file
		if ( $this->fileid ) $filetosave = $this->fileid;
		file_put_contents("xmlfiles/$filetosave", $this->xml->asXML());
	}
	
};

?>