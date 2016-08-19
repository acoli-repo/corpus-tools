use XML::LibXML;
use Getopt::Long;
use XML::XPath;
use XML::XPath::XMLParser;
$\ = "\n"; $, = "\t";

# Tab separated text to TEI
# A script to load a CSV table in UTF8
# And fills the information in each row into a TEI file

$parser = XML::LibXML->new(); 

GetOptions (
            'csvfile=s' => \$csvfile,
            'teiheader=s' => \$teiheader,
            'xmlfolder=s' => \$xmlfolder, # Which folder to use
            'debug' => \$debug, 
            'notext' => \$notext, # Do not create anything below <TEI> for new files
            'nocreate' => \$nocreate, # Do no create non-existing files
            'template=s' => \$template,
            'queries=s' => \$xpathqueries,
            'header' => \$header, # Whether the CSV file has a (non XPath) header row
            );

# Check whether the conditions are met
if ( !$xmlfolder ) { $xmlfolder = "xmlfiles"; };
if ( !$csvfile ) { $csvfile = shift; };
if ( !$csvfile ) { print "Usage: csv2tei [options] csvfile"; exit; };
if ( !-e $csvfile ) { print "No such file: $csvfile"; exit; }; 

$orgfile = $csvfile.".org";
if ( -e $orgfile ) {
	open FILE, $orgfile;
	$/ = undef;
	@orglines = split ( "\n", <FILE> );
	close FILE;
	$/ = "\n";
};

# Open the CSV file
open FILE, $csvfile;
binmode ( FILE, ":utf8" );

# read the header line(s)
if ( $header ) { 
	$headerrow = <FILE>; chomp($headerrow); 
	$orgheader = shift(@orglines);
	@namefields = split ( "\t", $headerrow );
	if ( $debug ) { print "Field names:\n".join ( "\n", @namefields); };
};

if ( $xpathqueries ) {
	open XFILE, $xpathqueries; 
	binmode ( XFILE, ":utf8" );
	$xpathqueries = <XFILE>;
	chop ( $xpathqueries );
	close XFILE;
	@xpfields = split ( ",", $xpathqueries ); 
	unshift(@xpfields, "[fn]");
	if ( $debug ) { print "Xpath definitions:\n".join ( "\n", @xpfields); };
} elsif ( $teiheader ) {
} else {
	$xprow = <FILE>; chomp($xprow);
	@xpfields = split ( "\t", $xprow );
	$orgfields = shift(@orglines);
	if ( $debug ) { print "Xpath definitions:\n".join ( "\n", @xpfields); };
	if ( $orgfields && $orgfields ne $xprow ) {
		print " - Error: header of original file not matching: ".$checkheader;
		exit;
	};
};

$filecnt = 0;

# Read the actual lines
while ( <FILE> ) {
	chomp; $line++; $fn = ""; undef(%toset);
	$filecnt++;
	$linetxt = $_;
	if ( $linetxt eq '' ) { next; };
	@row = split ( "\t" );
	
	
		
	if ( $debug ) { print "Line $line"; };

	$orgline = shift(@orglines);
	if ( $orgline && $orgline eq $linetxt ) {
		if ( $debug ) { print " = $linetxt\n - non-modified line - skipping"; };
		next;
	};
	
	# See what we need to do with this row
	for ( $i=0; $i<scalar @row; $i++ ) {
		$xpath = $xpfields[$i];
		if ( $xpath eq "[fn]" ) { 
			$fn = $row[$i];
		} elsif ( $xpath =~ /^\// ) {
			if ( $row[$i] ne ''  ) {
				$toset{$xpath} = $row[$i];
				if ( $debug ) { print $row[$i], $xpath; };
			};
		} else {
			print " -- Unparsable definition: $xpath"; exit;
		};
	};
	
	# Check the filename or use the sequential number
	if ( $fn eq '' ) { 
		$fn = $filecnt;
	};
	if ( $fn !~ /\.xml$/ ) { $fn .= ".xml"; };
	if ( $fn !~ /\// ) {
		$fn = "$xmlfolder/$fn";
	};
	
	print "Treating: $fn";
	# See if the file exists or create a new TEI XML
	if ( -e $fn ) {
		eval {
			$xml = $parser->load_xml(location => $fn);
		};
		if ( !$xml ) { print "Unable to parse: $fn"; exit; };
	} else {
		if ( $template ) {
			$tei = $template;
		} elsif ( $notext ) {
			$tei = "<TEI/>";
		} else { 
		$tei = "<TEI>
<teiHeader>
</teiHeader>
<text>
</text>
</TEI>";
		};
		$xml = $parser->load_xml(string => $tei);
	};	

	# Check whether we should create a new file
	if ( !-e $fn && $nocreate ) { 
		if ( $debug ) { print " - not creating non-existing file: $fn"; };
		next; 
	};
	
	# Now load the actual values into the XML
	while ( ( $xpath, $xval ) = each ( %toset ) ) {
		xpathset ( $xpath, $xml, $xval );
	};
	
	open OUTFILE, ">$fn";
	print OUTFILE $xml->toString;
	close OUTFILE;
	
};

