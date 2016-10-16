use Encode qw(decode encode);
use Time::HiRes qw(usleep ualarm gettimeofday tv_interval);
use HTML::Entities;
use XML::LibXML;
use Getopt::Long;

$scriptname = $0;

 GetOptions ( ## Command line options
            'debug' => \$debug, # debugging mode
            'force' => \$force, # tag even if already tagged
            'test' => \$test, # tokenize to string, do not change the database
            'linebreaks' => \$linebreaks, # tokenize to string, do not change the database
            'filename=s' => \$filename, # language of input
            'mtxtelm=s' => \$mtxtelm, # what to use as the text to tokenize
            );

$\ = "\n"; $, = "\t";

if ( $mtxtelm eq '' ) { $mtxtelm = 'text'; };

if ( $filename eq '' ) {
	$filename = shift;
};

if ( $filename eq '' ) {
	print " -- usage: xmltokenize.pl --filename=[fn]"; exit;
};

if ( !-e $filename ) {
	print " -- no such file $filename"; exit;
};

binmode ( STDOUT, ":utf8" );

$/ = undef;
open FILE, $filename;
binmode ( FILE, ":utf8" );
$rawxml = <FILE>;
close FILE;

if ( $rawxml eq '' ) {
	print " -- empty file $filename"; exit;
};

# Check if not already tokenized
if ( !$force && $rawxml =~ /<\/tok>/ ) {
	print "Already tokenized"; exit;
};

# We cannot have an XML tag span a line, so join them back on a single line
$rawxwl =~ s/<([^>]+)[\n\r]([^>]+)>/<\1 \2>/g;

# Check if this is valid XML to start with
$parser = XML::LibXML->new(); $doc = "";
eval {
	$doc = $parser->load_xml(string => $rawxml);
};
if ( !$doc ) { 
	print "Invalid XML in $filename"; 
	exit;
};

# Take off the header and footer (ignore everything outside of $mtxtelm)
if ( $rawxml =~ /(<$mtxtelm>|<$mtxtelm [^>]*>).*?<\/$mtxtelm>/gsmi ) { $tagtxt = $&; $head = $`; $foot = $'; }
else { print "No element <$mtxtelm>"; exit; };

if ( $linebreaks ) {
	if ( $debug ) {
		print "\n\n----------------\nBEFORE PARAGRAPHS\n----------------\n$tagtxt----------------\n";
	};

	# When so desired, interpret \r as <lb/> and \r\r as <p>
	$tagtxt =~ s/(<$mtxtelm>\s*|<$mtxtelm [^>]*>\s*)/\1<p><lb\/>/smi;
	$tagtxt =~ s/([\r\n]*<\/$mtxtelm>)/<\/p>\1/;
	$tagtxt =~ s/\n\n/<\/p>\n\n<p><lb\/>/g;
	$tagtxt =~ s/\n(?!<p>|\n|<\/text>)/\n<lb\/>/g; # This places <lb/> before tags that should not have one...
};

# Do some preprocessing
# decode will mess up encoded <> so htmlencode them
$tagtxt =~ s/&lt;/&amp;lt;/g;
$tagtxt =~ s/&gt;/&amp;gt;/g;
$tagtxt = decode_entities($tagtxt);
# Protect HTML Entities so that they do not get split
$tagtxt =~ s/(&[^ \n\r&]+;)/xx\1xx/g;
$tagtxt =~ s/&(?![^ \n\r&]+;)/xx&amp;xx/g;

# <note> elements should not get tokenized
# And neither should <desc> or <gap>
# Take them out and put them back later
$notecnt = 0;
while ( $tagtxt =~ /<(note|desc|gap|pb|fw|app)[^>]*(?<!\/)>.*?<\/\1>/gsmi )  {
	$notetxt = $&; $leftc = $`;
	$notes[$notecnt] = $notetxt; $newtxt = substr($leftc, -50).'#'.$notetxt;
	if ( $oldtxt eq $newtxt ) { 
		if ( $lc++ > 5 ) {
			print "Oops - trying to remove notes but getting into an infinite loop (or at least seemingly so).";
			print "before: $oldtxt"; 
			print "now: $newtxt"; 
			exit; 
		};
	};
	$oldtxt = $newtxt;
	$tagtxt =~ s/\Q$notetxt\E/<ntn $notecnt\/>/;
	$notecnt++;
};	

if ( $debug ) {
	print "\n\n----------------\nBEFORE TOKENIZING\n----------------\n$tagtxt----------------\n";
};

