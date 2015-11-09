use XML::XPath;
use XML::XPath::XMLParser;
use DBI;
use Cwd;
use Cwd 'abs_path';
use HTML::Entities;
use Getopt::Long;
use Data::Dumper;
use List::Util qw[min max];
use Time::HiRes qw( usleep ualarm gettimeofday tv_interval nanosleep );
binmode STDOUT, ":utf8";

$\ = "\n";$, = "\t";

## NEOTAG FOR CORPUSWIKI:
#
# An improved version of NeoTag that interacts directly with the CorpusWiki XML files

GetOptions ( ## Command line options
            'infile=s' => \$folder, # the full path of corpuswiki
            'debug' => \$debug, # debugging mode
            'force' => \$force, # tag even if already tagged
            'test' => \$test, # tokenize to string, do not change the database
            'filename=s' => \$filename, # language of input
            'thisdir=s' => \$thisdir, # determine where we are running from
            'folder=s' => \$folder, # determine where we are running from
            'labels' => \$labeldo, # also load labels (if present)
            );

if ( !$filename ) { print "no text specified"; exit; };
( $fileid = $filename ) =~ s/.*\///;

if ( !$thisdir ) { 
	$thisdir = getcwd();
	# ( $thisdir = abs_path($0) ) =~ s/[^\/]*$//; # Get the name of the folder where the script is running from
}; print $thisdir;

$forcelemma = 1;


if ( !$folder ) { 
	$folder = "/crpc/neotag/crpc";
	# ( $folder = abs_path($0) ) =~ s/[^\/]*$//; # Get the name of the folder where the script is running from
	# $folder =~ s/[^\/]*\/$/parameters\//; # Get the name of the folder where the script is running from
	# print $0, abs_path($0), $folder; exit;
};

print " - reading XML file $filename";
$/ = undef;
open FILE, "$thisdir/$filename";
binmode ( FILE, ":utf8" );
$xmltxt = <FILE>;
$/ = "\n";
close FILE;

if ( $xmltxt eq '' ) { print "File empty or no such file: $thisdir/$filename"; exit; };

