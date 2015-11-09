# Train a Neotag parameter set on all (tagged) XML files in --infiles
# Save parameter set to --folder

use encoding 'utf8';
use Getopt::Long;
use HTML::Entities;
use XML::LibXML;
use XML::XPath;
use XML::XPath::XMLParser;
use File::Find qw(find);
 
GetOptions (
            'lower=i' => \$lower,
            'insearch=s' => \$insearch, # Define infiles by a regexp
            'infolder=s' => \$infolder, # Define infiles by a regexp
            'folder=s' => \$folder, # Where to store the parameter files
            'forms=s' => \$formlist, # The form hierarchy
            'debug' => \$debug,
            'incnotag' => \$incnotag, # Take words without pos into account
            'context=i' => \$contlen,
            'test' => \$test,
            'debug' => \$debug,
            'restriction=s' => \$xprest, # Restriction in XPath to select only part of the files (multiple langs, dialects, periods, etc)
            'pos=s' => \$posatt, # Attribute for the pos tag to use (ana, pos, mfs, etc)
            );

$histsep = '¢'; # Which symbol to use to separate tags in history
$mfssep = '#'; # Which symbol to use to separate POS from MFS

$\ = "\n"; $, = "\t";

# Define/create the folder where to save the parameter files
if ( !$folder ) { $folder = "./neotag"; };
if ( !-d $folder ) { mkdir($folder) || die "Unable to create directory <$!>\n"; }; # Create the folder if necessary

if ( !$insearch && !$infolder ) { $insearch = "xmlfiles/*.xml,xmlfiles/*/*.xml,xmlfiles/*/*/*.xml"; };
if ( !$posatt ) { $posatt = "pos"; };
if ( !$formlist ) { $formlist = "nform,form"; }; @formlist = split ( ",", $formlist );

# Create the list of files of the training corpus
# By folder
if ( -d $infolder ) { 
	print "Adding: $infolder";
	find(sub {
	return if( $_ eq '.' || $_ eq '..' );
		push @infiles, $File::Find::name;
}, $infolder ); };
# By search pattern
if ($insearch) { foreach $incond ( split ( ",", $insearch ) ) {
	@tmp = glob($incond);
	@infiles = (@infiles, @tmp);
};};

# if ( $debug ) { 
# 	print "File list to treat:";
# 	print join (", ", @infiles);
# };

# Some default labels (should there be any?)
$labels{'gloss'} = 1;
$labels{'morph'} = 1;
$labels{'ipa'} = 1;
$labels{'pronunciation'} = 1;

if ( !$deftag ) { $deftag = "pos:PUNCT"; };
if ( !$contlen ) { $contlen = 1; };


$numt = scalar @infiles;

print "reading tokens in the $numt verified texts in $db";

