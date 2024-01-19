use Encode qw(decode encode);
use Time::HiRes qw(usleep ualarm gettimeofday tv_interval);
use HTML::Entities;
use XML::LibXML;
use utf8;
use Data::Dumper;
use Getopt::Long;
use POSIX qw(strftime);
use Cwd 'abs_path';

# Script to tokenize XML files inline; splits on spaces, and splits off UTF punctuation chars at the beginning and end of a token
# Splits existing XML tags if they get broken by the tokens
# Can split sentences as well, where a sentence boundary is a sentence-final character as its own token
# (c) Maarten Janssen, 2014

$scriptname = $0;

 GetOptions ( ## Command line options
            'debug' => \$debug, # debugging mode
            'verbose' => \$verbose, # debugging mode
            'force' => \$force, # tag even if already tagged
            'test' => \$test, # tokenize to string, do not change the database
            'keepns' => \$keepns, # do not kill the xmlns
            'nobu' => \$nobu, # do not create a backup
            'nonl' => \$nonl, # do not introduce newlines
            'breaks' => \$addbreaks, # add breaks before every sentence
            'noinner' => \$noinner, # Do not keep inner-token punctuation marks
            'inner=s' => \$inner, # ... except for these
            'pelms=s' => \$pelms, # elements that should always become blocks (start new sentence)
            'notok=s' => \$notoks, # elements that should not be tokenized (note)
            'flush=s' => \$flush, # elements that should receive a nl before
            'linebreaks' => \$linebreaks, # tokenize \n to string, do not change the XML
            'filename=s' => \$filename, # language of input
            'mtxtelm=s' => \$mtxtelm, # what to use as the text to tokenize
            'sent=i' => \$sentsplit, # split into sentences (1=yes, 2=only)
            'emptys' => \$emptys, # keep sentences as empty elements with this at
            'emptyatt' => \$emptyatt, # empty sentence attribute
            );

$\ = "\n"; $, = "\t";

if ( $mtxtelm eq '' ) { $mtxtelm = 'text'; };
if ( $debug ) { $verbose = 1; print "Tokenizing $mtxtelm"; };

if ( $filename eq '' ) {
	$filename = shift;
};

$pelms = "$pelms,div,head,p,u,speaker"; $sep = "";
$ptreg = $pelms; $ptreg =~ s/^,|,$//g;  $ptreg =~ s/,+/\|/g; 
foreach $pelm ( split(",", $pelms) ) {
	$pnts{$pelm} = 1;
	if ( $pelm) { $preg .= $sep.$pelm; $sep = "|"; };
}; $preg = "<($preg)[ >\/]";