# Check if this text has not already been tagged
if ( $xmltxt =~ /pos="/ && !$test && !$force ) { print "already tagged"; exit; };

# For this to work, we need to kill the DOCTYPE
$xmltxt =~ s/(<!DOCTYPE[^>]+>)//; $doctype = $1; 

$xp = XML::XPath->new(xml => $xmltxt);

$nodeset = $xp->find('//tok'); # find all words
print " - number of words: ".$nodeset->size;
if ( $nodeset->size == 0 ) { print "no tokens in this texts: has it been tokenized?"; exit; };

$txtfile = "$thisdir/tmp/tagtemp\_$fileid";
$cmd = "/usr/bin/xsltproc --novalid $thisdir/Resources/verticalize.xslt $thisdir/$filename | perl -e 'while (<>) { s/(\\S*)\\t(\\S*)/\\2\\t\\1/; print; }' > $txtfile";
`$cmd`;
print $cmd;


# We need to find a nice balance of settings for transition smoothing, end retry, etc.
# Which might very well depend on the language....

$tagfile = "$thisdir/tmp/tagged\_$fileid";
$cmd = "/bin/cat $txtfile | /usr/local/bin/neotag --linenr --featuretags --forcelemma --transsmooth=0.1 --endretry=1 --folder='$folder' > $tagfile";
`$cmd`;
print $cmd;

$taghist = $deftag;

if ( $labeldo ) {
	if ( -e "$folder/labels.txt" ) {
		open LABELFILE, "$folder/labels.txt";
		binmode LABELFILE, ":utf8";
		while ( <LABELFILE> ) {
			chomp;
			( $ltype, $lform, $llemma, $lpos, $ltag, $llabel, $lfreq ) = split ( "\t" );
			if ( $lfreq > $max{$ltag.'@'.$lform.'@'.$ltype} ) {
				$labels{$ltag.'@'.$lform}{$ltype} = $llabel;
				$max{$ltag.'@'.$lform.'@'.$ltype} = $lfreq;
			};
			if ( $lfreq > $max{$lpos.'@'.$llemma.'@'.$ltype} ) {
				$labels{$lpos.'@'.$llemma}{$ltype} = $llabel;
				$max{$lpos.'@'.$llemma.'@'.$ltype} = $lfreq;
			};
		};
		close LABELFILE;
	} else {
		print "Asked for labels, but no label file in $folder";
	};
	if ( -e "$folder/translexicon.txt" ) {
		# Read an external translation lexicon
		open LABELFILE, "$folder/translexicon.txt";
		binmode LABELFILE, ":utf8";
		while ( <LABELFILE> ) {
			chomp;
			( $llemma, $lpt, $llabel ) = split ( "\t" );
			($lpos, $lsub) = split ( " ", $lpt );
			if ( !$max{$lpos.':@'.$llemma.'@gloss'} ) {
				$labels{$lpos.':@'.$llemma}{"gloss"} = $llabel;
				$max{$lpos.':@'.$llemma.'@gloss'} = 1;
			};
		};
		close LABELFILE;
	};
};

open TAGFILE, $tagfile;
binmode TAGFILE, ":utf8";
while ( <TAGFILE> ) {
	$tagline = $_;
	$tagline =~ s/'/&#039;/g;
	$tagline =~ s/\x00//g; # Remove null characters that (created by Neotag at times)
	chomp($tagline);
	( $wordid, $word, $tag, $lemma, $source ) = split ( "\t",  $tagline );
	if ( $tag =~ /^pos:(.*?)(;|$)/ ) { $pos = $1; };
	if ( $debug ) { print $wordid, $word, $tag, $lemma, $pos; };

	if ( $wordid eq "- dtok" ) {
		# Treat a dtok
		
		$si++;
		$dform = $word;
		$dlemma = $lemma;
		$dtok = XML::XPath::Node::Element->new('dtok');
		@attpairs = split ( ';', $tag );
		foreach $attpair ( @attpairs ) {
			($key, $val) = split ( ':', $attpair );
			if ( $key eq 'form' ) {
				$dform = $val;
			} elsif ( $key eq 'lemma' ) {
				$alemma = $val;
			} else {	
				my $setatt = XML::XPath::Node::Attribute->new($key,  $val);	
				$dtok->appendAttribute($setatt);
			};
		};		
		
		# Set the form
		# if ( $form eq '' ) { $form = $words[$j]; };
		if ( $dform eq '??' ) {
			$dform = "";
		};
		
		if ( $dlemma eq '' ) { # Lexical contractions do not have a lemma for their parts, so do not fill for now
			# $dlemma = "??"; 
		} elsif ( $dlemma eq '??' ) {
			$dlemma = "";
		};

		# If this dtok is not type, make it a base
		$tokpos = $thisnode->getAttribute('pos');
		$dtype = $dtok->getAttribute('dtype');
		$dpos = $dtok->getAttribute('dpos');
		$dform = $dtok->getAttribute('form');
		if ( $tokpos eq 'CONTR' ) { 
			$parenttype = "";
			my $setatt = XML::XPath::Node::Attribute->new('id',  'd-'.$nodeid.'-'.$dtoknr );
			$dtok->appendAttribute($setatt);
			$dtoknr++;
			if ( $dtype eq '' ) {
				my $setatt = XML::XPath::Node::Attribute->new('dtype',  'contraction');	
				$dtok->appendAttribute($setatt);
			};
			if ( $dpos eq 'clitic' ) {
				$parenttype = 'clitic';	
			} elsif ( $dpos eq 'contracted' ) {
				$parenttype = 'contraction';	
			} elsif ( $dpos eq '' ) {
				# Make any unmarked dtok of a contraction BASE by default
				my $setatt = XML::XPath::Node::Attribute->new('dpos',  'base');	
				$dtok->appendAttribute($setatt);
			};
			if ( $thisnode->getAttribute('type') eq '' && $parenttype ne '' ) {
				my $setatt = XML::XPath::Node::Attribute->new('type',  $parenttype);	
				$thisnode->appendAttribute($setatt);
			};
		} elsif ( $dtok->getAttribute('dertype') ne '' ) {
			if ( $dtype eq '' ) {
				my $setatt = XML::XPath::Node::Attribute->new('dtype',  'derivation');	
				$dtok->appendAttribute($setatt);
			};
		};
		
		$text = XML::XPath::Node::Text->new($dform);
		
		## Only add a dtok if we know what it is - for now, this mostly leaves out MWE
		if ( $dtok->getAttribute('dtype') ne '' ) {
			$dtok->appendChild($text);
		};
		
		my $setatt = XML::XPath::Node::Attribute->new('lemma',  $dlemma);	
		$dtok->appendAttribute($setatt);

		if ( $dform ) {
			my $setatt = XML::XPath::Node::Attribute->new('form',  $dform);	
			$dtok->appendAttribute($setatt);
		};

		$thisnode->appendChild($dtok);
	} else {
		# Treat a normal word
		
		( $nodeid = $wordid ) =~ s/w-//; $dtoknr = 1;
		$thisnode = $nodeset->get_node($nodeid);
		
		$orgword = $thisnode->string_value;
		$orgword =~ s/\n//g;
		$orgword =~ s/'/&#039;/g;
		
		if ( $orgword ne $word ) { 
			print "ERROR - input and output do not match <$wordid> input: $orgword (", $thisnode->getAttribute(), ") - tagged: $word ($tag)";
			# unlink($tagfile);
			#unlink($txtfile);
			exit;
		};
		
		# if ( $source ne "corpus" ) { $lemma = "?$lemma"; };

		# For contractions, kill the lemma if it is a + type lemma
		if ( $pos eq 'CONTR' && $lemma =~ /.\+./ ) {
			$lemma = "";
		};

		$setatt = XML::XPath::Node::Attribute->new('lemma',  $lemma);	
		if ( $thisnode ) { $thisnode->appendAttribute($setatt); }; # How can there be no node???

		if ( $source eq "corpus:1" ) {
			# This is a unique word - fully resolved
			$setatt = XML::XPath::Node::Attribute->new('tcnt',  "1" );	
			if ( $thisnode ) { $thisnode->appendAttribute($setatt); }; # How can there be no node???
		} else { 
			# This word is not existing, or not unique, flag it as unresolved
			( $stype, $scnt ) = split ( ":", $source );
			if ( $scnt == "" ) { $scnt = "0"; };
			$setatt = XML::XPath::Node::Attribute->new('tcnt',  $scnt );	
			if ( $thisnode ) { $thisnode->appendAttribute($setatt); }; # How can there be no node???
			if ( $tcnt == 0 ) { # only flag if the word is not in the training corpus
				$setatt = XML::XPath::Node::Attribute->new('res',  "no" );	
				if ( $thisnode ) { $thisnode->appendAttribute($setatt); }; # How can there be no node???
			};
		};
	
		@stoks = split ( "[+]=", $tag );
		$maintoken = shift(@stoks);
		
		@attpairs = split ( ';', $maintoken );
		foreach $attpair ( @attpairs ) {
			($key, $val) = split ( ':', $attpair );
			my $setatt = XML::XPath::Node::Attribute->new($key,  $val);	
			if ( $thisnode ) { $thisnode->appendAttribute($setatt); };
		};


		# Insert labels where appropriate
		if ( defined($labels{$tag.'@'.$word}) ) {
			while ( ( $lk, $lv ) = each ( %{$labels{$tag.'@'.$word}} ) ) {
				my $setatt = XML::XPath::Node::Attribute->new($lk,  $lv);	
				if ( $thisnode ) { $thisnode->appendAttribute($setatt); };
			};
		} elsif ( $labels{$pos.':@'.$lemma} ) {
			while ( ( $lk, $lv ) = each ( %{$labels{$pos.':@'.$lemma}} ) ) {
				my $setatt = XML::XPath::Node::Attribute->new($lk,  $lv);	
				if ( $thisnode ) { $thisnode->appendAttribute($setatt); };
			};
		} elsif ( $labels{'?'.$pos.':@'.$lemma} ) {
			while ( ( $lk, $lv ) = each ( %{$labels{'?'.$pos.':@'.$lemma}} ) ) {
				my $setatt = XML::XPath::Node::Attribute->new($lk,  $lv);	
				if ( $thisnode ) { $thisnode->appendAttribute($setatt); };
			};
		};
		
		$si = 0;
		foreach $stok ( @stoks ) {
			$dform = $partlist1[$si];
			$dlemma = $partlist2[$si];
			$dtok = XML::XPath::Node::Element->new('dtok');
			@attpairs = split ( ';', $stok );
			foreach $attpair ( @attpairs ) {
				($key, $val) = split ( ':', $attpair );
				if ( $key eq 'pos' ) { $pos = $val; };
				if ( $key eq 'form' ) {
					$form = $val;
				} elsif ( $key eq 'lemma' ) {
					$alemma = $val;
				} else {	
					my $setatt = XML::XPath::Node::Attribute->new($key,  $val);	
					$dtok->appendAttribute($setatt);
				};
			};		
			
			# Set the form
			if ( $form eq '' ) { $form = $words[$j]; };
	
			if ( $dlemma eq '' ) { # Contractions do not have a lemma for (some) their parts
				# $dlemma = "??"; 
			} elsif ( $dlemma eq '??' ) {
				$dlemma = "";
			};

			# if ( $source ne "corpus" && $source ne "contraction" ) { $dlemma = "?$dlemma"; };
			
			$text = XML::XPath::Node::Text->new($dform);
			$dtok->appendChild($text);
	
			my $setatt = XML::XPath::Node::Attribute->new('lemma',  $dlemma);	
			$dtok->appendAttribute($setatt);
	
			# print '-', 'dtok', $dform, $stok, $dlemma;
				
			$thisnode->appendChild($dtok);
			$si++;
			
		};
		$si = 0;
	};
};
close TAGFILE;

$allnodes = $xp->find("/");
$tmp = $allnodes->shift();
$taggedxml = $tmp->toString();

if ( $doctype ne '' ) { 
	$taggedxml =~ s/(<?xml[^>]+>)/\1\n$doctype/;
};

$taggedxml =~ s/'/&#039;/g; # Protect the quotes again
$taggedxml =~ s/ ="" / /g; # Remove empty features (which should not exist anyway)

if ( $debug || $test ) { 
	print " -- debugging, not saving. End result: \n\n$taggedxml"; 
	#unlink($tagfile);
	#unlink($txtfile);
	exit; 
};

# Print this back to the same file

print " - writing XML back to file $filename";
open FILE, ">$thisdir/$filename";
binmode ( FILE, ":utf8" );
print FILE $taggedxml;
close FILE;
