<?php

# A Class to read a TEITOK style tagset file
# (c) Maarten Janssen, 2015

class TTTAGS
{	
	public $xml; # The xml of the tagset
	public $tagset; # The array version of the tagset
	public $username; # Username

	function __construct($filename = "xxxxxx", $fatal = 1) {	
		global $lang, $sharedfolder, $settings;
		if ( !$sharedfolder ) $sharedfolder = "xxxxx";
		if ( !$filename) $filename = "xxxxxxxx";
		$settingstagset = getset('tagset') or $settingstagset = "xxxxxxx";
		$attempts = array (
			$filename, "Resources/$filename",  "$sharedfolder/Resources/$filename", 
			$settingstagset, "Resources/$settingstagset",  
			"$sharedfolder/Resources/$settingstagset", 
			"Resources/tagset-$lang.xml", "Resources/tagset.xml",
			"$sharedfolder/Resources/tagset-$lang.xml", "$sharedfolder/Resources/tagset.xml",			
		);
		foreach ( $attempts as $attempt ) {
			if ( file_exists($attempt) ) { $filename = $attempt; break; };
		};
		
		if ( $filename ) {
			$this->xml = simplexml_load_file($filename);
			$this->tagset = xmlflatten($this->xml);
		};
		
		if ( $fatal && !$this->tagset ) { fatal("Failed to load tagset"); };
	}
	
	function analyse ( $tag ) {
		$pos1 = substr($tag,0,1);
		$tagoptions = $this->tagset['positions'][$pos1];
		$array = array();
		$array[0] = array ("name" => "Main type", "value" => $pos1, "text" => $tagoptions['display'], "display" => $tagoptions['display'] );
		for ( $i=1; $i<strlen($tag); $i++ ) {
			$posx = substr($tag,$i,1);
			$array[$i] = array ( "name" => $tagoptions[$i]['display'], "value" => $posx, "text" => $key2val, "display" => $tagoptions[$i][$posx]['display'] );
		};
		return $array;
	}

	function table ( $tag ) {
		global $lang; # Use a language when defined
		global $username; # Check for login
		$pos1 = substr($tag,0,1);
		$tagoptions = $this->tagset['positions'][$pos1];
		$table = "<h2>{%Tag}: $tag</h2>
		<table cellpadding='5px'>";
		$tagname = $tagoptions['lang-'.$lang] or $tagname = $tagoptions['display'];
		$table .= "<tr><td>$pos1<th>{%Main POS}<td>$tagname</h2>";
		for ( $i=1; $i<strlen($tag); $i++ ) {
			$posx = substr($tag,$i,1);
			if ($tagoptions[$i]['lang-'.$lang]) $key1val = "{%{$tagoptions[$i]['lang-'.$lang]}}"; 
				else if ($tagoptions[$i]['display']) $key1val = "{%{$tagoptions[$i]['display']}}"; 
				else $key1val = "<span style='color: #aaaaaa'><i>{%does not apply}</i></span>";
			if ( !$tagoptions[$i][$posx] && $username ) $warnings .= "<p>Invalid value for $pos1 position $i: $posx";
			if ($tagoptions[$i][$posx]['lang-'.$lang]) $key2val = "{%{$tagoptions[$i][$posx]['lang-'.$lang]}}"; 
				else if ($tagoptions[$i][$posx]['display']) $key2val = "{%{$tagoptions[$i][$posx]['display']}}"; 
				else $key2val = "<span style='color: #aaaaaa'><i>{%does not apply}</i></span>";
			$thdr = $tagoptions[$i]['display'] or $thdr = "(unknown)";
			$table .= "<tr><td>$posx<th>{%$thdr}<td>$key2val</h2>";
		};
		$table .= "</table>";
		
		return $table;
	}
	
	function taglist () {
		global $lang;
		$optionarray = array ();
		if ( is_array($this->tagset) && is_array($this->tagset['positions']) ) 
		  foreach ( $this->tagset['positions'] as $key => $val ) {
			if ( $val['multi'] ) {
				foreach ( $val['multi'] as $key2 => $val2 ) {
					$pname = $val2['display-'.$lang] or $pname = "{%".$val2['display']."}";
					$optionarray[$key2] = $pname;
				};
			} else {
				$pname = $val['display-'.$lang] or $pname = "{%".$val['display']."}";
				$optionarray[$key] = $pname;
			};
		};
		asort($optionarray);
		return $optionarray;
	}
	
};

?>
		