$notoktype = "note|desc|gap|pb|fw|rdg";
if ( $notoks ) {
	$notoktype = $notoks; $notoktype =~ s/^,|,$//g;  $notoktype =~ s/,+/\|/g; 
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

if ( $rawxml =~ /<\/s>/ && $sentsplit ) {
	print "Already split into sentences - not splitting";
	$sentsplit = 0;
};

# Check if not already tokenized
if ( $rawxml =~ /<\/tok>/ ) {
	if ( $force ) {
		# Forcing, so just go on
	} elsif ( $sentsplit ) {
		$sentsplit = 2;
	} else {
		print "Already tokenized"; exit;
	};
};

# Kill the xmlns
if ( !$keepns ) {
	$rawxml =~ s/ xmlns=/ xmlnsoff=/g;
};


# To avoid problems - put a new line after each </s></p>
# if ( !$nonl ) {
# 	$rawxml =~ s/(<\/(p)>)(?!\n)/\1\n/g;
# 	$rawxml =~ s/(?!\n)(<\/(p)[ >]>)/\n\1/g;
# };

# We cannot have an XML tag span a line, so join them back on a single line
# $rawxwl =~ s/<([^>]+)[\n\r]([^>]+)>/<\1 \2>/g;

# Check if this is valid XML to start with
$parser = XML::LibXML->new(); $doc = "";
eval {
	$doc = $parser->load_xml(string => $rawxml);
};
if ( !$doc ) { 
	print "Invalid XML in $filename\n$@"; 
	exit;
};


# Take off the header and footer (ignore everything outside of $mtxtelm)
if ( $rawxml =~ /(<$mtxtelm>|<$mtxtelm [^>]*>).*?<\/$mtxtelm>/smi ) { $tagtxt = $&; $head = $`; $foot = $'; }
else { print "No element <$mtxtelm>"; exit; };


# We need to remove linebreaks in the middle of a tag
$lc = 0; while ( $tagtxt =~ /<([^>\n\r]*?)[\n\r]+\s*/g && $lc++ < 5) {
	# print $tagtxt;
	$tagtxt =~ s/<([^>\n\r]*?)[\n\r]+\s*/<\1 /g;
};

# Add newlines where asked
if ( $flush ) {
	foreach $pelm ( split(",", $flush) ) {
		if ( $pelm ) { $flreg .= $sep.$pelm; $sep = "|"; };
	}; $flreg = "(?<!\n)<($flreg)[ >\/]";
	$tagtxt =~ s/$flreg/\n$&/g;
};


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

if ( $sentsplit != 2 ) {
	# There are some element that should never be inside a word - such as paragraphs. So add whitespace inside those to prevent errors
	# $tagtxt =~ s/(<\/($ptreg)>)(<($ptreg))(?=[ >])/\1\n\3/g;

	# Deal with |~ encode line endings (from with page-by-page files)
	$tagtxt =~ s/\s*\|~\s*((<[pl]b[^>]*>\s*?)*)\s*/\1/gsmi;

	# Do some preprocessing
	# decode will mess up encoded <> so htmlencode them
	$tagtxt =~ s/&amp;/xxAMPxx/g;
	$tagtxt =~ s/&lt;/xxLTxx/g;
	$tagtxt =~ s/&gt;/xxGTxx/g;
	# $tagtxt = decode_entities($tagtxt);

	# Protect HTML Entities so that they do not get split
	# TODO: This should not exist anymore, right?
	$tagtxt =~ s/(&[^ \n\r&]+;)/xx\1xx/g;
	$tagtxt =~ s/&(?![^ \n\r&;]+;)/xx&amp;xx/g;
};

# <note> elements should not get tokenized
# And neither should <desc> or <gap>
# Take them out and put them back later
# TODO: this goes wrong with nested notes (which apt. are allowed in TEI)
$notecnt = 0;
while ( $tagtxt =~ /<($notoktype)[^>]*(?<!\/)>.*?<\/\1>/gsmi )  {
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
	$tagtxt =~ s/\Q$notetxt\E/<ntn n="$notecnt"\/>/;
	if ( $debug ) {
		print "Removing $notoktype : $notetxt";
	};
	$notecnt++;
};	
# Also do XML comments 
while ( $tagtxt =~ /<!--.*?-->/gsmi )  {
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
	$tagtxt =~ s/\Q$notetxt\E/<ntn n="$notecnt"\/>/;
	$notecnt++;
};	


if ( $debug ) {
	print "\n\n----------------\nBEFORE TOKENIZING\n----------------\n$tagtxt----------------\n";
};


# There are some element that should never be broken
if ( !$nonl && $preg ) {
	if ( $debug ) { print "Adding newlines to: "; $preg; };
	$tagtxt =~ s/(?<![\n\r])($preg)/\n\1/g;
};

# Now actually tokenize
# Go line by line to make it faster
if ( $sentsplit != 2 ) {
	@textlines = split ( "\n", $tagtxt );
	$totl = scalar @textlines;
	if ( $verbose ) { print "$tokl lines to tokenize";  };
	$lcnt = 0;
	foreach $line ( @textlines ) {

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

		# <split/> being a non-TEI indication to split - should lead to two tokens
		$line =~ s/<split\/>/<\/tok><c form=" "><split\/><\/c><tokk>/g;

		# Remove toks around only XML tags
		$line =~ s/<tokk>((<[^>]+>)+)<\/tok>/\1/g;

		$line =~ s/%%/ /g;

		# Move tags between punctuation and </tok> out 
		$line =~ s/(\p{isPunct})(<[^>]+>)<\/tok>/\1<\/tok>\2/g;
		# Move tags between <tok> and punctuation out 
		$line =~ s/<tokk>(<[^>]+>)(\p{isPunct})/\1<tokk>\2/g;

		if ( $debug ) {
			print "BP ".$lcnt++." - ".int(($lcnt/$totl)*100)."% || $line\n";
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
		
			# Correct unmatched tags
			$chkcnt = 0;
			while ( !$tokxml && ( $m =~ /^<([^>]+)>/ || $m =~ /<([^>]+)>$/) ) { 
				if ( $chkcnt++ > 15 )  { print "Oops - infinite loop on $chtok"; exit; };
				if ( $m =~ /^<([^>]+)\/>/ ) {
					# Leftmost empty
					$a .= $&;
					$m = $';
				} elsif ( $m =~ /<([^>]+)\/>$/ ) {
					# Rightmost empty
					$b = $&.$b;
					$m = $`;
				} elsif ( $m =~ /^<\/([^>]+)>/ ) {
					# Leftmost closing
					$a .= $&;
					$m = $';
				} elsif ( $m =~ /<[^\/>][^>]*>$/ ) {
					# Rightmost opening
					$b .= $&.$b;
					$m = $`;
				} elsif ( $m =~ /^<([^\/>][^>]*)>/ ) {
					# Leftmost opening without close
					# TODO: This is not a complete check
					$tm = $&; $ti = $1; $rc = $';
					( $tn = $ti ) =~ s/ .*//; $tn =~ s/^\///; # tag name
					if ( $rc !~ /^((?<!<$tn ).)+<\/$tn>/ ) { 
						# Move out
						$m =~ s/^\Q$tm\E//;
						$a .= $tm;
					} else {
						# Mark as non-movable
						$m = "#".$m;
					};
				} elsif ( $m =~ /<\/([^>]+)>$/ ) {
					# Rightmost closing without open
					# TODO: This is not a complete check
					$tm = $&; $ti = $1; $lc = $`;
					( $tn = $ti ) =~ s/ .*//; $tn =~ s/^\///; # tag name
					( $tv = $ti ) =~ s/^[^ ]+ //;
					if ( $lc !~ /<$tn [^>\/]*>(.(?!<\/$tn>))+$/ ) { 
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
			
				# Count all the tags
				undef(%tgchk); # Clean count hash first
				while ( $m =~ /<([^ >]+)([^>]*)>/g ) {
					$tn = $1; $ta = $2;
					if ( $tn =~ /^\// ) {
						$tn = $';
						if ( $tgchk{$tn} > 0 ) {
							$tgchk{$tn}--;
						} else {
							# Closing before opening
							$a .= "<\/$tn>";
							$m = "<$tn rpt=\"1\">".$m;
						};
					} elsif ( $ta =~ /\/$/ || $tn =~ /\/$/ ) {
						# Ignore
					} else {
						$tgchk{$tn}++;
					};										
				}; 
				
				# Repair unpaired tags
				while ( ( $tn, $val ) = each ( %tgchk ) ) {
					# TODO: these should be added in the right order...
					$onto = "";
					if ( $val < 0 ) {
						for ( $i=0; $i>$val; $i-- ) {
							$a .= "<\/$tn>";
							$m = "<$tn rpt=\"1\">".$m;
							if ( $debug ) {
								print "CTK2 | ($a) $m+$onto ($b)";
							};
						};
					} elsif ( $val > 0  ) {
						for ( $i=0; $i<$val; $i++ ) {
							$onto = "<\/$tn>".$onto;
							$b .= "<$tn rpt=\"1\">";
							if ( $debug ) {
								print "CTK2 | ($a) $m+$onto ($b)";
							};
						};
					};
				};
				$m = $m.$onto;
				
				# Check whether <tok> is valid XML
				$parser = XML::LibXML->new(); $tokxml = "";
				eval { $tokxml = $parser->load_xml(string => "<tok>$m</tok>"); };
			};

		
			# Finally, look at the @form and @fform and @nform
			$fts = "";
			if ( $m =~ /^(.+)\|=([^<>]+)$/ ) { # echa|=hecha -> normalization
				$m = $1; $fts .= " nform=\"$2\"";
			};
			if ( $m =~ /^(.+)\|\|([^<>]+)$/ ) { # q||que -> expansion
				$m = $1; $fts .= " fform=\"$2\"";
			};
			if ( $m =~ /<[^>]+>/ ) {
				$frm = $m; $ffrm = "";
				$frm =~ s/<del.*?<\/del>//g; # Delete deleted texts
				$frm =~ s/-<lb[^>]*\/>//g; # Delete hyphens before word-internal hyphens
				if ( $frm eq "" ) { $frm = "--"; };
			
				# Deal with expansions
				if ( $frm =~ /<ex/ || $frm =~ /<am/ ) {
					if ( $frm =~ /<\/ex>/ ) { 
						$frm =~ s/<\/?expan [^>]*>//g; 
					}; # With <ex> - <expan> is no longer an expanded stretch
					$ffrm = $frm;
					$frm =~ s/<ex.*?<\/ex[^>]*>//g; # Delete expansions in form
					$ffrm =~ s/<am.*?<\/am[^>]*>//g; # Delete abrrev markers in fform
				};

				# Remove all (other) tags from @form
				$frm =~ s/<[^>]+>//g;
				$frm =~ s/"/&quot;/g;
				$ffrm =~ s/<[^>]+>//g;
				$ffrm =~ s/"/&quot;/g;
				# These appear if there are &gt; in the original
				$frm =~ s/>/&gt;/g;
				$frm =~ s/</&lt;/g;
				$ffrm =~ s/>/&gt;/g;
				$ffrm =~ s/</&lt;/g;

				if ( $m ne $frm ) { $fts .= " form=\"$frm\""; };
				if ( $ffrm ne '' && $frm ne $ffrm ) { $fts .= " fform=\"$ffrm\""; };
			};

			# Move <lb/> out from beginning of <tok> - should be redundant
			if ( $m =~ /^<lb[^>]*\/>/ ) {
				$m = $'; $a .= $&;
			};

			if ( $debug ) {
				$mo = "";   $mo1 = ""; $mo2 = "";
				if ( $a ne ""  ) { $mo1 = "($a)"; };
				if ( $b ne "" ) { $mo2 = "($b)"; };
				print "TKK | $mo1 $m $mo2";
			};

			$line =~ s/\Q$n\E/$a<tok$fts>$m<\/tok>$b/;

		};

		# Move tags at the rim out of the tok
		$line =~ s/(<tok[^>]*>)(<([a-z0-9]+) [^>]*>)((.(?!<\/\3>))*.)<\/\3><\/tok>/\2\1\4<\/tok><\/\3>/gi;
		# This has to be done multiple time in principle since there might be multiple
		$line =~ s/(<tok[^>]*>)(<([a-z0-9]+) [^>]*>)((.(?!<\/\3>))*.)<\/\3><\/tok>/\2\1\4<\/tok><\/\3>/gi;

		if ( $noinner || $inner ) {
			# Split off the punctuation marks now (splitting tokens)
			$tryline = $line;
			@todo = ();
			while ( $tryline =~ /(<tok[^<>]*>)(.*?)(<\/tok>)/g ) {
				$tokp = $&;
				push(@todo, $tokp);
			};
			if ( $inner ) {
				if ( $inner eq 'eng' ) {
					$inner = "’'-";
				};
				$noth = "|[$inner]";
			};
			foreach $tokp ( @todo ) {
				if ( decode_entities($tokp) =~ /<tok[^>]*>.*?(\p{isPunct}).*?<\/tok>/ ) { 
					$xtok = $parser->load_xml(string => $tokp); 
					foreach $tn ( $xtok->findnodes("//*/text()") ) {
						$tv = decode_entities($tn); 
						$tv =~ s/(?!&[^;]+;$noth)(\p{isPunct})/xxTBxx\1xxTBxx/g;
						$tn->setData($tv); 
					};
					$newtok = decode_entities($xtok->toString);
					$newtok =~ s/<\?.*?\?>\n?//g;
					$newtok =~ s/xxTBxx/<\/tok><tok>/g;
					$newtok =~ s/<tok><\/tok>//g;
					if ( decode_entities($tokp) ne $newtok ) { 
						if ( $debug ) { print " -- Split: $tokp => $newtok"; };
						$tryline =~ s/\Q$tokp\E/$newtok/;
					};
				};
			};
			# Check that we still have valid XML before using the line
			eval { $tryxml = $parser->load_xml(string => $tryline); };
			if ( $tryxml ) { $line = $tryline; }
			elsif ( $debug ) { print "-- Inner splitting leading to incorrect XML: $tryline"; };
		};
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

	$teitext =~ s/xx(&(?!xx)[^ \n\r&]+;)xx/\1/g; # Unprotect HTML Characters
	$teitext =~ s/xxAMPxx/&amp;/g; # Undo xxAMPxx
	$teitext =~ s/xxLTxx/&lt;/g; # Undo xxAMPxx
	$teitext =~ s/xxGTxx/&gt;/g; # Undo xxAMPxx

	# A single capital with a dot is likely a name
	$teitext =~ s/<tok>([A-Z])<\/tok><tok>\.<\/tok>/<tok>\1.<\/tok>/g; # They can also be inside a tok

} else {
	$teitext = $tagtxt;
};

	if ( $debug ) {
		print "\n\n----------------\nBEFORE SENTENCES\n----------------\n$teitext----------------\n";
	};

