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
	
	## PHPCQP object constructor
    function CQP($registryfolder = "", $cqpapp = "/usr/local/bin/cqp", $cqpcorpus = "") {
    	global $settings; global $cqpcorpus;
  
  		# Determine the corpus name
		if ( !$cqpcorpus ) {
			$cqpcorpus = $settings['cqp']['corpus'] or $cqpcorpus = "tt-".$foldername;
			if ( $settings['cqp']['subcorpora'] ) {
				$subcorpus = $_SESSION['subc'] or $subcorpus = $_GET['subc'];
				if ( !$subcorpus ) {
					fatal("No subcorpus selected");
				};
				$_SESSION['subc'] = $subcorpus;
				$cqpcorpus = strtoupper("$cqpcorpus-$subcorpus"); # a CQP corpus name ALWAYS is in all-caps
				$cqpfolder = "cqp/$subcorpus";
				$corpusname = $_SESSION['corpusname'] or $corpusname = "Subcorpus $subcorpus";
				$subcorpustit = "<h2>$corpusname</h2>";
			} else {
				$cqpcorpus = strtoupper($cqpcorpus); # a CQP corpus name ALWAYS is in all-caps
				$cqpfolder = $settings['cqp']['cqpfolder'] or $cqpfolder = "cqp";
			};
		};
		$this->name = $corpusname;
		$this->corpus = $cqpcorpus;
		$this->folder = $corpusfolder;
  	
    	# Determine the registry folder
    	if ( $registryfolder == "" ) { 
			$registryfolder = $settings['cqp']['defaults']['registry'] or $registryfolder = "cqp";
    	};
    	$this->registryfolder = $registryfolder;
		if ( $cqpcorpus == "" ) $cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
		if ( !file_exists($registryfolder."/".strtolower($cqpcorpus)) && file_exists("/usr/local/share/cwb/registry/".strtolower($cqpcorpus)) ) {
			# For backward compatibility, always check the central registry
			$registryfolder = "/usr/local/share/cwb/registry/";
		};
		
		$this->wordfld = $settings['cqp']['wordfld'] or $this->wordfld = "word";

        $this->active = true;

		$descriptorspec = array(
		   0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
		   1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
		   2 => array("pipe", "w")   // stdout is a pipe that the child will write errors to
		);
		
		$env = array(); # $env = array('some_option' => 'aeiou');

		$this->prcs = proc_open($cqpapp.' -r '.$registryfolder.' -c', $descriptorspec, $this->pipes, '-c', $env); # This should be -c
		foreach ($this->pipes as $pipe) {
			stream_set_blocking($pipe, false);
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
    	
    	$cmd = str_replace("\0", " ", $cmd);
    	$cmd = preg_replace("/[\n\r]/", " ", $cmd); # Keep commands on a single line
    	$cmd = preg_replace("/\s*;*\s*$/", "", $cmd).";\n;.EOL.;\n"; // Append the .EOL. command to mark the end of the CQP output
			
        if (is_resource($this->prcs)) {
			fwrite($this->pipes[0], $cmd);
			
			do {
				$line = fread($this->pipes[1], 8192);
				$data .= $line;
			} while ( !strstr($line, "-::-EOL-::-") );
			$data = preg_replace ("/-::-EOL-::-/", "", $data); 

			if ( $settings['defaults']['cwblog'] || $this->logfile ) {
				# Write a CWB log file if so asked
				if ( !$this->logfile ) $this->logfile = $settings['defaults']['cwblog']['file'];
				if ( $fh = fopen($this->logfile, 'a') ) { 
						fwrite($fh, $cmd);
						fclose ( $fh );
				} else if ( $username ) print "<!-- error opening log file -->";
			};
						
			return $data;
		} else if ( $username ) { 
			fatal("Unable to open CWB pipe");
		} else { 
			fatal("A fatal error occurred with the corpus - please try again later");
		};
    		
	}
	 
	public function close() {
		fwrite($this->pipes[0], "exit;");
		fclose($this->pipes[0]);
		fclose($this->pipes[1]);
	    $return_value = proc_close($this->prcs);
	}

    public function version() {
		if ( $this->beta_version > 0 ) $beta = ".".$this->beta_version;
		return $this->major_version.'.'.$this->minor_version.$beta;
	}
		    
    
}

?>
