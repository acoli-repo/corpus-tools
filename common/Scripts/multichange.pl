use XML::LibXML;
use utf8;
use encoding 'utf8';

$pid = shift; $\ = "\n"; $, = "\t";
$chfn = "tmp/pid$pid.xml";
$parser = XML::LibXML->new(); 

$changexml = $parser->load_xml(location => $chfn);

open OUTFILE, ">tmp/pid$pid.log";
binmode ( OUTFILE, ":utf8" );


print OUTFILE "\n<h2>Multiple Token Edit Process</h2>";

if ( !$changexml ) {  print OUTFILE "<p>Process failed: failed to load pid file: $pid"; close OUTFILE; exit; };

$query = $changexml->findnodes("//query")."";
if ( $query ne "" ) {
	print OUTFILE "\n<p>Underlying query: <a href='index.php?action=cqp&cql=$query'>$query</a>";

	print OUTFILE "\n<p>Changes to be made:<ul>";
	foreach $ch ( $changexml->findnodes("//changes/tok") ) {
		print OUTFILE "\n<li>Change <i>".$ch->getAttribute("key")."</i> to: ".$ch->getAttribute("val");
	};
	print OUTFILE "\n</ul>";
} else { 
	print OUTFILE "\n<p>Changes made from verticalization";
};


print OUTFILE "\n<p>Process started: ".localtime."<hr>";


foreach $file ( $changexml->findnodes("//files/file") ) {
	$filename = $file->getAttribute("id");
		print "Treating: $filename";
	if ( $filename =~ /([^\/]+)\.xml/ ) { $fileid = $1; };
	print OUTFILE "<p>File: <a href=\"index.php?action=file&cid=$fileid\">$filename</a>";
	$xml = $parser->load_xml(location => $filename);
	if ( !$xml ) { print OUTFILE "Failed to load xml file: $filename"; next; };

	$changed = 0;
	foreach $tok ( $file->findnodes("tok") ) {
		$tokid = $tok->getAttribute("id");
		@tmp = $xml->findnodes("//*[\@id=\"$tokid\"]"); 
		if ( !@tmp ) { print OUTFILE "Token not found: $tokid"; next; }
		$token = $tmp[0];
		if ( $tok->getAttribute("org") ne $token->getAttribute("form") && $tok->getAttribute("org") ne $token->textContent ) { 
			print OUTFILE "Error: token $tokid has changed"; 
			next; 
		};
		foreach $ch ( $changexml->findnodes("//changes/tok") ) {
			$token->setAttribute($ch->getAttribute("key"), $ch->getAttribute("val"));
			$changed = 1;
		};
		$changes = "";
		foreach $ch ( $tok->findnodes("change") ) {
			$key = $ch->getAttribute("key").""; $val = $ch->getAttribute("val")."";
			if ( $token->getAttribute($key) ne $val ) { 
				print " - Changing ($tokid): $key = $val";
				$token->setAttribute($key, $val);
			
				$changes .= " $key = $val ; ";
			};
		};
		if ( $changes != "" ) {
			print OUTFILE "<p> - Token: <a href=\"index.php?action=file&cid=$fileid&jmp=$tokid\">$tokid</a> - $changes"; # = ".$token->toString;
			$changed = 1;
		};
	};
	if ( $changed ) {
		# File has changed - save
		# We should make a backup as well
		open FILE, ">$filename";
		print FILE $xml->toString;
		close FILE;
	};
	
};

print OUTFILE "<hr>\n<p>Process finished: ".localtime;
close OUTFILE;