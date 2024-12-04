<?php

/*
This is PHP class from the CorpusWiki / TEITOK project 
(c) Maarten Janssen, 2014

It mimicks the essential behavious of CWB::CQP

Example usage:

require ("cwcqp.php");
$cqp = new CQP();
$cqp->exec('DICKENS');
$cqp->exec('Matches = "where"');
$results = $cqp->exec('cat Matches');
$cqp->close();

*/

class CQP
{
	// global variables
	var $active;
	var $prcs;
	var $pipes;
	var $version;
	var $registryfolder;
	var $name;
	var $corpus;
	var $folder;
	var $wordfld;
	var $logfile;
	var $unsafe;

	# Old style constuctor = should be/become redundant
    function CQP($registryfolder = "", $cqpapp = "", $cqpcorpus = "") {    
		$this->__construct($registryfolder, $cqpapp, $cqpcorpus);
	}
	
	## PHPCQP object constructor
	public function __construct($registryfolder = "", $cqpapp = "", $cqpcorpus = "") {    
    	global $settings; global $cqpcorpus; global $username;
	
    	if ( !$cqpapp ) $cqpapp = findapp('cqp');
  
  		# Determine the corpus name
		if ( !$cqpcorpus ) {
			$cqpcorpus = getset("cqp/corpus", "tt-".$foldername);
			if ( getset("cqp/subcorpora") ) {
				$subcorpus = $_GET['subc'] or $subcorpus = $_SESSION['subc-'.$foldername];
				if ( !$subcorpus ) {
					fatal("No subcorpus selected");
				};
				$_SESSION['subc-'.$foldername] = $subcorpus;
				$cqpcorpus = strtoupper("$cqpcorpus-$subcorpus"); # a CQP corpus name ALWAYS is in all-caps
				$cqpfolder = "cqp/$subcorpus";
				$corpusname = $_SESSION['corpusname'] or $corpusname = "Subcorpus $subcorpus";
				$subcorpustit = "<h2>$corpusname</h2>";
			} else {
				$cqpcorpus = strtoupper($cqpcorpus); # a CQP corpus name ALWAYS is in all-caps
				$cqpfolder = getset("cqp/cqpfolder", "cqp");
			};
		};
		$this->name = $corpusname;
		$this->corpus = $cqpcorpus;
		$this->folder = $corpusfolder;
  	
    	# Determine the registry folder
    	if ( $registryfolder == "" ) { 
			$registryfolder = getset("cqp/defaults/registry", "cqp");
    	};
    	$this->registryfolder = $registryfolder;
		if ( $cqpcorpus == "" ) $cqpcorpus = strtoupper(getset("cqp/corpus")); # a CQP corpus name ALWAYS is in all-caps
		if ( !file_exists($registryfolder."/".strtolower($cqpcorpus)) && file_exists("/usr/local/share/cwb/registry/".strtolower($cqpcorpus)) ) {
			# For backward compatibility, always check the central registry
			$registryfolder = "/usr/local/share/cwb/registry/";
		};
		
		$this->wordfld = getset("cqp/wordfld", "word");

        $this->active = true;

		$descriptorspec = array(
		   0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
		   1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
		   2 => array("pipe", "w")   // stdout is a pipe that the child will write errors to
		);
		
		$env = array(); # $env = array('some_option' => 'aeiou');

		$this->prcs = proc_open($cqpapp.' -r '.$registryfolder.' -c', $descriptorspec, $this->pipes, null, $env);
		ignore_user_abort(true); # Continue even if user terminates connection
		foreach ($this->pipes as $pipe) {
			stream_set_blocking($pipe, false);
		};

        if ( !is_resource($this->prcs) ) {
        	$prm = substr(sprintf('%o', fileperms($cqpapp)), -4);
        	if ( $username ) fatal("Failed to open CWB - please check on the server: ".$cqpapp.' -r '.$registryfolder.' -c : ');
        	else fatal("Due to an error, searching is currently not available");
        };
		
		$version = fread($this->pipes[1], 4096); # Read the version number;
		if ( preg_match ( "/^CQP\s+(?:\w+\s+)*([0-9]+)\.([0-9]+)(?:\.b?([0-9]+))?(?:\s+(.*))?$/", $version, $matches) ) {
		  $this->major_version = $matches[1];
		  $this->minor_version = $matches[2];
		  $this->beta_version = $matches[3] or $this->beta_version = 0;
		  $this->compile_date = $matches[4] or $this->compile_date = "unknown";			
		} else {
			# print "<p>ERROR: CQP backend startup failed .. $version";  exit;
		};
		
		// Select the corpus by default
		$this->exec($cqpcorpus); // Select the corpus
		$this->exec("set PrettyPrint off");

    }