# Now actually tokenize
# Go line by line to make it faster
foreach $line ( split ( "\n", $tagtxt ) ) {

	# Protect XML tags
	$line =~ s/<([a-zA-Z0-9]+)>/<\1%%>/g;
	while ( $line =~ /<([^>]+) +/ ) {
		$line =~ s/<([^>]+) +/<\1%%/g;
	};
	
	# Protect MWE and other space-crossing or punctuation-including toks
	# When there is a parameter folder with a ptok.txt file
	if ( $params && -e "$params/ptoks.txt" ) {
	};
	
	$line =~ s/^(\s*)//; $pre = $1;
	$line =~ s/(\s*)$//; $post = $1;
	
	# Put tokens around all whitespaces
	if ( $line ne '' ) { $line = '<tokk>'.$line.'</tok>'; };

	$line =~ s/(\s+)/<\/tok>\1<tokk>/g;

	# Remove toks around only XML tags
	$line =~ s/<tokk>((<[^>]+>)+)<\/tok>/\1/g;

	$line =~ s/%%/ /g;

	# Move tags between punctuation and </tok> out 
	$line =~ s/(\p{isPunct})(<[^>]+>)<\/tok>/\1<\/tok>\2/g;
	# Move tags between <tok> and punctuation out 
	$line =~ s/<tokk>(<[^>]+>)(\p{isPunct})/\1<tokk>\2/g;

	if ( $debug ) {
		print "BP|| $line\n";
	};

	# Split off the punctuation marks
	while ( $line =~ /(?<!<tokk>)(\p{isPunct}<\/tok>)/ ) {
		$line =~ s/(?<!<tokk>)(\p{isPunct}<\/tok>)/<\/tok><tokk>\1/g;
	};
	while ( $line =~ /(<tokk[^>]*>)(\p{isPunct})(?!<\/tok>)/ ) {
		$line =~ s/(<tokk[^>]*>)(\p{isPunct})(?!<\/tok>)/\1\2<\/tok><tokk>/g;
	};
	if ( $debug ) {
		print "IP|| $line\n";
	};

	# This should be repeated after punctuation
	# First remove empty <tok>
	$line =~ s/<tokk><\/tok>//g;
	$line =~ s/<tokk>(<[^>]+>)<\/tok>/\1/g;
	# Move beginning tags at the end out 
	$line =~ s/(<[^>\/ ]+ [^>\/]*>)<\/tok>/<\/tok>\1/g;
	if ( $debug ) {
		print "IP|| $line\n";
	};
	# Move end tags at the beginning out 
	$line =~ s/<tokk>(<\/[^>]+>)/\1<tokk>/g;

	# Move notes out 
	$line =~ s/<tokk>(<ntn [^>]+>)/\1<tokk>/g;
	$line =~ s/(<ntn [^>]+>)<\/tok>/<\/tok>\1/g;

	# Always move <p> out
	$line =~ s/<tokk>(<p [^>]+>)/\1<tokk>/g;
	$line =~ s/(<p [^>]+>)<\/tok>/<\/tok>\1/g;

	if ( $debug ) {
		print "AP|| $line\n";
	};
	
	#print $line; 
	
	
	# Go through all the tokens
	while ( $line =~ /<tokk>(.*?)<\/tok>/ ) {
		$a = ""; $b = ""; undef(%added);
		$m = $1; $n = $&;

		if ( $debug ) {
			print "TOK | $m";
		};
		
		# Check whether <tok> is valid XML
		( $chtok = $n ) =~ s/tokk/tok/g;
		$parser = XML::LibXML->new(); $tokxml = "";
		eval { $tokxml = $parser->load_xml(string => $chtok); };
		
		# Check unmatched start tags
		$chkcnt = 0;
		while ( !$tokxml && ( $m =~ /^<([^>]+)>/ || $m =~ /<([^>]+)>$/) ) { 
			if ( $chkcnt++ > 15 )  { print "Oops - infinite loop on $chtok"; exit; };
			if ( $m =~ /^<([^>]+)>/ ) {
				# Leftmost 
				$tm = $&; $ti = $1; $rc = $';
				( $tn = $ti ) =~ s/ .*//;
				if ( $ti =~ /\/$/ || $ti =~ /^\// || $rc !~ /^((?<!<$tn ).)+<\/$tn>/ ) { 
					# Move out
					$m =~ s/^\Q$tm\E//;
					$a .= $tm;
				} else {
					# Mark as non-movable
					$m = "#".$m;
				};
			} elsif ( $m =~ /<([^>]+)>$/ ) {
				# Rightmost
				$tm = $&; $ti = $1; $lc = $`;
				( $tn = $ti ) =~ s/ .*//;
				( $tv = $ti ) =~ s/^[^ ]+ //;
				if ( $ti =~ /\/$/ || $ti !~ /^\// || $lc !~ /<$tn [^>\/]*>(.(?!<\/$tn>))+$/ ) { 
					# Move out
					$m =~ s/\Q$tm\E$//;
					$b = $tm.$b;
				} else {
					# Mark as non-movable
					$m = $m."#";
				};
			};
			if ( $debug ) {
				print "CTK1 | ($a) $m ($b)";
			};
			# Check whether <tok> is valid XML
			$parser = XML::LibXML->new(); $tokxml = "";
			eval { $tokxml = $parser->load_xml(string => "<tok>$m</tok>"); };
			$chcnt++;
		};
		$m =~ s/^#|#$//g;
		
		# If there are unmatched tags in the middle...
		if ( !$tokxml ) {
			$chkcnt = 0; $checkm = $m; $onto = "";
			while ( $checkm =~ /<([^\/ ]+) (.(?!<\/\1>))+$/ ) {
				if ( $chkcnt++ > 15 )  { print "Oops - infinite loop on $chtok"; exit; };
				$tn = $1;
				$onto = "<\/$tn>".$onto;
				$b .= "<$tn rpt=\"1\">";
				if ( $debug ) {
					print "CTK2 | ($a) $m/$onto ($b)";
				};
				$chcnt++; $checkm = $m.$onto;
			}; $m = $m.$onto;
			# Check whether <tok> is valid XML
			$parser = XML::LibXML->new(); $tokxml = "";
			eval { $tokxml = $parser->load_xml(string => "<tok>$m</tok>"); };
			$chcnt++;
		};
		if ( !$tokxml ) {
			$chkcnt = 0;
			while ( $m =~ /<\/([^>]+)>/g ) {
				if ( $chkcnt++ > 15 )  { print "Oops - infinite loop on $chtok"; exit; };
				$lc = $`; $tn = $1;
				if ( $lc !~ /<$tn [^>\/]*>(.(?!<\/$tn>))+$/ ) {
					$a .= "<\/$tn>";
					$m = "<$tn rpt=\"1\">".$m;
				};
				if ( $debug ) {
					print "CTK3 | ($a) $m ($b)";
				};
				$chcnt++;
			};
		};

		
		# Finally, look at the @form and @fform and @nform
		$fts = "";
		if ( $m =~ /^(.+)\|=([^<>]+)$/ ) {
			$m = $1; $fts .= " nform=\"$2\"";
		};
		if ( $m =~ /^(.+)\|\|([^<>]+)$/ ) {
			$m = $1; $fts .= " fform=\"$2\"";
		};
		if ( $m =~ /<[^>]+>/ ) {
			$frm = $m; $ffrm = "";
			$frm =~ s/<del.*?<\/del>//g; # Delete deleted texts
			$frm =~ s/-<lb[^>]*\/>//g; # Delete hyphens before word-internal hyphens
			if ( $frm eq "" ) { $frm = "--"; };
			
			# Deal with <ex> or <expan>
			if ( $frm =~ /<ex/ ) {
				if ( $frm =~ /<\/ex>/ ) { 
					$frm =~ s/<\/?expan [^>]*>//g; 
				}; # With <ex> - <expan> is no longer an expanded stretch
				$ffrm = $frm;
				$frm =~ s/<ex.*?<\/ex[^>]*>//g; # Delete expansions in form
			};

			# Remove all (other) tags
			$frm =~ s/<[^>]+>//g;
			$frm =~ s/"/&quot;/g;
			$ffrm =~ s/<[^>]+>//g;
			$ffrm =~ s/"/&quot;/g;

			if ( $m ne $frm ) { $fts .= " form=\"$frm\""; };
			if ( $ffrm ne '' && $frm ne $ffrm ) { $fts .= " fform=\"$ffrm\""; };
		};

		# Move <lb/> out from beginning of <tok>
		if ( $m =~ /^<lb\/>/ ) {
			$m = $'; $a .= $&;
		};

		if ( $debug ) {
			print "TKK | $m";
		};

		$line =~ s/\Q$n\E/$a<tok$fts>$m<\/tok>$b/;

	};

	# Move tags at the rim out of the tok
	$line =~ s/(<tok[^>]*>)(<([a-z0-9]+) [^>]*>)((.(?!<\/\3>))*.)<\/\3><\/tok>/\2\1\4<\/tok><\/\3>/gi;
	# This has to be done multiple time in principle since there might be multiple
	$line =~ s/(<tok[^>]*>)(<([a-z0-9]+) [^>]*>)((.(?!<\/\3>))*.)<\/\3><\/tok>/\2\1\4<\/tok><\/\3>/gi;

	# Split off the punctuation marks again (in case we moved out end tags)
	while ( $line =~ /(?<!<tok>)(\p{isPunct}<\/tok>)/ ) {
		$line =~ s/(?<!<tok>)(\p{isPunct}<\/tok>)/<\/tok><tok>\1/g;
	};
	while ( $line =~ /(<tok[^>]*>)(\p{isPunct})(?!<\/tok>)/ ) {
		$line =~ s/(<tok[^>]*>)(\p{isPunct})(?!<\/tok>)/\1\2<\/tok><tok>/g;
	};

	# Unprotect all MWE and other space-crossing or punctuation-including tokens
	while ( $line =~ /x#\{x[^\}]*%/ ) {
		$line =~ s/(x#\{x[^\}]*)%/\1 /g;
	};
	$line =~ s/x#\{x//g; $line =~ s/x\}#x//g;

	if ( $debug ) {
		print "LE|| $line\n";
	};

	# if ( $linebreaks && $line =~ /<tok>/ ) { $pre .= "<lb/> "; };
	$teitext .= $pre.$line.$post."\n";
};

# Join some non-splittable sequences		
$teitext =~ s/<tok>\[<\/tok><tok>\.<\/tok><tok>\.<\/tok><tok>\.<\/tok><tok>\]<\/tok>/<tok>[...]<\/tok>/g; # They can also be inside a tok
$teitext =~ s/<tok>\(<\/tok><tok>\.<\/tok><tok>\.<\/tok><tok>\.<\/tok><tok>\)<\/tok>/<tok>(...)<\/tok>/g; # They can also be inside a tok
$teitext =~ s/<tok>\.<\/tok><tok>\.<\/tok><tok>\.<\/tok>/<tok>...<\/tok>/g; # They can also be inside a tok

$teitext =~ s/xx(&[^ \n\r&]+;)xx/\1/g; # Unprotect HTML Characters


# A single capital with a dot is likely a name
$teitext =~ s/<tok>([A-Z])<\/tok><tok>\.<\/tok>/<tok>\1.<\/tok>/g; # They can also be inside a tok

while ( $teitext =~ /<ntn (\d+)\/>/ ) {
	$notenr = $1; $notetxt = $notes[$notenr]; 
	$teitext =~ s/<ntn (\d+)\/>/$notetxt/;
};


$xmlfile = $head.$teitext.$foot;

# Now - check if this turned into valid XML
$parser = XML::LibXML->new(); $doc = "";
eval {
	$doc = $parser->load_xml(string => $xmlfile);
};
if ( !$doc ) { 
	print "XML got messed up - saved to /tmp/wrong.xml\n"; 
	open FILE, ">/tmp/wrong.xml";
	binmode ( FILE, ":utf8" );
	print FILE $xmlfile;
	close FILE;
	
	$err = `xmlwf /tmp/wrong.xml`;
	if ( $err =~ /^(.*?):(\d+):(\d+):/ ) {
		$line = $2; $char = $3;
		print "First XML Error: $err";
		print `cat /tmp/wrong.xml | head -n $line | tail -n 1`;
	};
	
	exit; 
};

# One last thing we need to do is treat <tok> inside <del>
	foreach $ttnode ($doc->findnodes("//del//tok")) {
		$ttnode->setAttribute('form', "--");
	}; 

$xmlfile = $doc->toString;

if ( $test ) { 
	print  $xmlfile;
} else {
	open FILE, ">$filename";
	print FILE $xmlfile;
	close FILE;

	( $renum = $scriptname ) =~ s/xmltokenize/xmlrenumber/;

	print "$filename has been tokenized - renumbering tokens now";
	# Finally, run the renumber command over the same file
	$cmd = "/usr/bin/perl $renum --filename=$filename";
	# print $cmd;
	`$cmd`;
};

