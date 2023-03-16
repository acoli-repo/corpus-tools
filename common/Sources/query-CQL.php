<?php

	function queryCQL( $query, $qid ) {
		global $settings;
		
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
			$subfolder = "/$subcorpus";
		} else {
			$cqpcorpus = strtoupper($cqpcorpus); # a CQP corpus name ALWAYS is in all-caps
			$cqpfolder = $settings['cqp']['cqpfolder'] or $cqpfolder = "cqp";
			$corpusname = $settings['cqp']['name'] or $corpusname = $cqpcorpus;
		};

		$lockn = rand(100,100000);
		// Tabulate the relevant data about the sentence (text ID, sentence ID + matching token IDs)
		// Lock for the query to prevent attacks
		$metaquery = "
	set QueryLock $lockn;
	Matches = $query;
	unlock $lockn;
	tabulate Matches match text_id, match s_id, match[0]..matchend[0] id;";
		file_put_contents("tmp/$qid.cql", $metaquery);
		$cmd = "/usr/local/bin/cqp -r cqp -D $cqpcorpus -f 'tmp/$qid.cql' | perl -e 'while(<>) { s/ /,/g; print; };' > cache/$qid"; 
		file_put_contents("tmp/query", $cmd);
		shell_exec($cmd);
		if ( !$debug ) { unlink("tmp/$qid.cql"); };
	};

	$about['CQL'] = "<p>The CQL option lets you search through the Corpus WorkBench indexed corpus in TEITOK.</p>";
	$syntax['CQL'] = "https://cwb.sourceforge.io/files/CQP_Tutorial.pdf";
	
	$hints['CQL'] = '[form="a.*"]';
		
?>