$tcnt = 0;
foreach $infile ( @infiles ) {
	$tcnt++;
	print "$tcnt. reading text: $infile";

	$/ = undef;
	open FILE, $infile;
	$xmltxt = <FILE>;
	close FILE;
	$/ = "\n";

	if ( $xmltxt !~ / $posatt=/ ) {
		print "- untagged file, ignoring";
		next;
	};

	my $p = XML::Parser->new( NoLWP => 1);
	$xp = XML::XPath->new( parser => $p, xml => $xmltxt);
	
	# If a restriction is set, check if it is matched
	if ( $xprest ) {
		if ( !$xp->find($xprest) ) {
			print " --- XPath restriction not met: $xprest";
			next;
		};
	};
	
	$nodeset = $xp->find('//tok'); # find all tokens
	
	$taghist = $deftag;
	
	foreach $node ( $nodeset->get_nodelist ) {
		$word = ""; @formlist = split(",", $formlist);
		while ( $word eq '' && scalar @formlist ) { 
			$chfrm = shift(@formlist); $word = $node->getAttribute($chfrm); 
		};
		if ( $word eq '--' ) { next; }
		elsif ( !$word ) { $word = $node->string_value; }
		$word =~ s/&#039;/'/;
	
		# Check whether this is the first word of a sentence
		$sentid = $node->getParentNode->getAttribute('id');
		if ( $oldsent eq $sentid ) { $firstword = 0; } else { $firstword = 1; };
		$oldsent = $sentid;
		
		%taga = ();
		$lemma =  $node->getAttribute('lemma');
	
		$tag = $node->getAttribute($posatt);
		# if ( $node->getAttribute('mfs') ) { $tag .= $mfssep.$node->getAttribute('mfs'); };
		
		if ( $debug ) { print $node->getAttribute('id'), $word, $tag; };
		
		if ( $tag eq '' && !$node->findnodes("./dtok\[\@$posatt\]") && !$incnotag ) { next; }; # Ignore non-tagged words by default
		
		$i = 0; $sep = $contrfrm = $contrlem = ""; 
		$prepost = "pre"; # Mark whether the clitics are pre- or postclitic
		
		foreach $chnode ( $node->getChildNodes() ) {
			$chtype = $chnode->getName(); $i++;
				if ( $debug ) { print "Child $i = $chtype"; };

			if ( $chtype eq 'dtok' ) {
				$chlem .= $chnode->getAttribute('lemma');
				$derlemma = $chnode->getAttribute('lemma');
				$derpos = $chnode->getAttribute($posatt);
				# Take the form from either the form attribute, or the internal value (old setup)
				$derform = ""; @formlist = split(",", $formlist);
				while ( $derform eq '' && scalar @formlist ) { 
					$chfrm = shift(@formlist); $derform = $node->getAttribute($chfrm); 
				};
				$derform =~ s/&#039;/'/;

				$contrfrm .= $sep.$derform; 
				$derform =~ s/&#039;/'/g;
				$dertype = $chnode->getAttribute('dertype');
				$dpos = $chnode->getAttribute('dpos');

				if ( $debug ) { print " ==> $derform, $derpos, $derlemma, $contrfrm"; };

				$dtag = $chnode->getAttribute($posatt);
				# if ( $chnode->getAttribute('mfs') ) { $dtag .= $mfssep.$chnode->getAttribute('mfs'); };
				
				# Once we reach the base word, everythin after is a post-clitic (how about inclitics?)
				if ( $dpos eq 'base' || $dpos eq '' ) { 
					$prepost = "post"; 
					
					# Count the base word of a contraction as a separate token (cliticized words should not count in lemmatization)
					$lemmatize{$derform}{$dtag} = $derlemma;
					$freqlist{$derform}{$dtag}++;
					$wordfreq{$derform}++;
					$lemfreq{$derlemma}{$derpos}++;

				}; 

				$contrlem .= $sep.$chnode->getAttribute('lemma'); $sep = '+=';
				$tag .= "+=".$dtag; # .$chnode->string_value.'::';

				if ( $debug ) { print " - dtok $derform, $derlemma, $dtag"; };
				
				# For derivations, keep the lemmatization info				
				if ( $chnode->getAttribute('dtype') eq 'derivation' ) { $dtoklemma{$dertype}{$lemma}{$derlemma}++; };
				# Keep a full list of productive contractors 
				if ( 
					# Explicit productive marked on the <dtok>
					( $chnode->getAttribute('dtype') eq 'prod' || $chnode->getAttribute('dpos') eq 'clitic' )
					# Otherwise we have to rely on circumstantial evidence:
					# - lexicalized contractions should be lemmatized
					# - the contracted part should have a free form
					|| ( $chnode->getAttribute('fform') ne '' && $node->getAttribute('lemma') eq '' ) ) { 
				 	$dtokparts{$derform}{$prepost}{$derlemma}{$dtag}++; 
				 	if ( $debug ) { print " - dtokpart", $derform, $dertype, $lemma, $node->toString; };
				 };
			};
		};
	
		$pos = $node->getAttribute('pos').':'.$node->getAttribute('subclass');
		$orgword = $word;
		# if ( $lower ) { $word = lc($word); }; # We do not want to kill upercase in the training, since we will have no access to it afterwards

		# Make a list of the labels (gloss, pronunciation)
		while ( ( $key, $val ) = each ( %labels ) ) {
			$value = $node->getAttribute($key);
			if ( $value ) { 
				if ( $key eq 'gloss' ) { $gloss = 1; };
				$labeldict{$key."\t".$word."\t".$lemma."\t".$pos."\t".$tag."\t".$value}++;
			};
		};
	
		# Force repeating FS tags
		@tmp = split ( $histsep, $taghist );
		if ( $tmp[-1] eq $deftag ) { 
			$ftags = $taghist;			
			while ( $tmp[0] ne $deftag ) {
				@tmp = split( "#", $ftags.$histsep.$deftag );		
				@tmp = splice ( @tmp, -$contlen);			
				$ftags = join ( $histsep, splice ( @tmp, -$contlen ) );		
				@tmp = split( "#", $ftags );		
	
				if ( $debug ) { print " -- forcing $ftags to $tag <= ".join ("&", @tmp); };
				$seqlist{$ftags}{$tag}++;
				$tagfreq{$ftags}++;
	
			};
		};
		
		$cnt++;
		@tmp = split ( $histsep, $taghist );
		if ( $#tmp > $contlen-1 ) { $taghist = join ( $histsep, splice ( @tmp, -$contlen ) ); }; 
		
		if ( $debug ) {  print $word, $lemma, $tag, $taghist; };
	
		# Determine the case for this word
		if ( $taghist ne $deftag ) {
			if ( $word =~ /^\p{Ll}+$/ ) { 
				$case = "ll";
			} elsif ( $word =~ /^\p{Lu}\p{Ll}+$/ ) { 
				$case = "Ul";
			} elsif ( $word =~ /^\p{Lu}+$/ ) { 
				$case = "UU";
			} else {
				$case = "??";
			};
		};
		
		$words++;
		$tagfreq{$taghist}++;
		$wordfreq{$word}++;
		$freqlist{$word}{$tag}++;
		$lemfreq{$lemma}{$pos}++;
		$lemmatize{$word}{$tag} = $lemma;
		$pos{$word}{$tag} = $pos;
		$contr1{$word}{$tag} = $contrfrm;
		$contr2{$word}{$tag} = $contrlem;

		# Keep track of words beginning or ending with a "punctuation mark"
		if ( $word =~ /^[\p{isPunct}].|.[\p{isPunct}]$/ ) {
			$ptoks{$word}++;
		};

		$casecnt{$tag}{$case}++;
	
		$seqlist{$taghist}{$tag}++;
		$taghist .= $histsep.$tag;
				
		if ( $maxwords > 0 && $cnt > $maxwords ) { last; };
	
	};

};

# Calculate the clitic frequency of dtok parts
while ( ( $form, $val1 ) = each ( %dtokparts ) ) {
	$form =~ s/[\[\]|]/\\\1/g;
	while ( ( $prepost, $val2 ) = each ( %$val1 ) ) {
		while ( ( $tag, $val3 ) = each ( %$val2 ) ) {
			while ( ( $lemma, $freq ) = each ( %$val3 ) ) {
				$yesclit{$form}{$prepost} += 1;
				$yesclitt{$form}{$prepost} += $freq;
			};
		};
	};
	%tmp = %{$val1};
	if ( $tmp{'pre'} ) { $preclits .= $precsep.$form; $precsep = '|'; };
	if ( $tmp{'post'} ) { $postclits .= $postcsep.$form; $postcsep = '|'; };
	$preclits =~ s/\\/\\\\/g;
	$postclits =~ s/\\/\\\\/g;
}; 
if ( $debug ) { print "CLITICS: $preclits, $postclits"; };
while ( ( $word, $val1 ) = each ( %freqlist ) ) {
	while ( ( $tag, $freq ) = each ( %$val1 ) ) {
		if ( $tag !~ /pos:CONTR/ ) {
			$preclits =~ s/\?/\\\?/g;
			if ( $word =~ /^($preclits)./ ) {
				$clit = $1; #print '', 'noclit', $clit, $word, $tag, 'pre';
				$noclit{$clit}{'pre'} += 1;
				$noclitt{$clit}{'pre'} += $freq;
			};
			$postclits =~ s/\?/\\\?/g;
			if ( $word =~ /.($postclits)$/ ) {
				$clit = $1; # print '', 'noclit', $clit, $word, $tag, 'post';
				$noclit{$clit}{'post'} += 1;
				$noclitt{$clit}{'post'} += $freq;
			};
		};
	};
};

print "- done reading texts : $words tokens";

if ( $test ) { print "Testing not writing"; exit; };

# Print the transition table
if ( !$transfile ) { 
	if ($contlen == 1) { $transfile = "transitions.txt"; } else { $transfile = "transitions$contlen.txt"; };
};
print "writing the transition table... to $folder/$transfile";
open ( FILE, ">$folder/$transfile" );
binmode FILE, ":utf8";
while ( ( $key1, $val1 ) = each ( %seqlist ) ) {
	# print "processing $key1 => $val1, freq: ".$tagfreq{$key1};
	while ( ( $key2, $val2 ) = each ( %$val1 ) ) {
		print FILE $key1, $key2, $val2/$tagfreq{$key1}*10, $val2;
	};
};
close(FILE);


# Print the lexical probablity table
if ( !$lexfile ) { $lexfile = "lexiconprobs$lang.txt"; };
print "writing the lexicon table... to $folder/$lexfile";
open ( FILE, ">$folder/$lexfile" );
binmode FILE, ":utf8";
foreach $key1 (sort keys %freqlist) {
	$val1 = $freqlist{$key1};
	while ( ( $key2, $val2 ) = each ( %$val1 ) ) {
		if ( $key1 ne '' ) { 
			print FILE $key1, $key2, $val2/$wordfreq{$key1}*10, $val2, $lemmatize{$key1}{$key2}, $pos{$key1}{$key2}, $contr1{$key1}{$key2}, $contr2{$key1}{$key2};
		};
	};
};
close(FILE);


# Print the lemma frequency table (not for tagging, only for dict)
$file = "lemfreq$lang.txt";
print "writing the lemma frequency table... to $folder/$file";
open ( FILE, ">$folder/$file" );
binmode FILE, ":utf8";
foreach $key1 (sort keys %lemfreq) {
	$val1 = $lemfreq{$key1};
	while ( ( $key2, $val2 ) = each ( %$val1 ) ) {
		print FILE $key1, $key2, $val2;
		if ( $dict ) {
			$key1 =~ s/'/\\\'/g;
			$key2 =~ s/'/\\\'/g;
			$key2 =~ s/:.*//g;
			$query = "UPDATE wikicorpora.lemmalist$langid SET freq='$val2' WHERE citform='$key1' collate utf8_bin && class='$key2';";
			$sth = $dbh->prepare ( $query );
			$sth->execute ();
		};
	};
};
close(FILE);


# Print the dtok lemmatization
if ( !$dtokfile ) { $dtokfile = "dtoklemmas$lang.txt"; };
print "writing the dtok lemma table... to $folder/$dtokfile";
open ( FILE, ">$folder/$dtokfile" );
binmode FILE, ":utf8";
while ( ( $dertype, $val1 ) = each ( %dtoklemma ) ) {
	while ( ( $lemma, $val2 ) = each ( %$val1 ) ) {
		while ( ( $derlemma, $freq ) = each ( %$val2 ) ) {
			print FILE $dertype, $lemma, $derlemma, $freq;
		};
	};
};
close(FILE);

# Print the dtok parts
if ( !$dtokpfile ) { $dtokpfile = "dtokparts$lang.txt"; };
print "writing the dtok parts table... to $folder/$dtokpfile";
open ( FILE, ">$folder/$dtokpfile" );
binmode FILE, ":utf8";
while ( ( $form, $val1 ) = each ( %dtokparts ) ) {
	while ( ( $prepost, $val2 ) = each ( %$val1 ) ) {
		while ( ( $tag, $val3 ) = each ( %$val2 ) ) {
			while ( ( $lemma, $freq ) = each ( %$val3 ) ) {
				print FILE $form, $lemma, $tag, $prepost, $freq, $noclit{$form}{$prepost}, $noclitt{$form}{$prepost}, $yesclit{$form}{$prepost}, $yesclitt{$form}{$prepost};
			};
		};
	};
};
close(FILE);

# Print the labels list
$outfile = "labels$lang.txt"; 
print "writing the labels table... to $folder/$outfile";
open ( FILE, ">$folder/$outfile" );
binmode FILE, ":utf8";
while ( ( $key, $val ) = each ( %labeldict ) ) {
	print FILE $key, $val;
};
close(FILE);

# Print the case probability list
$outfile = "case$lang.txt"; 
print "writing the case table... to $folder/$outfile";
open ( FILE, ">$folder/$outfile" );
binmode FILE, ":utf8";
while ( ( $tag, $val1 ) = each ( %casecnt ) ) {
	while ( ( $case, $freq ) = each ( %$val1 ) ) {
		print FILE $tag, $case, $freq, $freq/($tagfreq{$tag}+1);
	};
};
close(FILE);

# Print the list of tokens starting or ending with a punctuation mark
$outfile = "ptoks.txt"; 
print "writing the ptok table... to $folder/$outfile";
open ( FILE, ">$folder/$outfile" );
binmode FILE, ":utf8";
while ( ( $word, $freq ) = each ( %ptoks ) ) {
	# print $word, $freq;
	print FILE $word, $freq;
};
close(FILE);

sub casetype ($string) {
	my $string = @_[0];
	
	if ( $word =~ /\p{Lu}/ && $word !~ /\p{Ll}/ ) {
		return "UA";
	} elsif ( $word =~ /^\p{Lu}/ ) {
		return "Uf";
	} elsif ( $word =~ /\p{Ll}/ && $word !~ /\p{Lu}/ ) {
		return "la";
	} elsif ( $word =~ /^\p{Ll}/ ) {
		return "lF";
	} else {
		return "xx";
	};

};
