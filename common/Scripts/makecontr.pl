use XML::LibXML;
$\ = "\n"; $, = "\t";

&dofolder("xmlfiles");
$parser = XML::LibXML->new();

foreach $file ( @files ) {
	print $file;
	eval {
		$doc = $parser->load_xml(location => $file);
	};
	if ( $doc ) {
		foreach $contr ($doc->findnodes("//tok[dtok]")) {
			$form = $contr->getAttribute("form") or $form = $contr->textContent;
			$form = lc($form);
			$sep = ""; $pps = ""; $wrong = 0;
			foreach $prt ($contr->findnodes("dtok")) {
				$pf = $prt->getAttribute("form");
				if ( $pf eq "" ) {
					print "No form: ".$prt->toString;
					$wrong = 1;
				};
				$pf = lc($pf);
				$pps .= $sep.$pf; $sep = ",";
			};
			if ( $wrong ) { next; };
			if ( !$cls{$form}{$pps} ) {
				print "$form => $pps";
				$cls{$form}{$pps} = 1;
			} else {
				$cls{$form}{$pps}++;
			};
		};
	};
};

# Check which words also appear as a single token
if ( -e "cqp/word.lexicon" ) {
	$/ = "\0";
	open FILE, "cqp/word.lexicon";
	while ( <FILE> ) {
		chop;
		$word = lc($_);
		if ( $cls{$word} ) {
			$cls{$word}{$word} = 1;
		};
	};
};

open FILE, ">Resources/contractions.txt";
binmode(FILE, ":utf8");
while ( ( $key, $val ) = each ( %cls ) ) {
	print FILE $key, join("\t", keys(%{$val}))
};
close FILE;

sub dofolder ( $fld ) {
	$fld = @_[0];

	foreach $file ( glob( "$fld/*" ) ) {
		if ( $file =~ /\.xml/ ) {
			push(@files, $file);
		} elsif ( -d $file ) {
			dofolder($file);
		};
	};

};