if ( $sentsplit ) {
	# Now - split into sentences; 
	if ( $addbreaks ) { $lb = "\n"; };

	# Start by making a <s> inside each <p> or <head>, fallback to <div>, or else just the outer xml (<text>) 
	$teitext =~ s/(<($ptreg)(?=[ >])[^<>]*>)/\1$lb<s\/>/g;
	
	$teitext =~ s/(<tok[^>]*>[.?!]<\/tok>)(\s*)/\1\2$lb<s\/>/g; 

	# Remove quotation marks into the sentence
	$teitext =~ s/($lb<s\/>)(<tok[^>]*>["']<\/tok>)/\2\1/g; 

	# Remove <s/> before more breaks - ?! etc.
	$teitext =~ s/$lb<s\/>(<tok[^>]*>[.?!]<\/tok>)/\1/g; 

	$teitext =~ s/<s\/>(<\/($ptreg))/\1/g; 
	
	# In case the splitting messed up the XML, undo
	$parser = XML::LibXML->new(); $tmp = "";
	eval {
		$tmp = $parser->load_xml(string => $teitext);
	};
		
};

# Put the notes back
while ( $teitext =~ /<ntn n="(\d+)"\/>/ ) {
	$notenr = $1; $notetxt = $notes[$notenr]; 
	$notecode = $&;
	$teitext =~ s/\Q$notecode\E/$notetxt/;
};


$xmlfile = $head.$teitext.$foot;


# Now - check if this turned into valid XML
$parser = XML::LibXML->new(); $doc = "";
eval {
	$doc = $parser->load_xml(string => $xmlfile);
};

if ( !$doc ) { 
	if ( -w "tmp" ) { 
		$wrongxml = "tmp/wrong.xml";
	} else {
		$wrongxml = "/tmp/wrong.xml";
	};
	print "XML of $filename got messed up - saved to $wrongxml\n"; 
	open FILE, ">$wrongxml";
	binmode ( FILE, ":utf8" );
	print FILE $xmlfile;
	close FILE;
	
	$err = `xmlwf $wrongxml`;
	if ( $err =~ /^(.*?):(\d+):(\d+):/ ) {
		$line = $2; $char = $3;
		print "First XML Error: $err";
		print `cat /tmp/wrong.xml | head -n $line | tail -n 1`;
	};
	
	exit; 
};

# Move tokens into sentences
if ( $sentsplit && !$emptys ) {

	if ( !$emptyatt ) { $emptyatt = "sameAs"; };

	# Move the tokens inside the sentences
	foreach $sent ( $doc->findnodes("//text//s" ) ) {

		$ptype = $sent->parentNode->nodeName;
		while ( !$sent->nextSibling() && !$pnts{$type} ) {
			# Move out if the <s/> ended up at the end of a node
			$sent->parentNode->parentNode->insertAfter($sent, $sent->parentNode);
			$ptype = $sent->parentNode->nodeName;
		};

		while ( $sib = $sent->nextSibling() ) {
			if ( $sib->nodeType == 1 && ( $sib->nodeName eq 's' || $sib->findnodes(".//s") ) ) { 
				last; 
			};
			$sent->addChild($sib);
		};
	};

	foreach $sent ( $doc->findnodes("//text//s[not(.//tok)]" ) ) {
		# Check whether empty nodes are not redundant (with the next tok under an s)
		$tmp = $sent->findnodes("./following::tok");
		if ( $tmp ) {
			$nexttok = $tmp->item(0);
			$tmp = $nexttok->findnodes("./ancestor::s");
			if ( $tmp ) {
				# Next token is under a <s> - redundant
				$sent->parentNode->removeChild($sent);
			} else {
				# Empty tok next - leave be
			};
		} else {
		};
	};
	
};

# One last thing we need to do is treat <tok> inside <del>
foreach $ttnode ($doc->findnodes("//del//tok")) {
	$ttnode->setAttribute('form', "--");
}; 


if ( $sentsplit == 2 ) {
	$actiontxt = "split into sentences";
} elsif ( $sentsplit == 1 ) {
	$actiontxt = "tokenized and split into sentences";
} else {
	$actiontxt = "tokenized";
};


# Add a revisionDesc to indicate the file was tokenized
$revs = makenode($doc, "/TEI/teiHeader/revisionDesc");
if ( $revs ) {
	$revnode = XML::LibXML::Element->new( "change" );
	$revs->addChild($revnode);
	$when = strftime "%Y-%m-%d", localtime;
	$revnode->setAttribute("who", "xmltokenize");
	$revnode->setAttribute("when", $when);
	$revnode->appendText("$actiontxt using xmltokenize.pl");
};

$xmlfile = $doc->toString;

if ( $sentsplit ) {
	$xmlfile =~ s/(<s(?=[ >])[^<>]*>)(\s+)/\2\1/gsmi;
	$xmlfile =~ s/(\s+)(<\/s>)/\2\1/gsmi;
};

if ( $test ) { 
	print  $xmlfile;
} else {

	# Make a backup of the file
	if ( !$nobu ) {
		( $buname = $filename ) =~ s/xmlfiles.*\//backups\//;
		$date = strftime "%Y%m%d", localtime; 
		$buname =~ s/\.xml/-$date.nt.xml/;
		$cmd = "/bin/cp $filename $buname";
		`$cmd`;
	};
	
	open FILE, ">$filename";
	print FILE $xmlfile;
	close FILE;

	$fullfilename = abs_path($filename); # Send the full path to the renumber script 

	( $renum = $scriptname ) =~ s/xmltokenize/xmlrenumber/;

	print "$filename has been $actiontxt - renumbering tokens now";
	# Finally, run the renumber command over the same file
	$cmd = "/usr/bin/perl $renum --filename=$fullfilename";
	# print $cmd;
	`$cmd`;
};

sub makenode ( $xml, $xquery ) {
	my ( $xml, $xquery ) = @_;
	if ( !$xquery ) { return; };
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
			if ( $parnode ) { $parnode->addChild($newchild); };
			
		} else {
			print "Failed to find or create node: $xquery";
		};
	};
};
