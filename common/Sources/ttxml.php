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
	public $header; # The teiHeader
	public $audio = array(); # An array with the audio file(s) in this XML
	public $audiourl; # The URL for the first AUDIO node
	public $video = array(); # An array with the video file(s) in this XML
	public $videourl; # The URL for the first AUDIO node
	public $nospace; # Not space-sensitive
	public $warning = array(); # Warnings about the text
	public $noparse; # flag to not parse the entire XML
	
	var $xmlfolder;
	var $rawtext;
	var $title;
	
	public $pagnav; # The page navigation bar
	public $facsimg; # The facsimile image for the page

	function __construct($fileid = "", $fatal = 1, $options = "" ) {	
		global $xmlfolder; global $baseurl; global $settings; global $username;
		
		if ( !isset($this->noparse) ) 
			if ( $settings['defaults'] && $settings['defaults']['noparse'] ) $this->noparse = true;
			else $this->noparse = false;
		
		# Use $_GET to find the file
		if ( !$xmlfolder ) $xmlfolder = "xmlfiles";
		if ( strstr($options, "pagetrans") != false ) {  $xmlfolder = "pagetrans"; };
		
		if (!$fileid) $fileid = $_POST['cid'] or $fileid = $_POST['id'] or $fileid = $_GET['cid'] or $fileid = $_GET['id'];
		if ( !preg_match("/\.xml/", $fileid) && $fileid != "" ) $fileid .= ".xml";
		$this->fileid = $fileid;
		$oid = $fileid;
		
		if ( !$this->fileid  && $fatal ) { 
			fatal ( "No XML file selected." );  
		};

		if ( !file_exists("$xmlfolder/".$this->fileid) && substr($this->fileid,-4) != ".xml" ) { 
			$fileid .= ".xml";
			$this->fileid .= ".xml";
		};
		if ( !file_exists("$xmlfolder/".$this->fileid) && substr($this->fileid,0,9) == "xmlfiles/" ) { 
			$fileid = substr($fileid, 9);
			$this->fileid = substr($this->fileid, 9);
		};
	
		if ( !file_exists("$xmlfolder/$fileid") ) {
			if ( $settings['xmlfile']['fullpath'] ) {
				if ( $fatal ) fatal("No such XML File: $this->fileid"); 
				else return -1;
			};
			# Search for the file, unless told to only use direct path
			$fileid = preg_replace("/^.*\//", "", $fileid);
			$test = array_merge(glob("$xmlfolder/**/$fileid")); 
			if ( !$test ) 
				$test = array_merge(glob("$xmlfolder/$fileid"), glob("$xmlfolder/*/$fileid"), glob("$xmlfolder/*/*/$fileid"), glob("$xmlfolder/*/*/*/$fileid")); 
			$temp = array_pop($test); 
			$fileid = preg_replace("/^".preg_quote($xmlfolder, '/')."\/?/", "", $temp);
	
			if ( $fileid == "" && $fatal ) {
				fatal("No such XML File: {$fileid}"); 
			};
			$this->fileid = $fileid;
		};

		if ( !file_exists("$xmlfolder/".$this->fileid) && substr($this->fileid,-4) != ".xml" ) { 
			$this->fileid .= ".xml";
		};

		$this->filename = preg_replace ( "/.*\//", "", $fileid );
		$this->xmlid = preg_replace ( "/\.xml/", "", $this->filename );

		# Check whether this XML file is small enough to just load
		if ( !$noparse ) {
			$limit = str_replace(array('G', 'M', 'K'), array('000000000', '000000', '000'), ini_get('memory_limit'));
			$maxsize = $limit / 3; # We can work with XML files taking up that 1/3 of the total PHP memory
			$fullfile = "xmlfiles/".$this->fileid;
			if ( file_exists($fullfile) ) $filesize = filesize($fullfile);
			if ( $filesize > $maxsize ) {
				$noparse = 1;
				fatal("XML file exceeds file size limit");
			};
		};

		if ( !$noparse ) $this->loadxmlfile();
		else $this->loadheader();
		
	}
	
	function title( $type = "" ) {
		global $settings;
		if (!$this->xml) return "";
		if ( !$this->title ) {
			if ( $settings['xmlfile']['title'] == "[id]" ) {
				$this->title = $this->xmlid;
			} else {
				$titlexp = $settings['xmlfile'][$type]."" or $titlexp = $settings['xmlfile']['title']."" or $titlexp = "//title";
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

	function xpath($xpath) {
		if ( !is_object($this->xml) ) return;
		$res = $this->xml->xpath($xpath);
		return $res;
	}
	
	function loadheader() {
		global $ttroot;
		$fullfile = "xmlfiles/".$this->fileid;
		$perlapp = findapp("perl") or $perlapp = "/usr/bin/perl";

		$cmd = "$perlapp $ttroot/common/Scripts/ttxpath.pl --file='$fullfile' --query='/TEI/teiHeader'";
		$string = shell_exec($cmd);
		$header = simplexml_load_string($string);
		$this->header = $header;
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
			<div id='tokinfo' style='display: block; position: absolute; z-index: 3;'></div>
			<script language=Javascript>
				var formdef = $jsonforms;
				var attributelist = Array($attlisttxt);
				var transl = $jsontrans;
				$attnamelist
			</script>
			";
	
		return $header;
	}
	
	function loadxmlfile() {
		global $xmlfolder; global $baseurl; global $settings; global $username;
		$fullfile = "xmlfiles/".$this->fileid;
		$this->rawtext = file_get_contents($fullfile); 

		if ( strstr($options, "keepns") == false ) {
			$this->rawtext = namespacemake($this->rawtext);
		};
		
		# Check if there are no non-TEITOK things in the XML
		if (
			strpos($this->rawtext, 'facs="#') !== false
			||
			strpos($this->rawtext, 'start="#') !== false
		) {
			array_push($this->warning, "The XML does not conform to TEITOK standards. Consider converting it.");
		};
		if ( $username && !is_writable("$xmlfolder/$fileid") ) {
			$warning = "Due to filepermissions, this file cannot be modified by TEITOK - please contact the administrator of the server.";
			array_push($this->warning, $warning);
		};
			
		libxml_use_internal_errors(true);
		if ( $settings['xmlfile']['nospace'] || preg_match("/<text[^>]+xml:space=\"remove\"/", $this->rawtext) ) {
			$this->xml = simplexml_load_string($this->rawtext, null, LIBXML_NOBLANKS);
			$this->nospace = $settings['xmlfile']['nospace'];
			if ( $this->nospace ) 
			if ( preg_match("/join=\"right\"/", $this->rawtext) ) {
				$this->nospace = 2;
			} else if ( preg_match("/join=\"left\"/", $this->rawtext) ) {
				$this->nospace = 3;
			} else {
				$this->nospace = 1;
			};
		} else {
			$this->xml = simplexml_load_string($this->rawtext);
		};
		if ( !$this->xml && $fatal ) { 
			if ( $username ) {
				$ermsg = "<h2>Incorrect XML</h2><p>Failing to read/parse $fileid
				<hr><p>Since the XML cannot be opened, the error cannot be corrected within the interface. The
					error message is displayed below. You can either edit the XML directly on the server if you have 
					access to it, or revert to a <a href='index.php?action=backups&cid=$this->fileid'>backup</a>
					if there is one.
				<hr>";
				$rawarr = explode("\n", $this->rawtext);
				foreach(libxml_get_errors() as $error) {
					$tmp = $rawarr[$error->line-1];
					$linetxt = "<span style='opacity: 0;'>".htmlentities(substr($tmp, 0, $error->column))."</span>"
						."<span style='color:red; font-weight: bold; '>-</span>";
					$ermsg .= "". htmlentities($error->message). "(line $error->line, col $error->column)".
					"<div style='width:800px; overflow-x: scroll;'>
						<pre>".htmlentities($tmp)."</pre>"."<pre style='margin-top: -17px; line-height: 5px;'>$linetxt</pre>
					</div><hr>";
				}
				$time = time();
				if (!is_dir("tmp")) mkdir("tmp"); 
				file_put_contents("tmp/error_$time.txt", $ermsg);
				print "<h1>Fatal Error</h1><p>A fatal error has occurred
					<script language=Javascript>top.location='index.php?action=error&msg=$time';</script>";
				exit;
			} else fatal ( "An error occurred with this file. We apologize for the inconvenience." ); 
		} else if ( !$this->xml ) {
			return;
		};
		
		// Put the teiHeader in a variable
		$this->header = current($this->xml->xpath("/TEI/teiHeader"));
		
		// See if there is an Audio element in the header
		if ( $_GET['media'] ) {
			$mediaxp = "//media[@id=\"{$_GET['media']}\"]";
		} else {
			$mediaxp = "//recording//media";
		};
		$mediabaseurl =  $settings['defaults']['media']['baseurl'] or $mediabaseurl =  $settings['defaults']['base']['media'] or $mediabaseurl = "Audio";
		foreach ( $this->xml->xpath($mediaxp) as $medianode ) {
			$mimetype = $medianode['mimeType'] or $mimetype = $medianode['mimetype'] or $mimetype = mime_content_type($medianode['url']."");
			list ( $mtype, $mform ) = explode ( '/', $mimetype );
			if ( $mtype == "video" ) {
				array_push($this->video, $medianode);
				if ( $videourl == "" ) $videourl = $medianode['url']; 
			} else { // If it ain't video, it's audio
				array_push($this->audio, $medianode);
				if ( $audiourl == "" ) $audiourl = $medianode['url']; 
			};
		}; 
		if ( $audiourl != "" ) {
			if ( !strstr($audiourl, 'http') ) {
				if ( file_exists($audiourl) ) $audiourl =  "$baseurl/$audiourl"; 
				else $audiourl =  "$mediabaseurl/$audiourl";
				# else if ( !strstr($audiourl, 'Audio') ) $audiourl = $baseurl."Audio/$audiourl"; 
			};
			$this->audiourl = $audiourl;
		};		
		if ( $videourl != "" ) {
			if ( !strstr($videourl, 'http') ) {
				if ( file_exists($videourl) ) $videourl =  "$baseurl/$videourl"; 
				else if ( file_exists("Video/$videourl") ) $videourl =  "Video/$videourl"; 
				else $videourl =  "$mediabaseurl/$videourl!!"; 
				# else if ( !strstr($videourl, 'Video') ) $videourl = $baseurl."Video/$videourl"; 
			};
			$this->videourl = $videourl;
		};		
		
		// If we have pseudonimization rules, pseudonimize the text
		if ( $settings['anonymization'] && !$settings['anonymization']['manual'] ) {
			$this->pseudo();
		};	
			
	}
	
	function tableheader( $tpl = "", $showbottom = true ) {
		global $username; global $settings; global $lang; global $popup;
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
				if ( $val['xpath'] ) {
					$xval = getxpval($this->xml, $val['xpath']);
				} else if ( $val['value'] ) {
					$xval = $val['value'];
					if ( strpos(" ".$xval, "{#") != false ) {
						$xval = xpathrun($xval, $this->xml);
					};
				};
				if ( $xval && ( !$val['admin'] || $username ) ) {
					if ( $popup && $val['nopopup'] ) continue;
					if ( in_array($tpl, explode(",", $val['show'])) || ( !$val['show'] && $tpl == "long" ) ) {
						if ( $val['lang'] && $val['lang'] != $lang ) continue;
						if ( $settings['teiheader'][$key]['type'] == "xml" ) $hval = $xval->asXML();
						else if ( preg_match("/@[^\/]+$/", $val['xpath']) ) $hval = "".$xval;
						else if ( is_object($xval) ) $hval = preg_replace( "/^<[^>]+>|<[^>]+>$/", "", $xval->asXML());
						else $hval = $xval;
						// Link when so asked
						if ( $val['link'] && $hval ) {
							if ( strpos($val['link'], "http") != false ) $tmp = $val['link'];
							else if ( $val['link'] == "self" ) {
								$tmp = $hval;
							} else if ( strpos($val['link'], "{#") != false ) {
								$tmp = xpathrun($val['link'], $this->xml);
								if ( $tmp == preg_replace("/{#[^{}]*}/", "", $val['link']) ) $tmp = "";
							} else $tmp = getxpval($this->xml, $val['link']);
							if ( $tmp ) 
								if ( $val['docinfo'] ) {
									$hval = "<a href='$tmp' cid=\"$hval\"  onmouseover=\"showdocinfo(this)\" onmouseout=\"hidetokinfo()\">$hval</a>";
								} else $hval = "<a href='$tmp'>$hval</a>";
						};
						if ( $settings['teiheader'][$key]['options'][$hval]['display'] ) 
							$hval = $settings['teiheader'][$key]['options'][$hval]['display'];
						if ( $settings['teiheader'][$key]['i18n'] ) 
							$hval = "{%$hval}";
						if ($hval != "" && $hval != "{%}") $tableheader .= "<tr><th>{%$disp}<td>$hval";
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
			if ( $username  ) {
				$moreopts .= " $sep <a href='index.php?action=fileadmin&cid=$this->fileid' class=adminpart>file admin</a>";
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
	
	function mfoliotxt($folioname, $elm) {
		if ( substr($folioname,0,1) == "@" ) {
			$attname = substr($folioname,1);
			$attval = $elm[$attname];
			if ( $attval ) return $attval;
			else return "";
		};
		return $folioname;
	}
	
	function asXML( $whole = false ) {
		global $mtxtelement; global $settings; global $username; global $ttroot;
		
		if ( $_GET['page'] == "all" ) $whole = 1;
		
		if ( $this->noparse ) {
		
			$jmp =  $_GET['jmp'] or $jmp = $_GET['tid'];
			$jmp = preg_replace("/ .*/", "", $jmp);
			$pbelm = $_GET['pbelm'] or $pbelm = "page";
			$fileid = "xmlfiles/$this->fileid";

			if ( $jmp ) $cql = "Matches = [id=\"$jmp\"] :: match.text_id=\"$fileid\"";
			else $cql = "Matches = <text> [] :: match.text_id=\"$fileid\"";
			
			require("$ttroot/common/Sources/cwcqp.php");
			$cqpcorpus = strtoupper($settings['cqp']['corpus'].$subcorpus); # a CQP corpus name ALWAYS is in all-caps
			$cqpfolder = "cqp";
			$cqp = new CQP();
			$cqp->exec($cqpcorpus); // Select the corpus
			$cqp->exec("set PrettyPrint off");
			$cqp->exec($cql);
			$tmp = $cqp->exec("tabulate Matches match, match text_id, match {$pbelm}_id");
			list ( $leftpos, $textid, $pageid ) = explode("\t", $tmp );
			$rightpos = $leftpos;
			
			$xidxcmd = findapp('tt-cwb-xidx'); 
			$expand = " --expand='$pbelm'";
			$cmd = "$xidxcmd --cqp='$cqpfolder' --filename='$fileid' $expand $leftpos $rightpos";
			$xmltxt = shell_exec($cmd);
			
			if ( $xmltxt != "" ) {
				return $xmltxt;
			} else if ( $username ) {
				$maintext .= "<p>Failed - no results for $cmd"; exit;
			};
		};

		if ( getset('xmlfile/restriction') && !$this->xml->xpath($settings['xmlfile']['restriction']) && !$username ) { 
			$tokid = $_GET['jmp'] or $tokid = $_GET['tid'] or $tokid = 'w-1';
			# Take only the first one
			$tokid = preg_replace("/ .*/", "", $tokid);
			$xmltxt = $this->context($tokid);
			$this->pagenav = "<p>{%Due to copyright restrictions, only a fragment of this text is displayed}</p><hr>"; 
		} else if ( !$whole && ( getset('xmlfile/paged/type') == "xp" || $_GET['pagetype'] == "xp" ) ) {
			$xmltxt = $this->xppage();
		} else if ( !$whole && ( $_GET['paged'] 
				|| ( is_array($settings['xmlfile']) && ( $settings['xmlfile']['paged'] || is_array($settings['xmlfile']['paged']) ) )
				|| $_GET['pbtype'] 
				) ) {
			$xmltxt = $this->page();
		} else {
			# Not restricted - just display the whole XML
			if ( !$mtxtelement ) $mtxtelement = "//text";
			$result = $this->xml->xpath($mtxtelement);
			if ($result) {
				if ( $result[0]->getName() == "text" && $result[0]['title'] ) unset($result[0]['title']);
				$xmltxt = $result[0]->asXML();
			} else {
				$xmltxt = "($mtxtelement not found)";
			};
		};
						
		# Protect empty elements
		$xmltxt = preg_replace( "/<([^> ]+)([^>]*)\/>/", "<\\1\\2></\\1>", $xmltxt );
		
		# Add spaces in case of @join type spacing
		# TODO: regexp is not the safest for this
		if  ( $this->nospace == 2 ) {
			$xmltxt = str_replace( "</tok>", "</tok><njs> </njs>", $xmltxt );
			$xmltxt = preg_replace( "/(join=\"right\"((?!<tok).)+<\/tok>)<njs> <\/njs>/", "\\1", $xmltxt );
		} elseif  ( $this->nospace == 3 ) {
 			$xmltxt = str_replace( "<tok ", "<njs> </njs><tok ", $xmltxt );
 			$xmltxt = preg_replace( "/<njs> <\/njs>(<tok(.(?!<\/tok))+join=\"left\")/", "\\1", $xmltxt );
		};
		
		return $xmltxt;
	}

	function toklist ( $elm ) {
		$toklist = array();
		foreach ( $elm->xpath(".//tok[not(dtok)] | //dtok") as $tok ) {
			array_push($toklist, $tok);
		};
		return $toklist;
	}
		
	function elm2id ( $elm ) {
		global $settings;
		$att = getset('xmlfile/paged/att', "n");
		$elmid = $elm[$att] or $elmid = $elm['id'];
		if ( getset('xmlfile/paged/seqnum') ) $elmid = preg_replace("/.*-/", "", $elmid);
		return $elmid;
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
		$befnum = $this->elm2id($page);
		$aftnum = $befnum;
		if ( !$page ) {
			if ( getset('xmlfile/paged/hard') ) fatal("No such page: $xp");
			else $page = $this->xml;
		};
		
		$befpag = array_reverse($page->xpath("preceding-sibling::$pbelm"));
		$aftpag = $page->xpath("following-sibling::$pbelm");
		$max = getset('xmlfile/paged/multi', 0);

		$pagedxml = $page->asXML(); 
		$bp = min($max, count($befpag));
		for ( $i = 0; $i < $bp; $i++ ) {
			$thispage = $befpag[$i];
			if ($thispage) {
				$pagedxml = $thispage->asXML() . $pagedxml;
				$befnum = $this->elm2id($thispage);
			};
		}; 
		$ap = min($max, count($aftpag));
		for ( $i = 0; $i < $ap; $i++ ) {
			$thispage = $aftpag[$i];
			if ($thispage) {
				$pagedxml = $pagedxml . $thispage->asXML();
				$aftnum = $this->elm2id($thispage);
			};
		}; 
		
		if ( $befnum == $aftnum ) $num = $befnum; else $num = "$befnum - $aftnum";
		
		$folioname = getset('xmlfile/paged/display', "page");
		if ( getset('xmlfile/paged/i18n') ) $folioname = "{%$folioname}";

		if ( $action == "text" || $action == "file" || $settings['xmlfile']['paged']['index'] ) $bnav = "<a href='index.php?action=pages&cid=$this->fileid$pbsel'>{%index}</a>";
		if ( $befpag[$bp] ) {
			$tmp = min(count($befpag)-1, $bp+$max*2); $npag1 = $befpag[$tmp]; $bid = $idxpag['id'];
			$bnum1 = $this->elm2id($npag1);
			$npag2 = $befpag[$bp]; 
			$bnum2 = $this->elm2id($npag2);
			if ( $npag1 == $npag2 ) $bnum = $bnum2; else $bnum = "$bnum1 - $bnum2";
			$tmp = min(count($befpag)-1, $bp+$max); $idxpag = $befpag[$tmp]; $bid = $idxpag['id'];
			if ( $bnav ) $bnav .= " &bull; ";
			$bnav .= "<a href='index.php?action=$action&cid=$this->xmlid&pageid=$bid'>".$this->mfoliotxt($folioname, $npag1)." $bnum</a> <";
			$hasnav = 1;
		};
		if ( $aftpag[$ap] ) {
			$tmp = min(count($aftpag)-1, $ap+$max*2); $npag1 = $aftpag[$tmp]; $bid = $idxpag['id'];
			$bnum1 = $this->elm2id($npag1);
			$npag2 = $aftpag[$ap]; 
			$bnum2 = $this->elm2id($npag2);
			if ( $npag1 == $npag2 ) $bnum = $bnum2; else $bnum = "$bnum2 - $bnum1";
			$tmp = min(count($aftpag)-1, $ap+$max); $idxpag = $aftpag[$tmp]; $bid = $idxpag['id'];
			$nnav = "> <a href='index.php?action=$action&cid=$this->xmlid&pageid=$bid'>".$this->mfoliotxt($folioname, $npag1)." $bnum</a>";
			$hasnav = 1;
		};

		$foliotxt = $this->mfoliotxt($folioname, $page)." ".$num;

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

		if ( $page && getset('xmlfile/paged/header') ) {
			$pageinfo = "<center><table>";
			foreach ( $settings['xmlfile']['paged']['header'] as $kk => $vv ) {
				$vval = $page[$kk];
				if ( $vv['type'] == "url" ) { 	
					$vname = $vv['name'] or $vname = $vval; 
					if ( $vv['url'] ) { 
						$vurl = str_replace('%%', $vval, $vv['url']); 
					} else $vurl = $vval;
					$vval = "<a href='$vurl' target='details'>$vname</a>"; 
				};
				if ( $vval ) $pageinfo .= "<tr><th>{$vv['display']}</th><td>$vval</td></tr>";
			};
			$pageinfo .= "</table></center>";
		};

		# Build the page navigation
		if ( $hasnav ) $this->pagenav = "
						$tocnav
						<table style='width: 100%'><tr> 
						<td style='width: 33%' align=left>$bnav
						<td style='width: 33%' align=center>$foliotxt $folionr
						<td style='width: 33%' align=right>$nnav
						</table>
						$pageinfo
						<hr> 
						";
	
		return $pagedxml;
	}
	
	function mtxt($editable=1) {
		global $username, $settings; global $jsurl;
		if ( $editable ) { 
			$moreactions .= "\n\tvar username='$username'; ";
		};	
		
		$jsonforms = array2json(getset('xmlfile/pattributes/forms'));
		#Build the view options	
		foreach ( getset('xmlfile/pattributes/forms', array()) as $key => $item ) {
			$attlisttxt .= $alsep."\"$key\""; $alsep = ",";
			$attnamelist .= "\nattributenames['$key'] = \"{%".$item['display']."}\"; ";
		};
		foreach ( getset('xmlfile/pattributes/tags', array()) as $key => $item ) {
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
	
	function page ( $pagid = "", $opts = "" ) {
		global $action; global $settings; global $pbtype;
		
		# This gives an error if there is no XML
		$editxml = $this->xml->asXML();
		$tmp = $this->xml->xpath($xp);
		if ( $tmp ) $page = current($tmp); 

		# Return the xml for a page (or other element) of the text
		
		// Determine what element to use
		# if ( $settings['xmlfile']['paged'] == 2) $me = "you";
		
		if ( !is_array($opts) ) $opts = array();
		
		if ( $opts['pbtype'] || $opts['elm'] ) $pbtmp = $opts['pbtype'] or $pbtmp = $opts['elm'];
		else if ( $_GET['action'] == "appalign" ) $pbtmp = $_GET['pbtype'] or $pbtmp = $settings['appid']['baseview'];
		else if ( $_GET['pbtype'] ) $pbtmp = $_GET['pbtype'];
		else if ( is_array($settings['xmlfile']['paged']) ) $pbtmp = $settings['xmlfile']['paged']['element'];
		else $pbtmp = "pb";
		
		// Determine kind of page to cut out
		$pbatt = "n";
		if ( $opts['elm'] ) { // Explicit element
			$pbelm = $opts['elm'];
			$titelm = $opts['elmname'] or $titelm = $pbelm;
		} else if ( $action == "pagetrans" ) { // Page
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
			$pbtype = $settings['xmlfile']['paged']['element'] or $pbtype = $pbtmp;
			$titelm = $settings['xmlfile']['paged']['display'] or $titelm = ucfirst($pbtmp);
			$pbelm = $pbtype;
			$pbatt = $settings['xmlfile']['paged']['att'] or $pbatt = "n";
		} else {  // Generic milestone
			$pbtype = "milestone[@type=\"{$pbtmp}\"]";
			$titelm = getset("xmlfile/paged/options/$pbtmp/display", ucfirst($pbtmp));
			$pbelm = "milestone";
			$pbsel = "&pbtype={$pbtmp}";
		};
	
		if ( !$pagid ) $pagid = $_GET['pageid'];
		if ( !$tid ) $tid = $_GET['tid'] or $tid = $_GET['jmp'];
		$tid = preg_replace("/ .*/", "", $tid);
		$appid = $_GET['appid'];

		$selid = $pagid or $selid = $tid;
		if ( $selid ) {
			$tokidx = strpos($editxml, " id=\"$selid\"");
			$pb = "<$pbelm";
			$pidx = rstrpos($editxml, $pb, $tokidx);
		} else if ( $_GET['appid'] ) {
			$tokidx = strpos($editxml, " appid=\"{$_GET['appid']}\"");
			$pb = "<$pbelm";
			$pidx = rstrpos($editxml, $pb, $tokidx);
		} else {
			// get the first page
			$pb = "<$pbelm ";
			$pidx = strpos($editxml, $pb);
			$tmp = substr($editxml, $pidx, strpos($editxml, ">", $pidx)-$pidx+1);
			# If the first page is empty, jump over it
			while ( $pidx  && strpos($tmp, "empty") && $cnt++ < 10 ) {
				$pidx = strpos($editxml, $pb, $pidx+1);
				$tmp = substr($editxml, $pidx, strpos($editxml, ">", $pidx)-$pidx+1);
			};
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
			if ( $pagid && $fatal ) 
				fatal ("No such $pbelm in XML: $pagid"); 
			else {
				# There are no such elements in the document
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
			$tmp = substr($editxml, $nidx, $nidy-$nidx+1 ); 
			 
			if ( preg_match("/id=\"(.*?)\"/", $tmp, $matches ) ) { $npid = $matches[1]; };
			if ( preg_match("/$pbatt=\"(.*?)\"/", $tmp, $matches ) ) { $npag = $matches[1]; };
			
			if ( $npid ) $nnav = "<a id=nextpag href='index.php?action=$action&cid=$this->fileid&pageid=$npid&pbtype=$pbtype'>> $npag</a>";
			else $nnav = "<a id=nextpag href='index.php?action=$action&cid=$this->fileid&pageid=$npag'>> $npag</a>";
		};
		
		# Find the previous page/chapter (for navigation)
		$bidx = rstrpos($editxml, "<$pbelm ", $pidx-1); 
		if ( !$bidx || $bidx == -1 ) { 
			$bidx = strpos($editxml, "<text", 0); 
			if ( $action == "text" || $action == "file" || ( is_array($settings['xmlfile']['paged']) && $settings['xmlfile']['paged']['index'] ) ) $bnav = "<a href='index.php?action=pages&cid=$this->fileid$pbsel'>{%index}</a>";
		} else {
			$tmp = substr($editxml, $bidx, 150 ); 
			if ( preg_match("/id=\"(.*?)\"/", $tmp, $matches ) ) { $bpid = $matches[1]; };
			if ( preg_match("/$pbatt=\"(.*?)\"/", $tmp, $matches ) ) { $bpag = $matches[1]; } else { $bpag = ""; };
			if ( $bpid  )  $bnav = "<a href='index.php?action=$action&cid=$this->fileid&pageid=$bpid$pbsel'>$bpag <</a> ";
			else $bnav = "<a id=prevpag href='index.php?action=$action&cid=$this->fileid&page=$bpag'>$bpag <</a>";
			if ( !$firstpage && ( $action == "text" || $action == "file" ) ) { $bnav = "<a href='index.php?action=pages&cid=$this->fileid$pbsel'>{%index}</a> &nbsp; $bnav"; };
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

		$pidy = strpos($editxml, ">", $pidx); 
		$tmp = substr($editxml, $pidx, $pidy-$pidx+1 ); 
		 
		if ( preg_match("/id=\"(.*?)\"/", $tmp, $matches ) ) { $pageid = $matches[1]; };
		if ( preg_match("/$pbatt=\"(.*?)\"/", $tmp, $matches ) ) { $folionr = $matches[1]; };
		if ( preg_match("/facs=\"(?!#)(.*?)\"/", $tmp, $matches ) ) {
			$img = $matches[1];
			if ( !preg_match("/^(http|\/)/", $img) ) $img = "Facsimile/$img";
		};
		
		$span = $nidx-$pidx;
		$editxml = $facspb.substr($editxml, $pidx, $span); 

		$editxml = preg_replace("/<lb([^>]+)\/>/", "<lb\\1></lb>", $editxml);
		
		$this->facsimg = $img;
		
		if ( $titelm ) $foliotxt = $titelm;
		else if ( $settings['xmlfile']['paged']['display'] ) $foliotxt = $settings['xmlfile']['paged']['display'];
		else if ( $pbelm == "pb" ) $foliotxt = "Folio";
		if ( is_array($settings['xmlfile']['paged']) && $settings['xmlfile']['paged']['i18n'] ) $foliotxt = "{%$foliotxt}";

		if ( $page && is_array($settings['xmlfile']['paged']) &&  $settings['xmlfile']['paged']['header'] ) {
			$pageinfo = "<center><table>";
			foreach ( $settings['xmlfile']['paged']['header'] as $kk => $vv ) {
				$vval = $page[$kk];
				if ( $vv['type'] == "url" ) { 	
					$vname = $vv['name'] or $vname = $vval; 
					if ( $vv['url'] ) { 
						$vurl = str_replace('%%', $vval, $vv['url']); 
					} else $vurl = $vval;
					$vval = "<a href='$vurl' target='details'>$vname</a>"; 
				};
				if ( $vval ) $pageinfo .= "<tr><th>{$vv['display']}</th><td>$vval</td></tr>";
			};
			$pageinfo .= "</table></center>";
		};
				
		# Build the page navigation
		$this->pagenav = "<table style='width: 100%'><tr> <!-- /<$pbelm [^>]*id=\"$pagid\"[^>]*n=\"(.*?)\"/ -->
						<td style='width: 33%' align=left>$bnav
						<td style='width: 33%' align=center>$foliotxt $folionr
						<td style='width: 33%' align=right>$nnav
						</table>
						$pageinfo
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
		global $settings; global $username; global $action;  
		if ( !$this->xml ) return false;

		if ( !$viewopts['text'] ) $viewopts['text'] = "Text view"; // Unless otherwise defined, always use Text view
		
		// Add the sattribute levels
		if ( !$settings['views'] ) foreach ( $settings['xmlfile']['sattributes'] as $key => $item ) {	
			$lvl = $item['level'];	
			if ( strstr($this->rawtext, "<$key ") ) {
				$lvltxt = $item['display'] or $lvltxt = "Sentence";
				$viewopts['block:'.$lvl] = "{$lvltxt} view";
			}; 
		}; 
		
		foreach ( $settings['views'] as $key => $item ) {	
			if ( !is_array($item) ) continue;
			
			# Check whether we should do this
			$dothis = 1;
			if ( $item['admin'] && !$username ) { $dothis = 0; };
			
			# TODO: we might want to have functions only with tok/s selected
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
				if (strpos($key, '{#') !== false) $key = xpathrun($key, $this->xml);
				$lvltxt = $item['display'];
				$viewopts[$key] = $lvltxt;
			}; 
		};

		// Add the download link
		if ( $action != "text" && getset('download/always') ) {
			if ( !is_array($settings['download']) || ( ( $settings['download']['admin'] != "1" || $username ) && $settings['download']['disabled'] != "1" ) ) {
				$dltit = "Download XML";
				if ( is_array($settings['download']) && $settings['download']['title'] ) $dltit = $settings['download']['title'];
				$viewopts['getxml:xml'] = $dltit;
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
		if ( !$settings['views'] && $this->xml && $this->xml->xpath("//lb[@bbox]") ) {
			$lvltxt = $settings['views']['lineview']['display'] or $lvltxt = "Manuscript line";
			$viewopts['lineview'] = "{$lvltxt} view";
		};
		if ( !$settings['views'] && $this->xml->xpath("//tok[@bbox]") ) {
			$lvltxt = $settings['views']['facsview']['display'] or $lvltxt = "Facsimile";
			$viewopts['facsview'] = "{$lvltxt}";
		};
		
		if ( $initial."" == "select" ) {
				$views = "<option value='' disabled selected>[{%select}]</option>";
		};

		if ( $username ) $viewopts['fileadmin'] = "<span class='adminpart'>File admin</a>";
			
		$jmp = $_GET['jmp'] or $jmp = $_GET['tid'];
		$jmp = preg_replace("/ .*/", "", $jmp);
		$sep = ""; if ( !$initial ) $sep = " &bull; ";
		foreach ( $viewopts as $key => $val ) {
			list ( $doaction, $dolvl ) = explode ( ":", $key );
			if ( $action != $doaction || ($dolvl && $dolvl != $_GET['elm']) ) {

			# Keep the reference to the selected element in the URL
			$elmref = "";
			if ( $_GET['pageid'] ) $elmref .= "&pageid={$_GET['pageid']}";
			if ( $_GET['sid'] ) $elmref .= "&sid={$_GET['sid']}";
			if ( $jmp ) $elmref .= "&jmp=$jmp";
			if ( $dolvl ) $elmref .= "&elm=$dolvl";
				if ( $initial."" == "select" ) {
					$views .= $sep."<option value='index.php?action=$doaction&cid=$this->fileid$elmref'>{%$val}</option>";
					$sep = "\n";
				} else {
					$views .= $sep."<a href='index.php?action=$doaction&cid=$this->fileid$elmref'>{%$val}</a>";
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

	function viewopts() {
		global $settings, $username, $editxml;
		#Build the view options
		foreach ( $settings['xmlfile']['pattributes']['forms'] as $key => $item ) {
			$formcol = $item['color'];
			# Only show forms that are not admin-only
			if ( $username || !$item['admin'] ) {
				if ( $item['admin'] ) { $bgcol = " border: 2px dotted #992000; "; } else { $bgcol = ""; };
				$ikey = $item['inherit'];
				if ( preg_match("/ $key=/", $editxml) || $item['transliterate'] || ( $item['subtract'] && preg_match("/ $ikey=/", $editxml) ) || $key == "pform" ) { #  || $item['subtract']
					$formbuts .= " <button id='but-$key' onClick=\"setbut(this['id']); setForm('$key')\" style='color: $formcol;$bgcol'>{%".$item['display']."}</button>";
					$fbc++;
				};
				if ( $key != "pform" ) {
					if ( !$item['admin'] || $username ) $attlisttxt .= $alsep."\"$key\""; $alsep = ",";
					$attnamelist .= "\nattributenames['$key'] = \"{%".$item['display']."}\"; ";
				};
			};
		}; 
		foreach ( $settings['xmlfile']['pattributes']['tags'] as $key => $item ) {
			$val = $item['display'];
			if ( preg_match("/ $key=/", $editxml) ) {
				if ( is_array($labarray) && in_array($key, $labarray) ) $bc = "eeeecc"; else $bc = "ffffff";
				if ( !$item['admin'] || $username ) {
					if ( $item['admin'] ) { $bgcol = " border: 2px dotted #992000; "; } else { $bgcol = ""; };
					$attlisttxt .= $alsep."\"$key\""; $alsep = ",";
					$attnamelist .= "\nattributenames['$key'] = \"{%".$item['display']."}\"; ";
					$pcolor = $item['color'];
					$tagstxt .= " <button id='tbt-$key' style='background-color: #$bc; color: $pcolor;$bgcol' onClick=\"toggletag('$key')\">{%$val}</button>";
				};
			} else if ( is_array($labarray) && ($akey = array_search($key, $labarray)) !== false) {
				unset($labarray[$akey]);
			};
		};

		$showform = $_POST['showform'] or $showform = $_GET['showform'] or $showform = 'form';
		if ( $showform == "word" ) $showform = $wordfld;
		$this->showform = $showform;

		# Only show text options if there is more than one form to show
		if ( $fbc > 1 ) {
			$viewopts['view'] .= "<p>{%Text}: $formbuts"; // <button id='but-all' onClick=\"setbut(this['id']); setALL()\">{%Combined}</button>

			$viewopts['show'] .= " - <button id='btn-col' style='background-color: #ffffff;' title='{%color-code form origin}' onClick=\"togglecol();\">{%Colors}</button> ";
			$sep = " - ";
		};

		return $viewopts;
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