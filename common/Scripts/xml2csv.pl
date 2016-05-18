use XML::LibXML;
use Getopt::Long;
use XML::XPath;
use XML::XPath::XMLParser;

# TEI to tab separated text 
# A script to create a CSV table in UTF8
# And read the information in each row from a TEI file

$parser = XML::LibXML->new(); 

GetOptions (
            'csvfile=s' => \$csvfile,
            'xmlfolder=s' => \$xmlfolder,
            'queries=s' => \$xpathqueries,
            'restriction=s' => \$restriction,
            'extention=s' => \$ext,
            'header' => \$header,
            'debug' => \$debug,
            );

if ( $xmlfolder eq '' ) { $xmlfolder = "xmlfiles"; };

if ( $xpathqueries eq '' ) { 
	$xpathqueries = "//title"; 
} elsif ( -e $xpathqueries ) {
	$/ = undef;
	open FILE, $xpathqueries; 
	binmode ( FILE, ":utf8" );
	$xpathqueries = <FILE>;
	chomp ( $xpathqueries );
	$xpathqueries = join ( ",", split ( "\n", $xpathqueries) );
	close FILE;
	$/ = "\n";
};
if ( $ext eq '' ) { $ext = "xml"; };

if ( $csvfile eq '' ) { 
	*OUTPUT = *STDOUT;
} else {
	open OUTPUT, ">$csvfile" or die("Unable to open $csvfile\n");
	print "Saving to $csvfile\n";
};
binmode ( OUTPUT, ":utf8" );

@xpath = split ( ",", $xpathqueries);
if ( $header ) { 
	print OUTPUT "[fn]\t".join ( "\t", split ( ",", $xpathqueries) )."\n";
};
readfolder($xmlfolder);

close OUTPUT;

# Go recursively through a folder
sub readfolder ( $folder ) {
	my $folder = @_[0]; 
	if ( $debug ) { print "Treating folder: $folder\n"; };
	
	opendir(my $dh, $folder) || die ("Cannot read: $folder");
	while($file = readdir $dh) {
		if ( $file =~ /^\./ || $file eq '' ) {	
			if ( $debug ) { print "Skipping file: $file\n"; };
			next;
		} elsif ( -d $folder.'/'.$file ) { 
			readfolder ($folder.'/'.$file);
		} elsif ( $file =~ /\.$ext/ )  {
			treatfile ( $folder.'/'.$file );
		} else {
			if ( $debug ) { print "Ignoring file: $file\n"; };
		};
	}
	closedir $dh;
}

sub treatfile ( $file ) {
	my $file = @_[0]; 
	if ( $debug ) { print "Treating file: $file\n"; };
	
	$xml = $parser->load_xml(location => $file);
	
	if ( $restriction && !$xml->findnodes($restriction) ) {
		if ( $debug ) { print "Not matching restriction: $restriction\n"; };
	};
	
	print OUTPUT $file;
	foreach $xpath ( @xpath ) {	
		@tmp = $xml->findnodes($xpath."");
		print OUTPUT "\t";
		if ( @tmp ) {
			print OUTPUT $tmp[0]->textContent;
		} else {
			if ( $debug ) {
				print OUTPUT "*".$xpath;
			} else {
				print OUTPUT "";
			};
		};
	};	
	print OUTPUT "\n";
}