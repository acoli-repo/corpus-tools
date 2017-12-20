use XML::LibXML;
use Getopt::Long;

$parser = XML::LibXML->new(); 

$\ = "\n"; $, = "\n";

 GetOptions ( ## Command line options
            'debug' => \$debug, # debugging mode
            'test' => \$test, # test mode
            'force' => \$force, # force retreating
            'retok' => \$retok, # retokenize after OCR
            'lang=s' => \$langid, # language to use for OCR
            'input=s' => \$input, # name of the PDF file
            'parse=s' => \$parsetype, # retreat an XML from HTML
            'getimg=s' => \$gs, # retreat an XML from HTML
            'useimg' => \$useimg, # Use already converted image files 
            'pagtype=s' => \$pagtype, # retreat an XML from HTML
            'offset=i' => \$offset, # How many initial pages to skip
            );

if ( !$input ) {
	$input = shift;
};

if ( !defined($offset) ) { $offset = 1; };

if ( !-e $input && !$useimg ) { 
	if ( -e "Originals/$input" ) {
		$input = "Originals/$input";
	} elsif ( -e "pdf/$input" ) {
		$input = "pdf/$input";
	} else {
		print "Error - no such file: $input (aborting)"; exit; 
	};
};


if ( $parsetype eq "ocr" ) {
	$pagetype = "pb";
	$linetype = "lb";
	$xmlfiles = "xmlfiles";
} elsif ( $parsetype eq "line" ) {
	$pagetype = "page";
	$linetype = "line";
	$xmlfiles = "pagetrans";
} elsif ( $parsetype eq "page" ) {
	$pagetype = "page";
	$linetype = "lb";
	$xmlfiles = "pagetrans";
} else {
	$pagetype = "pb";
	$linetype = "pb";
	$xmlfiles = "xmlfiles";
};

$filename = $input;
$filename =~ s/\.pdf//;
$filename =~ s/.*\///;

if ( -e "tmp/$filename.create.log" && !$debug ) {
	open LOG, ">>tmp/$filename.create.log";
	select LOG;
	print "---------";
};

$xmlfile = "$xmlfiles/$filename.xml";

@imgconv = ("/usr/local/bin/pdfimages", "/usr/bin/pdfimages");
# Convert the PDF to JPG images - 1 per page
if ( !-e "Facsimile/$filename/$filename-001.jpg" || $force ) {
	if ( !-d "Facsimile/$filename" ) { mkdir("Facsimile/$filename"); };
	print "Converting PDF to JPG images";
	if ( $gs eq "gs" ) {
		$cmd = "gs -dNOPAUSE -sDEVICE=jpeg -sOutputFile=Facsimile/$filename/$filename-%d.jpg -dJPEGQ=100 -r1000 $input -c quit ";
	} else {
		# Find the full path to a PDF to IMG converter
		while ( $pdfimagecmd eq "" && scalar @imgconv > 0 ) {
			$tryfile = shift(@imgconv);
			if ( -e $tryfile ) { $pdfimagecmd = $tryfile; };
		};
		if ( !$pdfimagecmd ) { print "No converter PDF > Image found (aborting)"; exit; };
		if ( !-d "Facsimile/$filename" ) { mkdir("Facsimile/$filename"); }; 
		$cmd = "$pdfimagecmd -all  $input Facsimile/$filename/$filename ";
	};
	if ( $debug ) { print $cmd; };
	`$cmd`;
};

