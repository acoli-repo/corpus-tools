use XML::LibXML '1.70';
use LWP::Simple;
use Encode;
use utf8;
use POSIX 'strftime';
use Getopt::Long;

# A script to fill <dtok> created by taggers that do not put a @form in the <dtok> 
# (such as Freeling)
# @form read from Neotag external lexicon or Neotag internal lexicon

$scriptname = $0;

 GetOptions ( ## Command line options
            'debug' => \$debug, # debugging mode
            'verbose' => \$verbose, # verbose mode
            'pid=s' => \$pid, # choose a specific neotag parameter set (if there is more than one)
            'folder=s' => \$folder, # choose a folder to process
            'file=s' => \$file, # choose a file to process
            );
  
$/ = undef; $\ = "\n"; $, = "\t";

$today = POSIX::strftime('%Y-%m-%d', localtime);

if ( !$folder ) {
	$folder = shift;
};
if ( !$folder ) {
	$folder = "xmlfiles";
};

binmode ( STDOUT, ":utf8" );

# Read the parameter set
my $settings = XML::LibXML->load_xml(
    location => "Resources/settings.xml",
); if ( !$settings ) { print "Not able to parse settings.xml"; };

if ( $pid ) { $pidr = "[\@pid=\"$pid\"]"; };

print "//neotag/parameters/item$pidr/\@lexicon";
if ( $settings->findnodes("//neotag/parameters/item$pidr/\@lexicon") ) {
	$lexiconfile = $settings->findnodes("//neotag/parameters/item$pidr/\@lexicon")->item(0)->textContent;
} else {
	$lexiconfile = $settings->findnodes("//neotag/parameters/item$pidr/\@params")->item(0)->textContent;
};

print "Using lexicon: $lexiconfile";
# Read the parameter data for back processing
my $lexicon = XML::LibXML->load_xml(
    location => $lexiconfile,
); if ( !$lexicon ) { print "Not able to parse lexicon: $lexiconfile"; };

print "Processing: $folder";
if ( $file ) {
	treatfile ( $file );
} else {
	readfolder($folder);
};
exit;



sub readfolder ( $folder ) {
	my $folder = @_[0];
	
	opendir(my $dh, $folder) || die "Can't open $folder: $!";
	while (readdir $dh) {		
		$loc = $_;
		$filename = "$folder/$loc";
		if ( $loc =~ /^\./ ) {
			next;
		} elsif ( -d $filename ) {
			readfolder($filename);
		} else {
			treatfile ( $filename );
		};
	}
	closedir $dh;
}

sub treatfile ( $filename ) {
	my $filename = @_[0];
	print "Treating: $filename"; 
	
	# Read the definitions for this newspaper
	eval {
		$xml = XML::LibXML->load_xml(
			location => $filename,
		);
	}; if ( $@ ) {
		print "Parsing error in $xml: $@";
		return; 
	};
	
	foreach $dtok ( $xml->findnodes("//dtok[not(\@form) or \@form=\"\"]") ) {

		$contr = $dtok->parentNode->textContent;
		$lemma = $dtok->getAttribute("lemma");
		$pos = $dtok->getAttribute("pos");
		$form = $dtok->getAttribute("form");
		if ( $lemma eq '"' ) { $lemma="&quot;"; };
		print "Checking: $lemma in $contr";
		
		# Look it up in the parameters
		if ( $forms{"$lemma.$pos"} ) {
			$form = $forms{"$lemma.$pos"};
		} else {
			print "//lexicon//tok[\@lemma=\"$lemma\" and \@pos=\"$pos\"]";
			@tmp = $lexicon->findnodes("//lexicon//tok[\@lemma=\"$lemma\" and \@pos=\"$pos\"]");
			if ( $tmp[0] ) {
				$form = lc($tmp[0]->parentNode->getAttribute("key"));
				$forms{"$lemma.$pos"} = $form;
			} else {
				$form = "";
				print "Not found: //lexicon//tok[\@lemma=\"$lemma\" and \@pos=\"$pos\"]";
			};
		};
		if ( $contr =~ /^[A-ZÁÉÓÚÍ]/ && $dtok->getAttribute("id") =~ /-1$/ ) {
			$form = ucfirst($form);
		};
		print $lemma, $pos, $form, $contr;
		$dtok->setAttribute("form", $form);
		print $dtok->toString;
	};
	
	# Now save the XML again
	open FILE, ">$filename";
	print FILE $xml->toString;
	close FILE;

};

