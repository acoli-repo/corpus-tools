open( FILE, "Resources/filelist.xml" ) || die "Enable to open test file";

while (defined(my $char = getc FILE)) {
	$newpos = tell(FILE);
	if ( $char eq '<' ) {
		$lastbrack = $oldpos;
		$label = $char;
	} elsif ( $char eq '>' ) {
		$label .= $char;
		if ( $label =~ /<file id="(.*?)"/ ) {
			$id = $1;
			print "$id\t$lastbrack\n";
		} else {
			# print "-$oldpos: $label\n"
		};
		$lastbrack = 0;
	} elsif ($lastbrack) {
		$label .= $char;
	};
	$oldpos = $newpos;
};
close(FILE);
