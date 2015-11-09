use XML::LibXML;
use Getopt::Long;
$\ = "\n";$, = "\t";

GetOptions (
            'id=s' => \$id,
            'tag=s' => \$tag,
            'xquery=s' => \$xquery,
            'filename=s' => \$filename,
            'debug' => \$debug,
            );

$parser = XML::LibXML->new(); 
eval {
	$doc = $parser->load_xml(location => $filename);
};
if ( !$doc ) { print "Unable to parse"; exit; };
if ( $xquery eq '' ) { $xquery = "//".$tag."[\@id='$id']"; };
if ( $debug ) { print "Query : ". $xquery; };
foreach $ttnode ($doc->findnodes($xquery)) {
	print $ttnode->toString;
}; 
