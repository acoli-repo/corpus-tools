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
	
	## PHPCQP object constructor
    function CQP($registry = "") {
    	global $settings;
    	if ( $registry == "" ) { 
			$registryfolder = $settings['cqp']['defaults']['registry'] or $registryfolder = "cqp";
    	};
		$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
		if ( !file_exists($registryfolder.strtolower($cqpcorpus)) && file_exists("/usr/local/share/cwb/registry/".strtolower($cqpcorpus)) ) {
			# For backward compatibility, always check the central registry
			$registryfolder = "/usr/local/share/cwb/registry/";
		};

        $this->active = true;

		$descriptorspec = array(
		   0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
		   1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
		   2 => array("pipe", "w")   // stdout is a pipe that the child will write to
		);
		
		$env = array(); # $env = array('some_option' => 'aeiou');

		$this->prcs = proc_open('/usr/local/bin/cqp -r '.$registryfolder.' -c', $descriptorspec, $this->pipes, '-c', $env); # This should be -c
		foreach ($this->pipes as $pipe) {
			stream_set_blocking($pipe, false);
		}
		
		$version = fread($this->pipes[1], 4096); # Read the version number;
		if ( preg_match ( "/^CQP\s+(?:\w+\s+)*([0-9]+)\.([0-9]+)(?:\.b?([0-9]+))?(?:\s+(.*))?$/", $version, $matches) ) {
		  $this->major_version = $matches[1];
		  $this->minor_version = $matches[2];
		  $this->beta_version = $matches[3] or $this->beta_version = 0;
		  $this->compile_date = $matches[4] or $this->compile_date = "unknown";			
		} else {
			// print "<p>ERROR: CQP backend startup failed .. $version";  exit;
		};

    }

    public function exec($cmd) {
    	global $settings;
    	
    	$cmd = preg_replace("/\n/", "", $cmd);
    	$cmd = preg_replace("/;*$/", ";\n;.EOL.;\n", $cmd); // Append the .EOL. command to mark the end of the CQP output
		
        if (is_resource($this->prcs)) {
			fwrite($this->pipes[0], $cmd);
			
			do {
				$line = fread($this->pipes[1], 8192);
				$data .= $line;
			} while ( !strstr($line, "-::-EOL-::-") );
			$data = preg_replace ("/-::-EOL-::-/", "", $data); 

			if ( $settings['defaults']['cwblog'] )
			if ( $fh = fopen($settings['defaults']['cwblog']['file'], 'a') ) { 
					fwrite($fh, $cmd);
					fclose ( $fh );
			} else print "<!-- error opening log file -->";
			
			return $data;
		} else { 
			print "<p>Error: CQP Pipe not open"; exit;
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