sub xpathset ( $xquery, $xml, $val ) {
	( $xquery, $xml, $val ) = @_;
	
	@tmp = $xml->findnodes($xquery); 
	if ( @tmp ) { 
		# Node exists
		$thisnode = shift(@tmp);
			if ( $thisnode->nodeType == 2 ) {
				# Attribute node
				$thisnode->setValue($val);
				if ( $debug ) { print "Setting attribute value: $xquery = $val"; };
			} elsif ( $thisnode->textContent ne "" ) {
				# This can remove XML node - check if there are only text children
				if ( $thisnode->hasChildNodes() ) { $thisnode->removeChildNodes(); };
				$thisnode->appendText($val);
				if ( $debug ) { print "Setting text content: $xquery = $val"; };
			} else {
				$newnode = $xml->createTextNode( $val );
				$thisnode->addChild($newnode);
				if ( $debug ) { print "Adding text content: $xquery = $val"; };
			};
	} else {
		# Node not found - create it
		if ( $xquery =~ /^(.*)\/(.*?)$/ ) {
			$parxp = $1; $thisname = $2;
			if ( $thisname =~ /^@(.*)/ ) {
				# Attribute node
				$thisname = $1; 
				$parnode = makenode($xml, $parxp);
				$parnode->setAttribute($thisname, $val);
			} else {
				$thisnode = makenode($xml, $xquery);
				$newnode = $xml->createTextNode( $val );
				$thisnode->addChild($newnode);
			};
		};
	};
};

sub makenode ( $xml, $xquery ) {
	my ( $xml, $xquery ) = @_;
	@tmp = $xml->findnodes($xquery); 
	if ( scalar @tmp ) { 
		$node = shift(@tmp);
		if ( $debug ) { print "Node exists: $xquery"; };
		return $node;
	} else {
		if ( $xquery =~ /^(.*)\/(.*?)$/ ) {
			my $parxp = $1; my $thisname = $2;
			my $parnode = makenode($xml, $parxp);
			$thisatts = "";
			if ( $thisname =~ /^(.*)\[(.*?)\]$/ ) {
				$thisname = $1; $thisatts = $2;
			};
			$newchild = XML::LibXML::Element->new( $thisname );
			
			# Set any attributes defined for this node
			if ( $thisatts ne '' ) {
				if ( $debug ) { print "setting attributes $thisatts"; };
				foreach $ap ( split ( " and ", $thisatts ) ) {
					if ( $ap =~ /\@([^ ]+) *= *"(.*?)"/ ) {
						$an = $1; $av = $2; 
						$newchild->setAttribute($an, $av);
					};
				};
			};

			if ( $debug ) { print "Creating node: $xquery ($thisname)"; };
			$parnode->addChild($newchild);
			
		} else {
			print "Failed to find or create node: $xquery";
		};
	};
};
