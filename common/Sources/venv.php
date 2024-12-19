<?php

# A class to run python under an Apache managed venv

class VENV
{
	var $venvdir;

	function __construct($venvdir = null) {	
		# Establish where to store the venv
		global $sharedfolder;

		if ( $venvdir ) $this->venvdir = $venvdir;		
		else {
			$this->venvdir = getset("defaults/base/venv");
			if ( !$this->venvdir ) {
				if ( $sharedfolder ) $this->venvdir = "$sharedfolder/Resources/venv";
				else $this->venvdir = "Resources/venv";
			};
		};
				
		# Create a new venv if none exists
		if ( !file_exists( $this->venvdir ) ) {
			shell_exec("python -m venv $this->venvdir");
		};
	}
	
	function installmod ( $modules ) {
		foreach ( explode(",", $modules) as $module ) {
			$test = shell_exec("import $modules; print(\"test\")");
			if ( $test == "test" ) continue;
		
			$maintext .= shell_exec("$this->venvdir/bin/pip install $module");
		};
	}
		
	function version ( ) {
		return shell_exec("$this->venvdir/bin/python --version");
	}
	
	function path ( ) {
		return $this->venvdir;
	}
	
	function exec ( $script ) {
		global $debug, $maintext;
		$cmd = "$this->venvdir/bin/python $script";
		if ( $debug ) $maintext .= "<p>VENV command: $cmd";
		return shell_exec($cmd);
	}
	
};

?>