	public function setcorpus() {
		$this->exec($this->corpus); // Select the corpus
		$this->exec("set PrettyPrint off");
	}

	public function setlog($fn = "tmp/cqp.log") {
		$this->logfile = $fn;
	}

    public function exec($cmd) {
    	global $settings, $username;
    	
		if ( connection_aborted() ) {
 			proc_terminate($this->prcs);
			return false;
		}
    	
    	$cmd = str_replace("\0", " ", $cmd);
    	$cmd = preg_replace("/[\n\r]/", " ", $cmd); # Keep commands on a single line
		
		// Add QueryLock around all Matches = queries
		if ( !$this->unsafe && substr($cmd, 0, 9) == "Matches =" ) {
			$lockn = rand(100,100000);
			$cmd = "set QueryLock $lockn; $cmd; unlock $lockn;";
		};

    	$cmd = preg_replace("/\s*;*\s*$/", "", $cmd).";\n;.EOL.;\n"; // Append the .EOL. command to mark the end of the CQP output
			
        if (is_resource($this->prcs)) {
			fwrite($this->pipes[0], $cmd);
			
			do {
				$line = fread($this->pipes[1], 8192);
				$data .= $line;
			} while ( !strstr($line, "-::-EOL-::-") );
			$data = preg_replace ("/-::-EOL-::-/", "", $data); 

			if ( getset("defaults/cwblog") || $this->logfile ) {
				# Write a CWB log file if so asked
				if ( !$this->logfile ) $this->logfile = getset("defaults/cwblog/file");
				if ( $fh = fopen($this->logfile, 'a') ) { 
						fwrite($fh, $cmd);
						fclose ( $fh );
				} else if ( $username ) print "<!-- error opening log file -->";
			};
						
			return $data;
		} else if ( $username ) { 
			fatal("Unable to open CWB pipe - please verify the CQP installation on the server");
		} else { 
			fatal("A fatal error occurred with the corpus - please try again later");
		};
    		
	}
	 
	public function close() {
		fwrite($this->pipes[0], "exit;");
		fclose($this->pipes[0]);
		fclose($this->pipes[1]);
        proc_terminate($this->prcs);
	    $return_value = proc_close($this->prcs);
	}

    public function version() {
		if ( $this->beta_version > 0 ) $beta = ".".$this->beta_version;
		return $this->major_version.'.'.$this->minor_version.$beta;
	}
    
}

function cqlprotect( $string ) {
	$string = preg_quote($string);
	$string = str_replace("'", "[\\']", $string);
	return $string;
};

function checkcqp($subcorpus) {
	global $settings, $username;
	$busy = false;

	if ( file_exists("tmp/recqp.pid") ) {
		if ( $subcorpus || getset("cqp/subcorpora") ) {
			$tmp = file_get_contents("tmp/recqp.pid");
			if ( preg_match( "/CQP Corpus: (.*)/", $tmp, $matches) ) $buildc = strtoupper($matches[1]);
			if ( $buildc == strtoupper($subcorpus) || $buildc == strtoupper(getset("cqp/corpus"))  || $buildc == strtoupper(getset("cqp/corpus")."-".$subcorpus) ) $busy = 1;
		} else {
			$buildc = "the entire CQP corpus";
			$busy = 1;
		};
	};
	if ( $username && $busy ) fatal ( "Search is currently unavailable because $buildc is being rebuilt. Please try again in a couple of minutes." );
	else if ( $busy ) fatal ( "Search is currently unavailable because the CQP corpus is being rebuilt. Please try again in a couple of minutes." );

	return true;
};

?>
