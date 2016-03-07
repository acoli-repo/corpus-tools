# Script to check whether there are any "redundant" forms
# Where the explicit value matches the inherited value

use XML::LibXML;
$filename = shift;

binmode (STDOUT, ":utf8" );

$parser = XML::LibXML->new();
eval {
	$xml = $parser->load_xml(location => $filename);
};

$settingsfile = "Resources/settings.xml";
$settings = $parser->load_xml(location => $settingsfile);

foreach $fdef ( $settings->findnodes("//xmlfile/pattributes/forms/item") ) {
	$form = $fdef->getAttribute("key");
	push ( @forms, $form);
	$inherit{$form} = $fdef->getAttribute("inherit"); 
};

if ( !$xml ) {
	print "Unable to parse $filename";
	exit;
};

foreach $ttnode ($xml->findnodes("//tok")) {
	# print $ttnode->getAttribute("id");
	
	foreach $cform ( @forms ) {
		$formval = calcform($ttnode, $cform);
		if ( $cform eq 'pform' ) {
			# print "\t".$cform."=".$ttnode->getAttribute($cform)."*".$formval;
		} else {
			# print "\t".$cform."=".$ttnode->getAttribute($cform)."*".$formval;
		};
		if ( $inherit{$cform} && $ttnode->getAttribute($cform) eq calcform($ttnode, $inherit{$cform}) ) { 
			print $ttnode->getAttribute("id")."\t$cform\t$formval ($cform)\t=\t".calcform($ttnode, $inherit{$cform})." (".$inherit{$cform}.")\n";
		};
	}; 
	
};

sub calcform ( $node, $form ) {
	( $node, $form ) = @_;
	
	if ( $form eq 'pform' ) {
		$value = $node->toString;
		$value =~ s/<\/?tok[^>]*>//g;
		return $value;
		# return $node->textContent;
	} elsif ( $node->getAttribute($form) ) {
		return $node->getAttribute($form);
	} elsif ( $inherit{$form} ) {
		return calcform($ttnode, $inherit{$form});
	} else {
		return "";
	};
};