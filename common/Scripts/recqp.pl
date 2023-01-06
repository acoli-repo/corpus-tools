use XML::LibXML;
use POSIX qw(strftime);
use Getopt::Long;

GetOptions ( ## Command line options
		'debug' => \$debug, # debugging mode
		'verbose' => \$verbose, # verbose mode
		'test' => \$test, # tokenize to string, do not change the database
		'sub=s' => \$subc, # set which subcorpus to compile
		'setfile=s' => \$setfile, # alternative settings file
		);

$scriptname = $0;

open FILE, ">tmp/recqp.pid";$\ = "\n";

if ( $setfile ) {
	$setopt = " --settings='$setfile'"; 
	print FILE "Using shared $setfile";
} else {
	$setfile = "Resources/settings.xml";
};

if ( $debug ) { $verbose = 1; };

# Read the parameter set
my $settings = XML::LibXML->load_xml(
	location => $setfile,
); if ( !$settings ) { print FILE "Not able to parse $setfile"; exit; };

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


if ( $subc ) { $scf = "/$subc"; };

$starttime = time(); 
print FILE 'Regeneration started on '.localtime();
print FILE 'Process id: '.$$;
print FILE "CQP Corpus: $cqpcorpus";
print FILE 'Removing the old files';
print FILE "command:
/bin/rm -Rf cqp$scf/*";
`/bin/rm -Rf cqp$scf/*`;


if ( $sub ) {

	print "Dealing with subcorpora in $search";	

	while ( <$search/*> ) {
		$sf = $_; ( $fn = $sf ) =~ s/.*\///;
		
		if ( !-d "$search/$fn" ) { next; };
		$test = `find $search/$fn -name '*.xml' | head -n 3`;
		if ( !$test ) { 
			if ( $verbose ) { print "Skipping empty subfolder: $fn"; }
			next;
		};
		
		if ( $subc && $fn ne $subc ) { next; };
		print "Creating $fn";	

		$subcorpus = "$cqpcorpus-$fn";
		`mkdir -p cqp/$fn`;
		
		if ( $settings->findnodes("//cqp/subcorpora") ) {
			$cqpcorpus = $settings->findnodes("//cqp/\@corpus")->item(0)->value."";
		} elsif ( $settings->findnodes("//title/\@display") ) {
			$corpname = $settings->findnodes("//title/\@display")->item(0)->value."";
			$subcorpusname = $corpname." - ".$fn;
		} else {
			$subcorpusname = $subcorpus;
		};
		
		if ( $verbose ) { print "Dealing with subcorpus $subcorpus - $subcorpusname"; }
		
		print FILE '----------------------';
		print FILE '(1) Encoding subcorpus $fn';
		$cmd = "/usr/local/bin/tt-cwb-encode -r $regfolder --folder='$sf' --corpusfolder='cqp/$fn' --corpus='$subcorpus' --name='$subcorpusname' $setopt";
		print FILE "command:
		$cmd";
		`$cmd`;

		print FILE '----------------------';
		print FILE '(2) Creating subcorpus $fn';
		if ( -e "/usr/local/bin/cwb-makeall" ) {
			$cwbmakeall = "/usr/local/bin/cwb-makeall";
		} elsif ( -e "/usr/bin/cwb-makeall" ) {
			$cwbmakeall = "/usr/bin/cwb-makeall";
		} else {
			$cwbmakeall = "/usr/local/bin/cwb-makeall";
			print FILE "cwb-makeall not installed - this step will fail";		
		};	
		$cmd = "$cwbmakeall  -r $regfolder $subcorpus";
		print FILE "command:
		$cmd";
		`$cmd`;

		if ( $sub eq 'both' ) {
			print FILE '----------------------';
			print FILE '(1) Encoding full corpus$';
			$cmd = "/usr/local/bin/tt-cwb-encode -r $regfolder --corpusfolder='cqp/full' --corpus='$cqpcorpus'  $setopt";
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
	$cmd = "/usr/local/bin/tt-cwb-encode -r $regfolder  $setopt";
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
