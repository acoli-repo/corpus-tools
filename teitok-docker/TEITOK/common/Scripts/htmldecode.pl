use HTML::Entities;

binmode (STDIN, ":utf8"); 
binmode (STDOUT, ":utf8"); 
while ( <> ) {
	s/^--\t.*\n$//; # Double hyphens are empty tokens
	$line = decode_entities($_);
	$line =~ s/^<\t/&#060;\t/;
	print $line;
};