use Getopt::Long;
use XML::LibXML;
use utf8;

GetOptions ( ## Command line options
		'debug' => \$debug, # debugging mode
		'nosub' => \$nosub, # do not create subfolders for images
		'rename' => \$rename, # rename //pb/@facs to local files
		'thumbnails' => \$thumbnails, # also create thumbnails
		'filename=s' => \$filename, # language of input
		);

if ( !$filename ) { $filename = shift; };

	$xmlid = $filename;
	$xmlid =~ s/.*\///;
	$xmlid =~ s/\.xml//;

$\ = "\n"; $, = "\t";

$parser = XML::LibXML->new(); $doc = ""; 
eval {
	$tmpdoc = $parser->load_xml(location => "$filename", load_ext_dtd => 0 );
};
if ( !$tmpdoc ) { 
	print "Unable to parse\n"; print $@;
	exit;
};

binmode(STDOUT,":utf8");

if ( !-d "Facsimile" ) { mkdir("Facsimile"); };
if ( !$nosub && !-d "Facsimile/$xmlid" ) { mkdir("Facsimile/$xmlid"); };
if ( !$nosub && $thumbnails && !-d "Thumbnails/$xmlid" ) { mkdir("Thumbnails/$xmlid"); };

$pn = 0;
foreach $mt ($tmpdoc->findnodes("//pb[\@facs]")) {

	$pn++;

	$facsurl = $mt->getAttribute('facs');
	if ( $facsurl =~ /\.([^.\/]+)$/ ) { $ext = $1; } else { $ext = "jpg"; };
	$pagenum = $mt->getAttribute('n');
	if ( !$pagenum ) { $pagenum = $pn; };
	
	
	print $facsurl;	

	if ( $facsurl !~ /^http/ ) { next; };

	if ( $nosub ) {
		$localimg = "$xmlid\_$pagenum.$ext";
	} else {	
		$localimg = "$xmlid/$xmlid\_$pagenum.$ext";
	};
	print $localimg;

	if ( !-e "Facsimile/$localimg" ) {
		$cmd = "curl -o Facsimile/$localimg '$facsurl'";
		print $cmd;
		`$cmd`;
	};

	if ( $thumbnails && !-e "Thumbnails/$localimg" ) {
		$cmd = "convert -resize 80x Facsimile/$localimg Thumbnails/$localimg";
		print $cmd;
		`$cmd`;
	};
	
	if ( $rename ) {
		$mt->setAttribute("facs", $localimg);
	};

}

if ( $rename ) {
	open FILE, ">$filename" or die ("unable to save");
	# binmode ( FILE, ":utf8" );
	print FILE $tmpdoc->toString;
	close FILE;
};

