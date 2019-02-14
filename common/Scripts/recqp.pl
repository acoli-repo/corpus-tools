use XML::LibXML;

$scriptname = $0;

# Read the parameter set
my $settings = XML::LibXML->load_xml(
	location => "Resources/settings.xml",
); if ( !$settings ) { print FILE "Not able to parse settings.xml"; exit; };

if ( $settings->findnodes("//cqp/\@corpus") ) {
	$cqpcorpus = $settings->findnodes("//cqp/\@corpus")->item(0)->value."";
} else { print FILE "Cannot find corpus name"; exit; };

if ( $settings->findnodes("//cqp/defaults/\@registry") ) {
	$regfolder = $settings->findnodes("//cqp/defaults/\@registry")->item(0)->value."";
} else { $regfolder = "cqp"; };

open FILE, ">tmp/recqp.pid";$\ = "\n";

$starttime = time(); 
print FILE 'Regeneration started on '.localtime();
print FILE 'Process id: '.$$;
print FILE "CQP Corpus: $cqpcorpus";
print FILE 'Removing the old files';
print FILE 'command:
/bin/rm -f cqp/*';
`/bin/rm -f cqp/*`;

print FILE '----------------------';
print FILE '(1) Encoding the corpus';
print FILE "command:
/usr/local/bin/tt-cwb-encode -r $regfolder";
`/usr/local/bin/tt-cwb-encode -r $regfolder`;

print FILE '----------------------';
print FILE '(2) Creating the corpus';
print FILE "command:
/usr/local/bin/cwb-makeall  -r $regfolder $cqpcorpus";
`/usr/local/bin/cwb-makeall  -r $regfolder $cqpcorpus`;

print FILE '----------------------';
$endtime = time();
print FILE 'Regeneration completed on '.localtime();
`mv tmp/recqp.pid tmp/recqp.log`;
close FILE;

$timelapse = $endtime - $starttime;
$tmp = `wc -c cqp/word.corpus`;
$size = $tmp/4;
open FILE, ">tmp/lastupdate.log";
print FILE localtime($starttime), $timelapse, $size;
close FILE;
