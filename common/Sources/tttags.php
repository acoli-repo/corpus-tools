<?php

# A Class to read a TEITOK style tagset file
# (c) Maarten Janssen, 2015

class TTTAGS
{	
	public $xml; # The xml of the tagset
	public $tagset; # The array version of the tagset
	public $username; # Username

	function __construct($filename = "", $fatal = 1) {	
	
		if ( $filename != "" ) {
			$this->xml = simplexml_load_file($filename);
			$this->tagset = xmlflatten($this->xml);
		} else if ( file_exists("Resources/tagset.xml") ) {
			$filename = "Resources/tagset.xml";
			$this->xml = simplexml_load_file($filename);
			$this->tagset = xmlflatten($this->xml);
		} else {
			global $settings; # Use settings when needed
			$this->tagset = $settings['tagset'];
		};
		
	}
	
	function analyse ( $tag ) {
		$pos1 = substr($tag,0,1);
		$tagoptions = $this->tagset['positions'][$pos1];
		$array = array();
		$array[0] = array ("name" => "Main type", "value" => $pos1, "text" => $tagoptions['display'] );
		for ( $i=1; $i<strlen($tag); $i++ ) {
			$posx = substr($tag,$i,1);
			$array[$i] = array ( "name" => $tagoptions[$i]['display'], "value" => $posx, "text" => $key2val );
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
		$table .= "<tr><td>$pos1<th>{%Main pos}<td>$tagname</h2>";
		for ( $i=1; $i<strlen($tag); $i++ ) {
			$posx = substr($tag,$i,1);
			if ($tagoptions[$i]['lang-'.$lang]) $key1val = "{%{$tagoptions[$i]['lang-'.$lang]}}"; 
				else if ($tagoptions[$i]['display']) $key1val = "{%{$tagoptions[$i]['display']}}"; 
				else $key1val = "<span style='color: #aaaaaa'><i>{%does not apply}</i></span>";
			if ( !$tagoptions[$i][$posx] && $username ) $warnings .= "<p>Invalid value for $pos1 position $i: $posx";
			if ($tagoptions[$i][$posx]['lang-'.$lang]) $key2val = "{%{$tagoptions[$i][$posx]['lang-'.$lang]}}"; 
				else if ($tagoptions[$i][$posx]['display']) $key2val = "{%{$tagoptions[$i][$posx]['display']}}"; 
				else $key2val = "<span style='color: #aaaaaa'><i>{%does not apply}</i></span>";
			$table .= "<tr><td>$posx<th>{%{$tagoptions[$i]['display']}}<td>$key2val</h2>";
		};
		$table .= "</table>";
		
		return $table;
	}
	
};

?>
		
