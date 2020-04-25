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
	public $audio = array(); # An array with the audio file(s) in this XML
	public $audiourl; # The URL for the first AUDIO node
	public $video = array(); # An array with the video file(s) in this XML
	
	var $xmlfolder;
	var $rawtext;
	var $title;
	
	public $pagnav; # The page navigation bar
	public $facsimg; # The facsimile image for the page

	function __construct($fileid = "", $fatal = 1, $options = "" ) {	
		global $xmlfolder; global $baseurl; global $settings;
		
		# Use $_GET to find the file
		if ( !$xmlfolder ) $xmlfolder = "xmlfiles";
		if ( strstr($options, "pagetrans") != false ) {  $xmlfolder = "pagetrans"; };
		
		if (!$fileid) $fileid = $_POST['id'] or $fileid = $_GET['id'] or $fileid = $_GET['cid'];
		$this->fileid = $fileid;
		if ( !preg_match("/\./", $fileid) && $fileid ) $fileid .= ".xml";
		$oid = $fileid;
		
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
		
		// See if there is an Audio element in the header
		foreach ( $this->xml->xpath("//recording//media") as $medianode ) {
			$mimetype = $medianode['mimeType'] or $mimetype = $medianode['mimetype'] or $mimetype = mime_content_type($medianode['url']);
			if ( strstr($mimetype, "video") ) {
				array_push($this->video, $medianode);
			} else { // If it ain't video, it's audio
				array_push($this->audio, $medianode);
				if ( $audiourl == "" ) $audiourl = $medianode['url']; 
			};
		}; 
		if ( $audiourl != "" ) {
			if ( !strstr($audiourl, 'http') ) {
				if ( file_exists($audiourl) ) $audiourl =  "$baseurl/$audiourl"; 
				else if ( !strstr($audiourl, 'Audio') ) $audiourl = $baseurl."Audio/$audiourl"; 
			};
			$this->audiourl = $audiourl;
		};		
		
		// If we have pseudonimization rules, pseudonimize the text
		if ( $settings['anonymization'] && !$settings['anonymization']['manual'] ) {
			$this->pseudo();
		};		
	}
	
	function title() {
		global $settings;
		if (!$this->xml) return "";
		if ( !$this->title ) {
			if ( $settings['xmlfile']['title'] == "[id]" ) {
				$this->title = $this->xmlid;
			} else {
				$titlexp = $settings['xmlfile']['title']."" or $titlexp = "//title";
				$result = $this->xml->xpath($titlexp); 
				$this->title = $result[0];
			};
			if ( $this->title == "" ) {
				if (  $settings['xmlfile']['title'] ) $this->title = "<i>{%Without Title}</i>";
				else $this->title = $this->xmlid;
			};
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
		if ( $settings['xmlfile']['mtokform'] ) $attnamelist .= "\nvar mtokform = true;";
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
		
	function tableheader( $tpl = "", $showbottom = true ) {
		global $username; global $settings;
		if (!$this->xml) return "";

		// Determine which header to show
		if ( $tpl == "" ) {
			if ( $_GET['headers'] == "full" ) $tpl = "long";
			else $tpl = $_GET['tpl'];
		};

		if ( file_exists("Resources/teiHeader.tpl") ) {
			// Create a header with information about the first from the teiHeader
			$opts = explode(",", $tpl); array_push($opts, "");
			while ( $tplfile == "" && count($opts) ) {
				$opt = array_shift($opts);
				if ( file_exists("Resources/teiHeader-$opt.tpl") ) {
					$tplfile = "Resources/teiHeader-$opt.tpl";
				} else if ( file_exists("Resources/teiHeader$opt.tpl") ) {
					$tplfile =  "Resources/teiHeader$opt.tpl";
				};
			};
		
			$header = file_get_contents($tplfile);
			$tableheader .= xpathrun($header, $this->xml);
		} else {
			
			if ( $tpl == "" ) $tpl = "short";
		
			$tableheader .= "<table>";
			foreach ( $settings['teiheader'] as $key => $val ) {
				$disp = $val['display'] or $disp = $key;
				if ( $val['type'] == "sep" ) {
					$tableheader .= "<tr><th colspan=2>{%$disp}";
					continue;
				};
				$xval = current($this->xml->xpath($val['xpath']));
				if ( $xval && ( !$val['admin'] || $username ) ) {
					if ( in_array($tpl, explode(",", $val['show'])) || ( !$val['show'] && $tpl == "long" ) ) {
						if ( preg_match("/@[^\/]+$/", $val['xpath']) ) $hval = "".$xval;
						else $hval = preg_replace( "/^<[^>]+>|<[^>]+>$/", "", $xval->asXML());
						// Link when so asked
						if ( $val['link'] ) {
							if ( strpos($val['link'], "http") != false ) $tmp = $val['link'];
							else $tmp = current($this->xml->xpath($val['link']));
							if ( $tmp ) $hval = "<a href='$tmp'>$hval</a>";
						};
						if ( $settings['teiheader'][$key]['options'][$hval]['display'] ) 
							$hval = $settings['teiheader'][$key]['options'][$hval]['display'];
						if ( $settings['teiheader'][$key]['i18n'] ) 
							$hval = "{%$hval}";
						$tableheader .= "<tr><th>{%$disp}<td>$hval";
					} else {
						$moretoshow = 1;
					};
				};
			};
			$tableheader .= "</table>";
		
		};
		
		# Show alternative header views
		if ( $showbottom ) {
			if ( !$_GET['cid'] && !$_GET['id'] ) $cidurl = "&cid=$this->fileid";
			$headeroptions = $settings['xmlfile']['teiHeader'] or $headeroptions = array (
				'' => array ( "display" => "less header data" ),
				'long' => array ( "display" => "more header data" ), # {%more header data}
				'edit' => array ( "display" => "edit header data", "edit" => 1 ), # {%edit header data}
				);
			$sep = "";
			if ( $username && $settings['teiheader'] ) {
				$moreopts .= " $sep <a href='index.php?action=header&act=edit&cid=$this->fileid' class=adminpart>edit header data</a>";
					$sep = "&bull;";
			};
			foreach ( $headeroptions as $key => $item ) {
				if ( $key ) $tfn = "teiHeader-$key.tpl"; else $tfn = "teiHeader.tpl";
				if ( !file_exists("Resources/$tfn") && ( !$moretoshow || $key != "long" ) && ( !$tpl || $key != "" ) ) continue;
				if ( $_GET['tpl'] == $key ) continue;
				$cond = $item['condition'];
				if ( $cond ) {
					$result = $this->xml->xpath($cond); 
					if ( !$result ) {
						continue; # Conditional header
					};
				};
				$tpl = $key;
				if ( $item['edit'] ) {
					if ($username && !$settings['teiheader'] ) $moreopts .= " $sep <a href='index.php?action=header&act=edit&cid=$this->fileid&tpl=$tpl' class=adminpart>{%{$item['display']}}</a>";
					$sep = "&bull;";
				} else if ( $item['admin'] ) {
					if ($username) $moreopts .= " $sep <a href='index.php?action={$_GET['action']}&cid=$this->fileid&tpl=$tpl$edittxt' class=adminpart>{%{$item['display']}}</a>";
					$sep = "&bull;";
				} else {
					$moreopts .= " $sep <a href='index.php?action={$_GET['action']}&cid=$this->fileid&tpl=$tpl'>{%{$item['display']}}</a>";
					$sep = "&bull;";
				};
			};
			if ( $username ) $moreopts .= " $sep <a href='index.php?action=header&act=rawview&cid=$this->fileid' class=adminpart>view teiHeader</a>";
			if ( $moreopts ) $tableheader .= "<ul><li>$moreopts</ul>";
			$tableheader .= "<hr>";
		};
		
		return $tableheader;
	}
	
	function asXML( $whole = false ) {
		global $mtxtelement; global $settings; global $username;
		
		if ( $_GET['page'] == "all" ) $whole = 1;
		
		if ( $settings['xmlfile']['restriction'] && !$this->xml->xpath($settings['xmlfile']['restriction']) && !$username ) { 
			$tokid = $_GET['jmp'] or $tokid = $_GET['tid'] or $tokid = 'w-1';
			# Take only the first one
			$tokid = preg_replace("/ .*/", "", $tokid);
			$xmltxt = $this->context($tokid);
			$this->pagenav = "<p>{%Due to copyright restrictions, only a fragment of this text is displayed}</p><hr>"; 
		} else if ( $settings['xmlfile']['paged'] != 2 && $_GET['div'] && 1==2 ) {
			# Show a whole DIV
		} else if ( !$whole && ( $settings['xmlfile']['paged']['type'] == "xp" ) ) {
			$xmltxt = $this->xppage();
		} else if ( !$whole && ( $settings['xmlfile']['paged'] || $_GET['pbtype'] ) ) {
			$xmltxt = $this->page();
		} else {
			$result = $this->xml->xpath($mtxtelement);
			if ($result) {
				$xmltxt = $result[0]->asXML();
			} else {
				$xmltxt = "($mtxtelement not found)";
			};
		};
				
		# Protect empty elements
		$xmltxt = preg_replace( "/<([^> ]+)([^>]*)\/>/", "<\\1\\2></\\1>", $xmltxt );
		
		return $xmltxt;
	}
	
	function xppage($pageid = "") {
		global $settings; global $action;
		
		if ( !$pagid ) $pagid = $_GET['pageid'];
		$jmp =  $_GET['jmp'] or $jmp = $_GET['tid'];
		$jmp = preg_replace("/ .*/", "", $jmp);
		$pbelm = $_GET['pbelm'] or $pbelm = $settings['xmlfile']['paged']['element'];
		
		if ( $pagid ) { 
			$xp = "//*[@id='$pagid']";
		} else if ( $_GET['appid'] ) {
			$appid = $_GET['appid'];
			$xp = "//*[@appid='$appid']/ancestor-or-self::$pbelm";
		} else if ( $jmp ) {
			$xp = "//*[@id='$jmp']/ancestor-or-self::$pbelm";
		} else {
			$xp = "//text//$pbelm";
		};
	
		$page = current($this->xml->xpath($xp)); 
		if ( !$page ) {
			if ( $settings['xmlfile']['paged']['hard'] ) fatal("No such page: $xp");
			else $page = $this->xml;
		};
		
		$num = $page['n'] or $num = $page['id'];
		$folioname = $settings['xmlfile']['paged']['display'] or $folioname = "page";
		if ( $settings['xmlfile']['paged']['i18n'] ) $folioname = "{%$folioname}";

		$npag = current($page->xpath("./preceding-sibling::{$pbelm}[1]"));
		if ( $npag ) {
			$bnum = $npag['n'] or $bnum = $npag['id'];
			$bid = $npag['id'];
			$bnav = "<a href='index.php?action=pages&cid=$this->fileid$pbsel'>{%index}</a> &bull; <a href='index.php?action=$action&cid=$this->xmlid&pageid=$bid'>$folioname $bnum</a> <";
			$hasnav = 1;
		} else {
			$bnav = "<a href='index.php?action=pages&cid=$this->fileid$pbsel'>{%index}</a>";
		};
		$npag = current($page->xpath("./following-sibling::$pbelm"));
		if ( $npag ) {
			$bnum = $npag['n'] or $bnum = $npag['id'];
			$bid = $npag['id'];
			$nnav = "> <a href='index.php?action=$action&cid=$this->xmlid&pageid=$bid'>$folioname $bnum</a>";
			$hasnav = 1;
		};

		$foliotxt = "$folioname $num";

		if ( $page['appid'] && file_exists("Resources/toc.xml") ) {
			$tocxml = simplexml_load_file("Resources/toc.xml");
			$tocnode = current($tocxml->xpath("//*[@appid='{$page['appid']}']"));
			if ( $tocnode ) {
				$tmp = $tocnode; $sep = "";
				while ( $tmp && $tmp->getName() != "toc") {
					$levelname = $tmp['display-'.$lang] or $levelname = $tmp['display'] or $levelname = $tmp['n'] ;
					if ( $settings['xmlfile']['toc']['i18n'] ) $levelname = "{%$levelname}";
					$tocnav = "<a href='index.php?action=pages&cid=$this->xmlid&appid={$tmp['appid']}'>$levelname</a> $sep $tocnav";
					$tmp = current($tmp->xpath("..")); $sep = ">";
				};		
				$tocnav = "<div class=tocnav>{$page['appid']}: $tocnav</div>";
			};
		};

		# Build the page navigation
		if ( $hasnav ) $this->pagenav = "
						$tocnav
						<table style='width: 100%'><tr> 
						<td style='width: 33%' align=left>$bnav
						<td style='width: 33%' align=center>$foliotxt $folionr
						<td style='width: 33%' align=right>$nnav
						</table>
						<hr> 
						";
	
		return $page->asXML();
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
	
	function context( $tokid, $size="", $highlight = false ) {
		# Return a restricted part of the text
		global $settings;
		$tmp = $this->xml->xpath("//*[@id='$tokid']"); $token = $tmp[0];
		if ( !$token ) return "";
		
		if ( !$size ) {
			$tmp = $token->xpath("ancestor::s | ancestor::l | ancestor::p");
		} else $tmp = "";
			
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
			# TODO: Show a reasonably sized context
			# Until defined, just show the first parent node

			if ( !$size ) $size = 7; # Default to 7 token window (left/right)
			$tmp = $this->rawtext;
			$tokpos = strpos($tmp, "id=\"$tokid\""); 
			$tokbef = $tokpos;
			for ( $i=0; $i<$size; $i++ ) $tokbef = rstrpos($tmp, "<tok ", $tokbef-1);
			if ( !$tokbef ) $tokbef = strpos($tmp, "<tok ");
			$tokaft = $tokpos+1;
			for ( $i=0; $i<$size+1; $i++ ) $tokaft = strpos($tmp, "<tok ", $tokaft+1); 
			if ( !$tokaft ) $tokaft = rstrpos($tmp, "<tok ");

			
			$context = substr($tmp, $tokbef, $tokaft-$tokbef);
			
		};
		
		if ( $highlight ) $context = preg_replace("/ (id=\"$tokid\")/", " \\1 hl=\"1\"", $context);

		return $context;
	}
	
	function page ( $pagid = "" ) {
		global $action; global $settings; global $pbtype;
		
		$editxml = $this->rawtext;
	
		# Return the xml for a page (or other element) of the text
		
		// Determine what element to use
		# if ( $settings['xmlfile']['paged'] == 2) $me = "you";
		
		if ( $_GET['action'] == "appalign" ) $pbtmp = $_GET['pbtype'] or $pbtmp = $settings['appid']['baseview'];
		else if ( is_array($settings['xmlfile']['paged']) ) $pbtmp = $settings['xmlfile']['paged']['element'];
		else $pbtmp = $_GET['pbtype'] or $pbtmp = "pb";
		
		// Determine kind of page to cut out
		if ( $action == "pagetrans" ) { // Page
			$pbelm = "page";
			$titelm = "Page";
		} else if ( $pbtmp == "pb" ) { // Page
			$pbelm = "pb";
			$titelm = "Page";
			$pbsel = "&pbtype={$_GET['pbtype']}";
		} else if ( $pbtmp == "chapter"  ) {  // Chapter
			$pbtype = "milestone[@type=\"chapter\"]";
			$titelm = "Chapter";
			$foliotxt = $titelm;
			$pbelm = "milestone";
			$pbsel = "&pbtype={$pbtmp}";
		} else if ( $this->is_closed($pbtmp) ) {  // Generic closed element
			$pbtype = $pbtmp;
			$pbelm = $pbtmp;
		} else if ( $settings['xmlfile']['milestones'][$pbtmp] ) {  // Custom-defined milestone
			$elm = $pbtmp;
			$pbtype = "milestone[@type=\"$elm\"]";
			$titelm = $settings['xmlfile']['milestones'][$pbtmp]['display'] or $titelm = ucfirst($elm);
			$titelm = "{%$titelm}";
			$foliotxt = $titelm;
			$pbelm = "milestone";
			$pbsel = "&pbtype={$pbtmp}";
		} else if ( is_array($settings['xmlfile']['paged']) && $settings['xmlfile']['paged']['closed'] ) {  // Custom-defined XML node
			$pbtype = $pbtmp;
			$titelm = $settings['xmlfile']['paged']['display'] or $titelm = ucfirst($pbtmp);
			$pbelm = $pbtmp;
		} else {  // Generic milestone
			$pbtype = "milestone[@type=\"{$pbtmp}\"]";
			$titelm = $settings['xmlfile']['paged']['display'] or $titelm = ucfirst($pbtmp);
			$pbelm = "milestone";
			$pbsel = "&pbtype={$pbtmp}";
		};

		if ( !$pagid ) $pagid = $_GET['pageid'];
		if ( !$tid ) $tid = $_GET['tid'] or $tid = $_GET['jmp'];
		$tid = preg_replace("/ .*/", "", $tid);

		if ( $pagid ) {
			$pb = "<$pbelm id=\"$pagid\"";
			$pidx = strpos($editxml, $pb);
		} else if ( $_GET['appid'] ) {
			$tokidx = strpos($editxml, " appid=\"{$_GET['appid']}\"");
			$pb = "<$pbelm";
			$pidx = rstrpos($editxml, $pb, $tokidx);
		} else if ( $tid ) {
			$tokidx = strpos($editxml, " id=\"$tid\"");
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
		if ( !$pidx || $pidx == -1 ) { 
			if ( $pagid ) 
				fatal ("No such $pbelm in XML: $pagid"); 
			else {
				global $mtxtelement;
				$result = $this->xml->xpath($mtxtelement);
				if ($result) {
					$xmltxt = $result[0]->asXML();
				} else {
					$xmltxt = "($mtxtelement not found)";
				};
				return $xmltxt;
			};
		};

		
		# Find the next page/chapter (for navigation, and to cut off editXML)
		$nidx = strpos($editxml, "<$pbelm", $pidx+1); 
		if ( !$nidx || $nidx == -1 ) { 
			$nidx = strpos($editxml, "</text", $pidx+1); $nnav = "";
		} else {
			$nidy = strpos($editxml, ">", $nidx); 
			$tmp = substr($editxml, $nidx, $nidy-$nidx ); 
			 
			if ( preg_match("/id=\"(.*?)\"/", $tmp, $matches ) ) { $npid = $matches[1]; };
			if ( preg_match("/n=\"(.*?)\"/", $tmp, $matches ) ) { $npag = $matches[1]; };
			
			if ( $npid ) $nnav = "<a id=nextpag href='index.php?action=$action&cid=$this->fileid&pageid=$npid&pbtype=$pbtype'>> $npag</a>";
			else $nnav = "<a id=nextpag href='index.php?action=$action&cid=$this->fileid&pageid=$npag'>> $npag</a>";
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
			else $bnav = "<a id=prevpag href='index.php?action=$action&cid=$this->fileid&page=$bpag'>$bpag <</a>";
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

	function restrict() {
		# Restrict the XML to only the page
		$this->xml = simplexml_load_string($this->page());
	}

	function is_closed($elm) {
		if ( $_GET['action'] == "appalign" ) return true;
	}

	function viewswitch($initial = true, $withself = false ) {
		global $settings; global $username; global $action; global $xml; 

		$viewopts['text'] = "Text view";
		
		// Add the sattribute levels
		if ( !$settings['views'] ) foreach ( $settings['xmlfile']['sattributes'] as $key => $item ) {	
			$lvl = $item['level'];	
			if ( strstr($this->rawtext, "<$key ") ) {
				$lvltxt = $item['display'] or $lvltxt = "Sentence";
				$viewopts['block:'.$lvl] = "{$lvltxt} view";
			}; 
		}; 

		foreach ( $settings['views'] as $key => $item ) {	
			# Check whether we should do this
			$dothis = 1;
			if ( $item['xprest'] ) {
				$tmp = $this->xml->xpath($item['xprest']);
				if ( $tmp ) $dothis = 0;
			};
			if ( $item['xpcond'] ) {
				$tmp = $this->xml->xpath($item['xpcond']);
				if ( !$tmp ) $dothis = 0;
			};
			if ( $item['filerest'] ||  $item['filecond'] ) {
				$filerest = $item['filerest'];
				$filerest = preg_replace("/\[fn\]/", $this->filename, $filerest);
				$filerest = preg_replace("/\[id\]/", $this->xmlid, $filerest);
			};
			if ( $item['filecond'] && !file_exists($filerest) ) $dothis = 0;
			if ( $item['filerest'] && file_exists($filerest) ) $dothis = 0;
			if ( $dothis ) { // View condition
				$lvltxt = $item['display'];
				$viewopts[$key] = $lvltxt;
			}; 
		}; 
		// Add the annotation levels
		if ( $settings['annotations'] ) {
			foreach ( $settings['annotations'] as $key => $val ) {
				if ( $val['type'] == "standoff" &&  ( !$val['admin'] || $username ) ) {
					if ( file_exists("Annotations/{$key}_$this->xmlid.xml") ) $viewopts['annotation'] = $val['display'];
					else $viewopts['annotation'] = "Create ".$val['display'];
				} else if ( $val['type'] == "psdx" && file_exists("Annotations/$this->xmlid.psdx") ) {
					$viewopts['psdx'] = $val['display'];
				} else if ( $val['type'] == "m" && strstr($this->rawtext, "<m ") ) {
					$viewopts['igt'] = $val['display'];
				};
			}; 
		}; 
		
		// TODO: Check that this does not get too slow
		if ( !$settings['views'] && $this->xml->xpath("//lb[@bbox]") ) {
			$lvltxt = $settings['views']['lineview']['display'] or $lvltxt = "Manuscript line";
			$viewopts['lineview'] = "{$lvltxt} view";
		};
		if ( !$settings['views'] && $this->xml->xpath("//tok[@bbox]") ) {
			$lvltxt = $settings['views']['facsview']['display'] or $lvltxt = "Facsimile";
			$viewopts['facsview'] = "{$lvltxt} view";
		};
		
		if ( $initial."" == "select" ) {
				$views = "<option value='' disabled selected>[{%select}]</option>";
		};
			
		$jmp = $_GET['jmp'] or $jmp = $_GET['tid'];
		$sep = ""; if ( !$initial ) $sep = " &bull; ";
		foreach ( $viewopts as $key => $val ) {
			list ( $doaction, $dolvl ) = explode ( ":", $key );
			if ( $action != $doaction || ($dolvl && $dolvl != $_GET['elm']) ) {
				if ( $initial."" == "select" ) {
					$views .= $sep."<option value='index.php?action=$doaction&cid=$this->fileid&pageid={$_GET['pageid']}&jmp=$jmp&elm=$dolvl'>{%$val}</option>";
					$sep = "\n";
				} else {
					$views .= $sep."<a href='index.php?action=$doaction&cid=$this->fileid&pageid={$_GET['pageid']}&jmp=$jmp&elm=$dolvl'>{%$val}</a>";
					$sep = " &bull; ";
				};
			};
		};

		return $views;
	}

	function makeedit() {
		# Provide the JS code to use tokedit and tokview with mtxt

		$editblock = "<div id='mtxt'>".$this->asXML()."</div>";

		return $editblock;
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
	
	// Functions for assigning pseudonyms to <anon> elements
	var $pseudo = array ( ); var $pseudodone = array(); var $caserules = array ();
	function pseudo() {
		global $settings;
		if ( $settings['anonymization'] ) {
			foreach ( $settings['anonymization']['values'] as $key => $val ) $this->pseudo[$key] = explode(",", $val['vals']); 
			foreach ( $settings['anonymization']['caserules'] as $key => $val ) $this->caserules[$key] = $val; 
		};
		foreach ( $this->xml->xpath("//anon") as $anon ) {
			$deanon = $this->deanon($anon);
		};
	}

	function deanon( $anon ) {
		global $settings;
		$full = $anon['type']."-".$anon['subtype'];
		$name = $anon['type'];
		if ( !$deanon ) {
			$n = $anon['n'];
			if ( $n && $this->pseudodone[$full.$n] ) {
				list ( $deanon, $name ) = $this->pseudodone[$full.$n];
			} else {
				$options = array ( $anon['type']."-".$anon['subtype'],  $anon['type'] );
				while ( !$deanon && $options ) {
					$option = array_pop($options)."";
					if ( is_array($this->pseudo[$option]) ) {
						$deanon = array_pop($this->pseudo[$option]);
						$name = $settings['anonymization']['values']["$option"]['display'];
					};
				};
			};
			if ( $deanon ) {
				$this->pseudodone[$full.$n] = array ( $deanon, $name );
				$case = $anon['case']."";
				if ( $this->caserules[$case] ) {
					$from = $this->caserules[$case]['from'] or $from = '$';
					$deanon = preg_replace("/$from/", $this->caserules[$case ]['to'], $deanon);
				};
			};
		};
		if ( !$deanon ) {
			$deanon = ucfirst($anon['type']);
			if ( $anon['n'] ) $deanon .= " ".$anon['n'];
		};
		if ( !$deanon ) $deanon = "Anon";
		$anon[0] = $deanon;
		$anon['title'] = "{%anonymized} {%$name}";
		
		return $deanon;
	}	
	
};

?>