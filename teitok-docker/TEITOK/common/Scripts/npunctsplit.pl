use HTTP::Request::Common qw(POST);
use XML::LibXML;
use utf8;
use POSIX qw(strftime);
use Getopt::Long;

# Splits off normalized punctuation into its own token (by default on @nform)
# So turns <tok nform="word,">word</tok> into <tok>word</tok><tok nform=","><ee/></tok>
# You can use a # to not care about the spelling - so <tok nform="#,">incorrectability</tok>
# With --dtok it will also add dtoks for same level (with the split forms as @form)
# So turn <tok nform="de él">dél</tok> into <tok nform="de él">dél<dtok form="de"/><dtok form="él"/></tok>
# (c) Maarten Janssen, 2020

$\ = "\n"; $, = "\t";
$scriptname = $0;

GetOptions ( ## Command line options
            'debug' => \$debug, # debugging mode
            'test' => \$test, # test mode
            'dtok' => \$dtok, # flag whether to also split tokens into dtoks if there is a space in the splitform
            'form=s' => \$splitform, # which form to tag (default: nform)
            'force' => \$force, # force retreating
            );

$filename = shift;

if ( $splitform eq '' ) { $splitform = 'nform'; };
$ttroot = "/var/www/html/teitok";

binmode (STDOUT, ":utf8");

if ( $filename eq "" ) { print "usage: perl npunctsplit.pl [fn]"; exit; };

# Load the XML file
my $xml = XML::LibXML->load_xml(
	location => $filename,
); if ( !$xml ) { print FILE "Not able to parse file"; exit; };

foreach $tok ( $xml->findnodes("//tok") ) {
	$oldform = $tok->getAttribute($splitform);
	$form = $tok->getAttribute('form') or $form = $tok->textContent;
	if ( $oldform =~ /(.+)([.,!?])$/ ) {
		$newform = $1; $punct = $2;
		if ( $newform eq $form && $newform eq '#' ) { 
			$tok->removeAttribute($splitform);			
		} else {
			$tok->setAttribute($splitform, $newform);			
		};
		$newtok = $xml->createElement( "tok" );
		$newee = $xml->createElement( "ee" );
		$newtok->addChild($newee);
		$newtok->setAttribute($splitform, $punct);
		$newtok->setAttribute('form', '--');
		$tok->parentNode->insertAfter($newtok, $tok);
		print "- Inserted new token for $punct after $newform";
	};
	if ( $dtok && $ort =~ / / ) {
		foreach $dform ( split ( / /, $ort ) ) {
			$dtok = $xml->createElement( "dtok" );
			$tok->addChild($dtok);
			$dtok->setAttribute("form", $dform);
			print "- Added dtok $dform";
		};
	};
};

# Now, save the file
$xmlfile = $xml->toString;

if ( $test ) { 
	binmode (STDOUT, ":latin1");
	print  $xmlfile;
} else {

	# Make a backup of the file
	( $buname = $filename ) =~ s/xmlfiles.*\//backups\//;
	$date = strftime "%Y%m%d", localtime; 
	$buname =~ s/\.xml/-$date.xml/;
	if ( !-e $buname ) { $cmd = "/bin/cp $filename $buname"; };
	`$cmd`;

	open FILE, ">$filename";
	print FILE $xmlfile;
	close FILE;

	print "$filename has been treated on $splitform - renumbering tokens now";

	( $renum = $scriptname ) =~ s/npunctsplit/xmlrenumber/;
	# Finally, run the renumber command over the same file
	$cmd = "/usr/bin/perl $renum --filename=$filename";
	# print $cmd;
	`$cmd`;
};
