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
            'extention=s' => \$ext,
            'debug' => \$debug,
            );

if ( $xmlfolder eq '' ) { $xmlfolder = "xmlfiles"; };

if ( $xpathqueries eq '' ) { 
	$xpathqueries = "//title"; 
} elsif ( -e $xpathqueries ) {
	open FILE, $xpathqueries; 
	binmode ( FILE, ":utf8" );
	$xpathqueries = <FILE>;
	chop ( $xpathqueries );
	close FILE;
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
readfolder($xmlfolder);

close OUTPUT;

# Go recursively through a folder
sub readfolder ( $folder ) {
	my $folder = @_[0]; 
	
	opendir(my $dh, $folder) || die ("Cannot read: $folder");
	while(readdir $dh) {
		$file = $_;
		if ( $file =~ /^\./ || $file eq '' ) {	
			next;
		} elsif ( -d $folder.'/'.$file ) { 
			readfolder ($folder.'/'.$file);
		} elsif ( $file =~ /\.$ext/ )  {
			treatfile ( $folder.'/'.$file );
		};
	}
	closedir $dh;
}

sub treatfile ( $file ) {
	my $file = @_[0]; 
	
	$xml = $parser->load_xml(location => $file);
	
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