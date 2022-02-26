use XML::LibXML;
use POSIX qw(strftime);
use Getopt::Long;

GetOptions ( ## Command line options
		'debug' => \$debug, # debugging mode
		'test' => \$test, # tokenize to string, do not change the database
		'sub=s' => \$subc, # set which subcorpus to compile
		'setfile=s' => \$setfile, # alternative settings file
		);

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

# See if we should export subcorpora
if ( $settings->findnodes("//cqp/\@subcorpora") ) {
	$sub = $settings->findnodes("//cqp/\@subcorpora")->item(0)->value."";
} else { $sub = 0; };
if ( $settings->findnodes("//cqp/\@searchfolder") ) {
	$search = $settings->findnodes("//cqp/\@searchfolder")->item(0)->value."";
} else { $search = "xmlfiles"; };

open FILE, ">tmp/recqp.pid";$\ = "\n";

if ( $sub ) { $scf = "$sub/"; };

$starttime = time(); 
print FILE 'Regeneration started on '.localtime();
print FILE 'Process id: '.$$;
print FILE "CQP Corpus: $cqpcorpus";
print FILE 'Removing the old files';
print FILE 'command:
/bin/rm -Rf cqp$scf/*';
`/bin/rm -Rf cqp$scf/*`;

if ( $setfile ) {
	$setfile = " --settings='$setfile'"; 
};

if ( $sub ) {

	print "Dealing with subcorpora in $search";	

	while ( <$search/*> ) {
		$sf = $_; ( $fn = $sf ) =~ s/.*\///;
		
		if ( $subc && $fn ne $subc ) { next; };
		print "Creating $fn";	

		$subcorpus = "$cqpcorpus-$fn";
		`mkdir -p cqp/$fn`;
		
		print FILE '----------------------';
		print FILE '(1) Encoding subcorpus $fn';
		$cmd = "/usr/local/bin/tt-cwb-encode -r $regfolder --folder='$sf' --corpusfolder='cqp/$fn' --corpus='$subcorpus' $setfile";
		print FILE "command:
		$cmd";
		`$cmd`;

		print FILE '----------------------';
		print FILE '(2) Creating subcorpus $fn';
		print FILE "command:
		/usr/local/bin/cwb-makeall  -r $regfolder $subcorpus";
		`/usr/local/bin/cwb-makeall  -r $regfolder $subcorpus`;

		if ( $sub eq 'both' ) {
			print FILE '----------------------';
			print FILE '(1) Encoding full corpus$';
			$cmd = "/usr/local/bin/tt-cwb-encode -r $regfolder --corpusfolder='cqp/full' --corpus='$cqpcorpus'  $setfile";
			print FILE "command:
			$cmd";
			`$cmd`;

			print FILE '----------------------';
			print FILE '(2) Creating subcorpus $fn';
			print FILE "command:
			/usr/local/bin/cwb-makeall -r $regfolder $cqpcorpus";
			`/usr/local/bin/cwb-makeall -r $regfolder $cqpcorpus`;
		};

	};

} else {

	print FILE '----------------------';
	print FILE '(1) Encoding the corpus';
	$cmd = "/usr/local/bin/tt-cwb-encode -r $regfolder  $setfile";
	print FILE "command:
	$cmd ";
	`$cmd`;

	print FILE '----------------------';
	print FILE '(2) Creating the corpus';
	$cmd = "/usr/local/bin/cwb-makeall  -r $regfolder $cqpcorpus";
	print FILE "command:
	$cmd
	";
	`$cmd`;

};

print FILE '----------------------';
$endtime = time();
print FILE 'Regeneration completed on '.localtime();
`mv tmp/recqp.pid tmp/recqp.log`;
close FILE;

$starttxt = strftime("%Y-%m-%d", localtime($starttime));
$timelapse = $endtime - $starttime;
if ( !$sub ) {
	$tmp = `wc -c cqp/word.corpus`;
	$size = $tmp/4; $, = "\t";
};
open FILE, ">tmp/lastupdate.log";
print FILE $starttxt, $timelapse, $size;
close FILE;