@imgcnt = <Facsimile/$filename/*>;
if ( scalar @imgcnt == 0 ) {
	print "Failed to create image files [$cmd] (aborting)"; exit;
};

if ( -e $xmlfile ) {
	$/ = undef;
	open FILE, $xmlfile;
	$teistring = <FILE>;
	close FILE;
	print "Loaded skeleton file $xmlfile";
};
if ( $teistring eq '' ) { $teistring = "<TEI>
<teiHeader/>
<text>
</text>
</TEI>"; };

$xml = $parser->load_xml(string => $teistring );
$text = $xml->findnodes("//text")->item(0);

$orgfile = makenode($xml, '/TEI/teiHeader/notesStmt/note[@n="orgfile"]');
$orgfile->appendText($input);
# print $xml->toString(); exit;

# If we are making <page> XML, mark the <text> element as such
if ( $pagetype eq "page" ) {
	$text->setAttribute('type', 'pagetrans');
};

# Check how many pages we got
$/ = undef;
@tmp = <Facsimile/$filename/$filename-*.jpg>;
$pages = scalar @tmp;

if ( -e "Resources/tesseract.conf" ) { $config = "Resources/tesseract.conf"; };

opendir(my $dh, "Facsimile/$filename") || die "Can't open directory: $!"; $i=0;
FACS: while (readdir $dh) {
	$jf = $_; 
	if ( $jf =~ /^\./ ) { next; }; 
	$i++;
	if ( $i <= $offset ) { print "Skipping the page $jf ($i)"; next FACS; }; # Skip x pages
   	$jf = "Facsimile/$filename/$jf";
	print "Processing page: $jf";
	
	if ( $parsetype eq "ocr" ) {
		if ( $debug ) { print "tmp/$filename/$filename-1.hocr"; };
		if ( !-e "tmp/$filename/$filename-1.hocr" || $force ) {
			if ( !-d "tmp/$filename" ) { mkdir("tmp/$filename"); };
			print "Running OCR";
			# OCR the page
			if ( $langid ) {  $langopt = "-l $langid"; };
			$cmd = "tesseract  $langopt $jf tmp/$filename/$filename-$i hocr $config > /dev/null ";
			if ( $debug ) { print $cmd; };
			`$cmd`;
		} else {
			if ( $debug ) { print "already OCR'ed: tmp/$filename/$filename-1.hocr"; };
		};
	
		if ( !-d "tmp" ) { mkdir("tmp"); };
		open FILE, "tmp/$filename/$filename-$i.hocr";
		binmode(FILE, ":utf8");
		$hx = <FILE>;
		close FILE;
	
		$hx =~ s/<!DOCTYPE [^>]+>//gsmi;
		$hx =~ s/xmlns="[^"]+"//gsmi;
	
		# Load the hOCR file
		$hocr = $parser->load_xml(string => $hx);
	
		print "Converting hOCR to TEI";
		foreach $node ( $hocr->findnodes("//span") ) { 
			$class = $node->getAttribute('class'); 
			if ( $debug ) { print "Treating $class: ".$node->getAttribute('id'); };
			$node->removeAttribute('id');
		
			if ( $class eq 'ocrx_word' ) {
				# ocrx_word is a token, but without the punctuation marks split off
				$node->setName("tok");
				$node->removeAttribute('class');
			} elsif ( $class eq 'ocr_line' ) {
				# ocr_line is a lb, but a node, not an empty node
				$node->setName("lb");
				$node->removeAttribute('class');
			};
		
			$title = $node->getAttribute('title'); 
			foreach $part ( split ( " *; *", $title ) ) {
				if ( $part =~ /([^ ]+) (.*)/ ) {
					$an = $1; $av = $2;
					$av =~ s/"//g;
					$node->setAttribute($an, $av);
				};
			};
			$node->removeAttribute('title');
		};

		foreach $node ( $hocr->findnodes("//div") ) { 
			$class = $node->getAttribute('class'); 
			$node->removeAttribute('id');
		
			if ( $class eq 'ocr_carea' ) {
				# ocr_carea is just a div
				$node->removeAttribute('class');
			} elsif ( $class eq 'ocr_page' ) {
				# ocr_page is a pb, but a node, not an empty node
				$node->setName("pb");
				$node->removeAttribute('class');
			};
		
			$title = $node->getAttribute('title'); 
			foreach $part ( split ( " *; *", $title ) ) {
				if ( $part =~ /([^ ]+) (.*)/ ) {
					$an = $1; $av = $2;
					$av =~ s/"//g; 
					if ( $an eq "image" ) { $an = "facs"; $av =~ s/Facsimile\///; };
					if ( $an eq "ppageno" ) { $an = "n"; $av = $i; };
					$node->setAttribute($an, $av);
				};
			};
			$node->removeAttribute('title');
		};
	
		foreach $node ( $hocr->findnodes("//p") ) { 
			$class = $node->getAttribute('class'); 
			$node->removeAttribute('id');
			if ( $class eq 'ocr_par' ) {
				$node->removeAttribute('class');
			};

			$title = $node->getAttribute('title'); 
			foreach $part ( split ( " *; *", $title ) ) {
				if ( $part =~ /([^ ]+) (.*)/ ) {
					$an = $1; $av = $2;
					$av =~ s/"//g; if ( $an eq "image" ) { $an = "facs"; $av = "$filename/$av"; };
					$node->setAttribute($an, $av);
				};
			};
			$node->removeAttribute('title');
		};

		foreach $node ( $hocr->findnodes("//em") ) { 
			$node->setName("hi");
			$node->setAttribute('rend', 'italic');
		};

		foreach $node ( $hocr->findnodes("//strong") ) { 
			$node->setName("hi");
			$node->setAttribute('rend', 'bold');
		};

		# Add all the pb to the TEI <text>	
		foreach $node ( $hocr->findnodes("//pb") ) { 
			$text->addChild($node);
		};
		if ( $debug ) { print "TEXT: ".$text->toString; };
	} elsif ( $parsetype eq "line" ) {
		# Now, recognize lines		
	} else {
		if ( $pagtype == "2" ) {
			if ( $i > $offset+1 ) {
				$node = $xml->createElement($pagetype);
				$pn = ($i-$offset-1)."v";
				$node->setAttribute("n", $pn);
				$node->setAttribute("id", "page-$pn");
				( $fi = $jf ) =~ s/^Facsimile\///;
				$node->setAttribute("facs", "$fi");
				$node->setAttribute("crop", "left");
				$text->addChild($node);
			};
			
			$node = $xml->createElement($pagetype);
			$pn = ($i-$offset)."r";			
			$node->setAttribute("n", $pn);
			$node->setAttribute("id", "page-$pn");
			( $fi = $jf ) =~ s/^Facsimile\///;
			$node->setAttribute("facs", "$fi");
			$node->setAttribute("crop", "right");
			$text->addChild($node);
		} else {
			$node = $xml->createElement($pagetype);
			$node->setAttribute("n", "$i");
			$node->setAttribute("id", "page-$i");
			( $fi = $jf ) =~ s/^Facsimile\///;
			$node->setAttribute("facs", "$fi");
			$text->addChild($node);
		};
	};		
};

$xmltxt = $xml->toString;

# Change lb and pb into empty elements
$xmltxt =~ s/<lb([^>]*)>/<lb\1\/>/g;
$xmltxt =~ s/<\/lb>//g;

$xmltxt =~ s/<page /\n<page /g;
$xmltxt =~ s/<\/text>/\n<\/text>/g;

$xmltxt =~ s/<pb([^>]*(?<!\/))>/<pb\1\/>/g;
$xmltxt =~ s/<\/pb>//g;

if ( $test ) { 
	print "Done - result:";
	print $xmltxt;
	exit;
};
open FILE, ">$xmlfile";
print FILE $xmltxt;
close FILE;
print "Saved file to $xmlfile";
print "DONE";

if ( $retok ) {
	`perl xmlrenumber.pl $xmlfile`;
};

# This does not seem to work - and is it useful?
# `perl ../common/Scripts/xmlrenumber.pl $xmlfiles/$filename.xml`;

sub makenode( $rootxml, $xpath ) {
	my ( $rootxml,  $xpath) = @_; 
	
	print "Looking for $xpath";
	$testxp = $rootxml->findnodes($xpath);

	if ( $testxp ) { return $testxp->item(0); };
	
	if ( $xpath =~ /^(.*)\/([^\/]+)$/  ) {  $pxp = $1; $newname{$pxp} = $2;  }
	else { print "Unable to create node $xpath"; };
	
	$newparent{$pxp} = makenode($rootxml, $pxp);
	print "Created node: for $pxp, $newname{$pxp} ".$newparent{$pxp}->toString();
	
	if ( $newname{$pxp} =~ /^(.*)\[(.*)\]$/ ) {
		$newname{$pxp} = $1;
		$newatts = $2;
	};

	print "Now creating in ".$pxp." a  ".$newname{$pxp};
	$newnode = $xml->createElement($newname{$pxp});
	while ( $newatts =~ /\@([^ \]"]+)=['"]([^"]+)['"]/g ) {
		$newnode->setAttribute($1, $2);
	};
	$newparent{$pxp}->addChild($newnode);
	
	return $newnode;
};