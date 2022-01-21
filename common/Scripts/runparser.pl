use XML::LibXML;
use Getopt::Long;
use LWP::UserAgent;
use JSON;
use POSIX qw(strftime);
use Data::Dumper;

$\ = "\n"; $, = "\t";

# Run the parser (default: UDPIPE) either from the command line or as REST

GetOptions (
            'debug=i' => \$debug,
            'verbose' => \$verbose,
            'force' => \$force,
            'test' => \$test,
            'help' => \$help,
            'killsent' => \$killsent,
            'filename=s' => \$filename,
            'settings=s' => \$settingsfile,
            'model=s' => \$model,
            'langxpath=s' => \$langxpath,
            'mode=s' => \$mode,
            'url=s' => \$url,
            'form=s' => \$form,
            'output=s' => \$outfile,
            'par=s' => \$ptype,
            'parser=s' => \$parsername,
            'format=s' => \$parserformat,
            'taglist=s' => \$formtags,
            'resp=s' => \$resp,
            'args=s' => \$args,
            'null=s' => \$null,
        );

if ( !$filename ) { $filename = shift; };
if ( !$outfile ) { $outfile = $filename; };
if ( !$settingsfile ) { $settingsfile = "Resources/settings.xml"; };
if ( !$parsername ) { $parsername = "udpipe"; };
if ( !$resp ) { $resp = "json:result"; };
if ( $model ) { $parsemodel = $model; $parsemodel =~ s/.*\///; };

if ( $debug ) { $verbose = 1; };

if ( $help ) {
	print "Usage: runparser [options]
Options:
	--debug		debug mode
	--verbose	verbose mode
	--force		force tagging even if already tagged
	--test		testing mode - do not save
	--help		help
	--killsent	kill sentence if there are any (and let the parser handle sentences)
	--filename=fn	input filename
	--output=fn	output outfile (=input when empty)
	--par=par	XML node to use as text segmentation (default: p)
";
	exit;
};

$ua = LWP::UserAgent->new(ssl_opts => { verify_hostname => 1 });

$parser = XML::LibXML->new(); 
$parser->keep_blanks(1);

if ( !$filename ) { print "No filename provided"; exit; };

if ( !-w $filename && !$test ) { print "Not allow to write to $filename"; exit; };

if ( $settingsfile && -e $settingsfile ) { $settings = $parser->load_xml(location => $settingsfile); };
if ( $settings ) {
	if ( $verbose ) { print "Inheritance from $settingsfile"; };
	foreach $fdef ( $settings->findnodes("//xmlfile/pattributes/forms/item") ) {
		$form = $fdef->getAttribute("key");
		push ( @forms, $form);
		$inherit{$form} = $fdef->getAttribute("inherit"); 
	}; if ( !$inherit{"form"} ) { $inherit{"form"} = "pform"; };
} else {
	$settings = $parser->load_xml(string => "<ttsettings/>");
};


open FILE, $filename;
binmode(FILE, ":utf8");
$/ = undef;
$raw = <FILE>;
close FILE;

if ( $killsent ) { 
 	$raw =~ s/<s ([^<>]+)>//g;
	$raw =~ s/<\/s>//g;
};

if ( $raw !~ /<\/s>/ ) { 
	$dosent = 1; 
};

eval {
	$xml = $parser->load_xml(string => $raw, load_ext_dtd => 0);
};
if ( !$xml ) { print "Unable to load XML file"; exit; };


if ( !$xml->findnodes("//tok") ) {
	print "Please tokenize first"; exit;
};

if ( $xml->findnodes("//tok[\@ord]") && !$force ) {
	print "Already parsed"; exit;
};

# Determine the model, tagform, and block when not given
if ( !$model ) {
	# Use the parser settings
	@tmp = $settings->findnodes("//parser/parameters/item");
	if ( !@tmp ) { @tmp = $settings->findnodes("//udpipe/parameters/item"); }; # Legacy
	if ( @tmp ) {
		foreach $param ( @tmp ) {
			$prest = $param->getAttribute('restriction');
			if ( $debug > 1 ) { print "Check parameter ($prest): ".$param->toString; };
			if ( !$prest || $xml->findnodes($prest) )  {
				if ( $debug ) { print "Selected parameter settings: ".$param->toString; };
				$tmp2 = $param->getAttribute('params') or $tmp2 = $param->getAttribute('model');
				if ( !$model ) { $model = $param->getAttribute('model'); };
				if ( !$formtags ) { $formtags = $param->getAttribute('taglist') or $formtags = $param->getAttribute('formtags'); };
				if ( !$url ) { $url = $param->getAttribute('url') or $url = $param->getAttribute('cmd'); };
				if ( !$null ) { $null = $param->getAttribute('null'); };
				if ( !$parserformat ) { $parserformat = $param->getAttribute('format') or $parserformat = $param->getAttribute('format'); };
				if ( !$parsername ) { $parsername = $param->getAttribute('parsername') or $parsername = $param->parentNode->parentNode->getAttribute('parsername');
				};
			};
		};
	};		
}; 
if ( $formtags && $formtags ne 'all' ) { 
	foreach $tmp3 ( split(",", $formtags ) ) { $dotag{$tmp3} = 1; };  
	if ( $verbose && %dotag ) { print " - only loading tags: $formtags"; };
};
if ( !$model ) { 
	# Use the TEI language
	if ( !$langxpath && $param ) { 
		$langxpath = $param->getAttribute("langxpath");
	};
	if ( !$langxpath ) { $langxpath = "//langUsage/language/\@ident"; };
	if ( $debug > 2 ) { print "Using for language detection: $langxpath"; };
	$tmp = $xml->findnodes($langxpath);
	if ( $tmp ) { 
		$model = $tmp->item(0)->value; 
		if ( $debug ) { print "Detected language: $model"; };
	};
};
if ( !$model ) { 
	# Use the default corpus language
	$tmp = $settings->findnodes("//defaults/\@lang");
	if ( $tmp ) { 
		$model = $tmp->item(0)->value; 
		if ( $debug ) { print "Detected language: $model"; };
	};
};
if ( !$model ) { print "No parser model selected - $url / $model"; exit; };
if ( !$form ) { 
	# Use the parser settings
	$tmp = $settings->findnodes("//parser/\@tagform");
	if ( $tmp ) { $form = $tmp->item(0)->value; }
	else { $tmp = $settings->findnodes("//udpipe/\@tagform"); };
	if ( $tmp ) { $form = $tmp->item(0)->value; };
};
if ( !$form ) { 
	# Use the normalized form
	if ( $inherit{"nform"} ) { $form = "nform"; };
	if ( $inherit{"reg"} ) { $form = "reg"; };
};
# Default to form
if ( !$form ) { $form = "form"; };
if ( !$ptype ) { 
	if ( $xml->findnodes("//p") && !$xml->findnodes("//tok[not(ancestor::p)]")) { 
		# when all tokens are subsumed under <p>, use that
		$ptype = "p"; 
	} else {
		$tmp = $xml->findnodes("//text/*");
		if ( $tmp ) {
			$nn = $tmp->item(0)->getName();
			if ( $xml->findnodes("//$nn") && !$xml->findnodes("//tok[not(ancestor::$nn)]")) { 
				$ptype = $nn;
			};
		};
	};
};
# Default to text
if ( !$ptype ) { $ptype = "text"; };

if ( $parserformat eq 'wpl' ) {
	$flds = "form,pos,lemma";
} elsif ( $parserformat eq 'wlp' ) {
	$flds = "form,lemma,pos";
} elsif ( $parserformat && $parserformat ne 'conllu' ) {
	$flds = $parserformat;
} else {
	# By default, assume CoNLL-U - with @ohead to calculate @head afterwards
	$flds = "ord,form,lemma,upos,xpos,feats,ohead,deprel,deps"; #,misc
};
@flds = split(",", $flds); for ( $i=0; $i<scalar @flds; $i++ ) { 
	if ( $flds[$i] eq 'form' ) { $wrdf = $i; }; 
};
if ( not( defined $wrdf ) ) { print "The option -format should at least contain a FORM: $flds"; exit; };

# Default to UDPIPE values
if ( !$parserformat ) { $parserformat = "conllu"; };
if ( !$null ) { $null = "_"; };

if ( $verbose ) { print "Parser output format: $parserformat"; };
if ( $verbose ) { print "Segmenting by $ptype - parsing using model '$model' on form '$form'"; };
if ( $verbose && $dosent ) { print "Adding sentences from parser"; };

$scnt = 1; $pcnt = 1;
foreach $par ( $xml->findnodes("//$ptype") ) {

	$id = $par->getAttribute('id') or $id = $pcnt++;
	if ( $verbose ) { print " - $ptype $id"; };

	$from = $to = 0;
	undef($mtok);
	# Apply normalization
	$regpar = $par->cloneNode(1);
	if ( $form ne 'pform' && $inherit{'form'} ) {
		foreach $tok ( $regpar->findnodes(".//tok") ) {
			$form = calcform($tok, "form");
			$tok->removeChildNodes;
			$tok->appendText($form);  
		};
	};
	@toks = $regpar->findnodes(".//tok"); 
	@orgtoks = $par->findnodes(".//tok"); 
	$text = $regpar->textContent;
	if ( $debug ) { print $text; };
	$parsed = runudpipe($text, $model);
	foreach $line ( split("\n", $parsed ) ) {
		if ( $debug > 2 ) { print $line; };
		if ( $parserformat eq 'conllu' ) {
			conlluline($line);
		} else {
			genericline($line);
		}; 
	};
};

if ( $dosent ) {
	# Move all the tok inside the <s>
	foreach $s ( $xml->findnodes("//s") ) {
		if ( $debug ) {
			print "Moving tokens inside ".$s->getAttribute("id");
		};
		while ( $next  = $s->nextSibling ) {
			if ( $next->getName() eq 's' ) { last; };
			if ( $debug > 2 ) { print "Moving ".$next->toString; };
			$s->appendChild($next);
		};
	};
};

## Add the @head
foreach $s ( $xml->findnodes("//s") ) {
	$sid = $s->getAttribute("id");
	if ( $debug ) { print "Doing \@head for $sid"; };
	foreach $tok ( $s->findnodes(".//tok | .//dtok") ) {
		$tokid = $tok->getAttribute("id");
		$ohead = $tok->getAttribute("ohead");
		if ( !$ohead ) { next; };
		$otok = $s->findnodes(".//*[\@ord=\"$ohead\"]");
		if ( $otok ) {
			$ohid = $otok->item(0)->getAttribute("id");
			$tok->setAttribute("head", $ohid);
			if ( $debug ) { print "$tokid - $ohead => $ohid"; };
		} else {
			if ( $verbose ) { print "Not found: $sid/\@ord=$ohead"; };
		};
	};
};

# Add a revisionDesc to indicate the file was parsed
$revnode = makenode($xml, "/TEI/teiHeader/revisionDesc/change[\@who=\"runparser\"]");
$when = strftime "%Y-%m-%d", localtime;
$revnode->setAttribute("when", $when);
if ( $parsemodel ) { $revnode->setAttribute("model", $parsemodel); };
$revnode->appendText("parsed using $parsername");

$parsed = $xml->toString;
$parsed =~ s/(\s+)<\/s>/<\/s>\1/gsm;

if ( $debug || $test ) { print $parsed; };

if ( $xml->findnodes("//tok[not(ancestor::s)]")  ) {
	print "Warning: tok outside of s"; 
	foreach $tok ( $xml->findnodes("//tok[not(ancestor::s)]") ) {
		print $tok->toString;
	};
};

if ( $test ) { exit; };

open FILE, ">$outfile";
print FILE $parsed;
close FILE;
if ( $verbose ) { print "Parsing complete - saved to $outfile"; };


sub runudpipe ( $raw, $model ) {
	($raw, $model) = @_;

	if ( !$url ) { $url = "http://lindat.mff.cuni.cz/services/udpipe/api/process"; };
	if ( $debug ) { print " - Running parser from $url + $model"; };
	
	if ( $url !~ /^http/ ) {
	
		# Command-line parser
		( $parsername = $url ) =~ s/.*\///;
		$parsername =~ s/ (.*)//;

		open FILE, ">/tmp/parsertmp.txt";
		binmode(FILE, ":utf8");
		print FILE $raw;
		close FILE;
	
		if ( $parsername eq 'udpipe' && !$1 ) { 
			if ( $debug ) { print "No arguments given for udpipe - calculating"; };
			if ( $mode eq 'parse' ) {
				$opt = "--parse";
			} elsif ( $mode eq 'tag' ) {
				$opt = "--tag";
			} else {
				$opt = "--tag --parse";
			};
			$cmd = "$url --tokenize $opt $model /tmp/parsertmp.txt";
		} else {
			$cmd = $url;
			$cmd =~ s/\[fn\]/\/tmp\/parsertmp.txt/;
			$cmd =~ s/\[model\]/$model/;
		};
		
		if ( $debug ) { print "Cmd: $cmd"; };
		binmode(FILE, ":UTF8");
		$result = `$cmd`;
		utf8::decode $result;
		
	} else {
	
		if ( $url =~ /udpipe/ && !$args ) {
			$datafld = "data";
			if ( $mode eq 'parse' ) {
				$tagger = "0"; $parser = "1";
			} elsif ( $mode eq 'tag' ) {
				$tagger = "1"; $parser = "0";
			} else {
				$tagger = "1"; $parser = "1";
			};
			$args = "tokenizer=1&tagger=$tagger&parser=$parser&model=$model";
		};
		if ( !$datafld ) { $datafld = "data"; };
		
		if ( $debug ) {	print "REST arguments: $args"; };
		
		$form{$datafld} = $raw;
		foreach $frmfld ( split("&", $args) ) {
			( $key, $val ) = split("=", $frmfld);
			if ( $val eq '[model]' ) { $val = $model; };
			$form{$key} = $val;
		}; 

		$res = $ua->post( $url, \%form );
		$rawresult = $res->decoded_content;
		if ( $debug ) { print "RAW parser result:\n".$rawresult; };
		if ( $resp ) {
			( $protocol, $pq ) = split(":", $resp);
			if ( $protocol eq "json" ) {
				eval {	
					$jsonkont = decode_json($rawresult);
				};
				if ( $jsonkont ) {
					$result = $jsonkont->{$pq};
					$parsemodel = $jsonkont->{'model'} or $parsemodel = $model; # UDPIPE specific
				} else { print "REST Error: $rawresult"; exit; };
			} elsif ( $protocol eq 'xml' ) {
				eval {	
					$rxml = simplexml_load_string($rawresult);
				};
				if ( $rxml ) {
					eval { $tmp = $rxml->findnodes($pq); };
					if ( $tmp ) {
						$tmp2 = $tmp->item(0);
						$result = $tmp2->value or $results = $tmp->textContent;
					} else {
						print "XML Error: $rawresult"; exit; 
					};
				} else {
					print "XML Error: $rawresult"; exit; 
				};
			};
		};		
	};
		
	if ( $debug ) { print "VRT parser result:\n".$result; };

	return $result;
};

sub calcform ( $node, $form ) {
	( $node, $form ) = @_;
	
	if ( $form eq 'pform' ) {
		return $node->textContent;
	} elsif ( $node->getAttribute($form) ) {
		$fval = $node->getAttribute($form);
		if ( $fval eq '--' ) { $fval = ""; };
		return $fval;
	} elsif ( $inherit{$form} ) {
		return calcform($node, $inherit{$form});
	} else {
		return "";
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

sub genericline ( $line ) {
	$line = @_[0];
	if ( $line =~ /^#.*/ || $line =~ /^<.*/ ) {
		# Ignore any comment lines and SGML lines
	} elsif ( $line eq "" ) {
		# Empty line - new sent
		if ( $debug ) { print "Empty line - treating as sentence start"; };
	} else {
		# Anything else should be a token line
		if ( $debug ) { print "Token: $line"; };
		@vals = split("\t", $line ); $word = $vals[$wrdf];
		$orgword = @toks[0]->textContent;
		while ( $orgword eq "" ) { 
			print " - Skipping empty token : ".$orgtoks[0]->toString;
			shift(@toks);  shift(@orgtoks); 
		}; 
		if ( $debug > 1 ) {
			print "Attempting to matching: (org) $orgword (word) $word (wordleft) $wordleft";
		}; 
		if ( $orgword eq $word ) {
			$regtok = shift(@toks); 
			$tok = shift(@orgtoks); 
			addline($tok, $line);
			if ( $debug ) { print $tok->toString; };
		} elsif ( $wordleft && $tok && $wordleft eq $word ) {
			# This to deal with model that do not properly indicate ranges
			$wordleft = substr($wordleft,length($word));
			if ( $debug ) { print "follow-up match: $orgword <= $word ($wordleft)\n$line"; };
			$dtok = $xml->createElement("dtok");
			$tok->appendChild($dtok);
			addline($dtok, $line);
			$did = $tok->getAttribute("id").".".$dc++;
			$dtok->setAttribute("id", $did);
			$dtok->setAttribute("form", $word);
			if ( $debug ) { print $tok->toString; };
		} elsif ( substr($word,0,length($orgword)) eq $orgword ) {
			# This is for merged tokens (Mr.)
			# check if the next few tokens complete the word
			$nt = 0;
			while ( substr($word,0,length($orgword)) eq $orgword && $orgword ne $word ) {
				if ( substr($word,length($orgword),1) eq ' ' ) { $orgword .= " "; };
				$nt++; $nword = $orgtoks[$nt]; $orgword += $nword;
				if ( $debug ) { print "Checking $nt: $orgword"; };
			};
		} elsif ( substr($word,0,length($orgword)) eq $orgword ) {
			# This is for merged tokens (Mr.)
			# check if the next few tokens complete the word
			$nt = 0; $more = "";
			while ( substr($word,0,length($orgword)) eq $orgword && $orgword ne $word ) {
				if ( substr($word,length($orgword),1) eq ' ' ) { $orgword .= " "; };
				$nt++; $ntok = $orgtoks[$nt]; 
				if ( $ntok ) { 
					$orgword .= $ntok->textContent;
					$more .= $ntok->textContent;
				};
				if ( $debug > 2 ) { print "Checking $nt: $orgword"; };
			};
			if ( $orgword eq $word ) {
				$regtok = shift(@toks); 
				$tok = shift(@orgtoks); 
				if ( $debug ) { print "merged match: $orgword == $word ($nt)"; };
				addline($tok, $line);
				$merged = 1;
				for ( $i=0; $i<$nt; $i++ ) {
					$regtok2 = shift(@toks); 
					$tok2 = shift(@orgtoks); 
					if ( $tok->nextSibling() != $tok2 ) { $merged = 0; };
					if ( $nomerge ) {
						$tok2->setAttribute("mwe", "1");
					} else {
						foreach $child ( $tok2->childNodes() ) {
							$tok->addChild($child);
						};
					};
				};
				if ( !$nomerge ) { $tok2->parentNode->removeChild($tok2); };
				if ( $debug ) { print $tok->toString; };
			} else {
				print "Oops - merging leads to non-matching words: $orgword != $word"; 
				print "Next few words: ".join(", ", $orgtoks[0..3]);
				exit;
			};
		} elsif ( !$mtok && substr($orgword,0,length($word)) eq $word ) {
			# This to deal with model that do not properly indicate ranges for fused words
			$wordleft = substr($orgword,length($word));
			if ( $debug ) { print "partial match: $orgword <= $word ($wordleft)\n$line"; };
			$regtok = shift(@toks); 
			$tok = shift(@orgtoks); 
			$dtok = $xml->createElement("dtok");
			$tok->appendChild($dtok);
			addline($dtok, $line);
			$did = $tok->getAttribute("id").".1"; $dc=2;
			$dtok->setAttribute("id", $did);
			$dtok->setAttribute("form", $word);
			if ( $debug ) { print $tok->toString; };
		} else {
			print "oops: $orgword =/= $word\n$line"; 
			exit;
		};
	};
};

sub conlluline ( $line ) {
	$line = @_[0];
	if ( $line =~ /^# sent_id = (\d+)/ ) {
		# Sentence
		$sid = $1;
		if ( $debug ) { print "Sentence: $line"; };
		if ( $dosent ) {
			$beftok = @orgtoks[0];
			$news = $xml->createElement("s");
			$news->setAttribute("id", "s-".$scnt);
			$news->setAttribute("org", $sid);
			$beftok->parentNode->insertBefore($news, $beftok);
			if ( $debug ) {
				print "Added s: ".$news->toString;
			};
		};
		$scnt++;
	} elsif ( $line =~ /^#.*/ ) {
		# Comment line
	} elsif ( $line =~ /^(\d+)\t(.*)/ ) {
		# Token line
		if ( $debug ) { print "Token: $line"; };
		( $ord, $word, $lemma, $upos, $xpos, $feats, $head, $deprel, $deps, $misc ) = split("\t", $line ); 
		$orgword = @toks[0]->textContent;
		while ( $orgword eq "" ) { 
			if ( $debug ) { print " - Skipping empty token : ".$orgtoks[0]->toString; };
			shift(@toks);  shift(@orgtoks); 
			$orgword = @toks[0]->textContent;
		}; 
		if ( $debug > 1 ) {
			print "Attempting to matching: (org) $orgword (word) $word (wordleft) $wordleft";
		}; 
		if ( $mtok && $ord <= $to ) {
			if ( $debug ) { print "Part of mtok: ".$mtok->getAttribute("id"); };
			$dtok = $xml->createElement("dtok");
			$mtok->appendChild($dtok);
			addline($dtok, $line);
			$did = $mtok->getAttribute("id").".".$dc++;
			$dtok->setAttribute("id", $did);
			$dtok->setAttribute("form", $word);
			if ( $debug ) { print $mtok->toString; };
		} elsif ( $orgword eq $word ) {
			$regtok = shift(@toks); 
			$tok = shift(@orgtoks); 
			addline($tok, $line);
			if ( $debug ) { print $tok->toString; };
		} elsif ( $wordleft && $tok && substr($wordleft,0,length($word)) eq $word ) {
			# This to deal with model that do not properly indicate ranges
			$wordleft = substr($wordleft,length($word));
			if ( $debug ) { print "follow-up match: $orgword <= $word ($wordleft)\n$line"; };
			$dtok = $xml->createElement("dtok");
			$tok->appendChild($dtok);
			addline($dtok, $line);
			$did = $tok->getAttribute("id").".".$dc++;
			$dtok->setAttribute("id", $did);
			$dtok->setAttribute("form", $word);
			if ( $debug ) { print $tok->toString; };
		} elsif ( substr($word,0,length($orgword)) eq $orgword ) {
			# This is for merged tokens (Mr.)
			# check if the next few tokens complete the word
			$nt = 0; $more = "";
			while ( substr($word,0,length($orgword)) eq $orgword && $orgword ne $word ) {
				if ( substr($word,length($orgword),1) eq ' ' ) { $orgword .= " "; };
				$nt++; $ntok = $orgtoks[$nt]; 
				if ( $ntok ) { 
					$orgword .= $ntok->textContent;
					$more .= $ntok->textContent;
				};
				if ( $debug > 2 ) { print "Checking $nt: $orgword"; };
			};
			if ( $orgword eq $word ) {
				$regtok = shift(@toks); 
				$tok = shift(@orgtoks); 
				if ( $debug ) { print "merged match: $orgword == $word ($nt)"; };
				addline($tok, $line);
				$merged = 1;
				for ( $i=0; $i<$nt; $i++ ) {
					$regtok2 = shift(@toks); 
					$tok2 = shift(@orgtoks); 
					if ( $tok->nextSibling() != $tok2 ) { $merged = 0; };
					if ( $nomerge ) {
						$tok2->setAttribute("mwe", "1");
					} else {
						foreach $child ( $tok2->childNodes() ) {
							$tok->addChild($child);
						};
					};
				};
				if ( !$nomerge ) { $tok2->parentNode->removeChild($tok2); };
				if ( $debug ) { print $tok->toString; };
			} else {
				print "Oops - merging leads to non-matching words: $orgword != $word"; 
				print "Next few words: ".join(", ", @orgtoks[0..3]);
				exit;
			};
		} elsif ( substr($orgword,0,length($word)) eq $word ) {
			# This to deal with model that do not properly indicate ranges
			$wordleft = substr($orgword,length($word));
			if ( $debug ) { print "partial match: $orgword <= $word ($wordleft)\n$line"; };
			$regtok = shift(@toks); 
			$tok = shift(@orgtoks); 
			$dtok = $xml->createElement("dtok");
			$tok->appendChild($dtok);
			addline($dtok, $line);
			$did = $tok->getAttribute("id").".1"; $dc=2;
			$dtok->setAttribute("id", $did);
			$dtok->setAttribute("form", $word);
			if ( $debug ) { print $tok->toString; };
		} else {
			print "oops: $word =/= $orgword - or part ".substr($wordleft,0,length($word))." - $wordleft\n$line"; 
			exit;
		};
	} elsif ( $line =~ /^(\d+)-(\d+)\t(.*)/ ) {
		# Range line
		if ( $debug ) { print "Range: $line"; };
		( $ord, $word, $lemma, $upos, $xpos, $feats, $head, $deprel, $deps, $misc ) = split("\t", $line ); 
		$orgword =  @toks[0]->textContent;
		if ( $orgword eq $word ) {
			$regtok = shift(@toks); 
			$mtok = shift(@orgtoks); 
			($from, $to) = split("-", $ord);
			$mtok->setAttribute("ord", $ord);
			$dc = 1;
			if ( $debug ) { print $mtok->toString; };
		} else {
			print "oops: $orgword =/= $word\n$line"; exit;
		};
	} elsif ( $line eq "" ) {
		# Empty line - new sent
		if ( $debug ) { print "Empty line - resetting"; };
		$from = $to = 0; 
		undef($mtok);
	} else {
		# Ignore line
		if ( $debug ) { print "Ignoring: $line"; };
	};
};

sub addline( $tt, $lt ) {
	($tt, $lt) = @_;
	@flds = split(",",$flds);
	@vals = split("\t", $lt);
	for ( $i=0; $i<scalar @flds; $i++ ) {
		$dothis = 0;
		if ( $flds[$i] eq 'form' && !$doform ) { next; };
		if ( $flds[$i] eq '-' ) { next; };
		if ( $vals[$i] && $vals[$i] ne $null && $vals[$i] ne '<unknown>' ) { 
			if ( !%dotag || $dotag{$flds[$i]} ) {
				$tt->setAttribute($flds[$i], $vals[$i]); 
				$dothis = 1;
			}; 
		};
		if ( $debug > 2 ) { print $i, $flds[$i], $vals[$i], $dothis; };
	}; 
};
