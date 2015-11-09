# Train a Neotag parameter set on all (tagged) XML files in --infiles
# Save parameter set to --folder

use encoding 'utf8';
use Getopt::Long;
use HTML::Entities;
use XML::LibXML;
use XML::XPath;
use XML::XPath::XMLParser;

$\ = "\n"; $, = "\t";

GetOptions (
            'pdf=s' => \$pdffile,
            'start=i' => \$start,
            'end=i' => \$end,
            'pagestyle=i' => \$pagestyle,
            'teiname=s' => \$teiname, # Take words without pos into account
            'debug' => \$debug,
            );
            
for ( $i=$start; $i<=$end; $i++ ) {

	if ( $pagestyle == 2 ) {
		$pagecnt = $i-$start+1;
		$pagenr = int($pagecnt/2);
		if ( $pagenr == $pagecnt/2 ) { 
			$pageid = ($pagenr-0)."v";
			$page = int($i/2)+0; $pageside = 1;
			$crop = " -crop 50%x100%+0+0 ";
		} else {
			$pageid = ($pagenr+1)."r";
			$page = int($i/2)-1; $pageside = 2;
			$crop = "  -gravity East -crop 50%x100%+0+0 ";
		}; 
	} else {
		$pageid = $i;
	};

	$pagelist .= "$sep<pb n=\"$pageid\" facs=\"$teiname\_$pageid.jpg\"/>";
	$sep = "\n";
	$cmd = "convert pdf/$pdffile\[$page\] $crop Facsimile/$teiname\_$pageid.jpg";
	print $i, $pageid, $page, $pageside, $cmd;
	`$cmd`;
};

$tei = "<TEI>
<teiHeader>
</teiHeader>
<text>
$pagelist
</text>
</TEI>";

open FILE, ">xmlfiles/$teiname.xml";
print FILE $tei;
close FILE;

