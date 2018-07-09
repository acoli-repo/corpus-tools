use XML::LibXML '1.70';
use LWP::Simple;
use Encode;
use Getopt::Long;
use utf8;

# Script to use Nominatum and OpenStreetMap to look up geographical coordinates
# (c) Maarten Janssen, 2018

$scriptname = $0; $done = 0;

GetOptions ( ## Command line options
            'debug' => \$debug, # debugging mode
            'test' => \$test, # test mode (will not save)
            'force' => \$force, # overwrite existing @geo
            'url=s' => \$fromurl, # url to treat
            'xmlfile=s' => \$xmlfile, # retreat an XML from HTML
            'xpath=s' => \$xpath, # XPath to indicate the node to put the @geo on
            'geo=s' => \$geo, # Attribute name of the @geo 
            'atts=s' => \$atts, # Attribute name of the @geo 
            'exclude=s' => \$exclude, # Values to exclude 
            'qstr=s' => \$qstr, # Allow explicitly given a query string
            );
  
$\ = "\n"; $, = "\t";  $/ = undef;

if ( !$xmlfile ) { $xmlfile = shift; };

# Read the settings.xml file
$tmp = XML::LibXML->load_xml(
	location => "Resources/settings.xml",
); if ( $tmp ) { 
	$settings = $tmp;
	$cqp = $settings->findnodes('//cqp/sattributes/item[@key="text"]/item[@key="geo"]')->item(0);
	$osm = $settings->findnodes('//geomap/osm')->item(0);
	$tmp = $cqp->getAttribute("xpath"); 
	if ( $atts eq '' ) { $atts = $osm->getAttribute("atts"); };
	if ( $exclude eq '' ) { $exclude = $osm->getAttribute("exclude"); };
	$replace = $osm->getAttribute("replace");
	$to = $osm->getAttribute("to");
	@assign = split( /, */, $osm->getAttribute("assign") );
	if ( $xpath eq '' ) { $xpath = $osm->getAttribute("xpath"); };
	if ( $tmp =~ /(.*)\/@(.*)/ ) { 
		if ( $xpath eq '' ) { $xpath = $1; };
		if ( $geo eq '' ) { $geo = $2; };
	};
};

if ( !$xpath ) { $xpath = "//placeName"; };
if ( !$geo ) { $geo = "geo"; };

if ( $debug ) { print "Trying to determine the @$geo on $xpath $atts"; };

if ( !$xmlfile ) { print "usage: perl geolookup.pl [options] filename"; exit; };

$xml = XML::LibXML->load_xml(
	location => $xmlfile,
); if ( !$xml ) { print "Failed to load: $xmlfile"; exit; };

foreach $att ( split ( ",", $atts ) ) {
	( $org, $tr ) = split ( "=", $att );
	if ( $tr eq "" ) { $tr = $org; };
	if ( $org ) { $trans{$org} = $tr; };
}; 

foreach $xpn ( $xml->findnodes($xpath) ) {
	
	if ( $debug ) { print $xpn->toString; };
	
	if ( $xpn->getAttribute($geo) && !$force ) { 
		print "- already geolocated";
		next; 
	};
	
	if ( $atts ) {
		foreach $att ( $xpn->attributes ) {
			$key = $att->getName();
			$key = $trans{$key};
			$val = $att->value;
			if ( $key && $val  && ( $exclude eq "" || $val !~ /\Q$exclude\E/ ) ) { $data .= "&$key=$val"; };
		};
	} else {
		if ( !$qstr ) { $qstr = $xpn->textContent; };
		if ( $replace ne "" ) { $qstr =~ s/$replace/$to/; }; 
		$data .= "&q=$qstr";
	};
		
 	$url = "https://nominatim.openstreetmap.org/search?format=xml&addressdetails=1$data";	
 	if ( $debug ) { print "Query URL: ".$url;};

	$result = get($url); if ( $debug ) { print $result; };
	$res = XML::LibXML->load_xml(
		string => $result,
	); if ( !$res ) { print "Failed to parse: $result"; exit; }; 
	
	$places = $res->findnodes("//place");

	$rsn = $places->item(0);
	if ( $rsn ) {
		print "- ".$xpn->textContent, $rsn->getAttribute('lat'), $rsn->getAttribute('lon');
		$xpn->setAttribute($geo,  $rsn->getAttribute('lat')." ".$rsn->getAttribute('lon') );
		if ( scalar @assign ) {
			foreach $assign ( @assign ) {
				if ( $force || !$xpn->getAttribute($assign) ) { $xpn->setAttribute($assign, $rsn->findnodes("$assign")->item(0)->textContent ); };
			};
		};
		$changed = 1;
	} else {
		print "- ".$xpn->textContent, "!! Not found";
	};

	if ( $debug ) { print $xpn->toString; };
	
};

if ( $changed && !$test ) {
	if ( $debug ) {  print "Changed - saving to $xmlfile"; };
	open FILE, ">$xmlfile";
	print FILE $xml->toString;
	close FILE;		 
};