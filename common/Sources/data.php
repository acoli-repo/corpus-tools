<?php
	// Script to display which attributes are used in the corpus
	// called upon by the centralized TEITOK search index (optional)
	// (c) Maarten Janssen, 2015

	if ( $act == "patts" ) {
		print join(",", array_keys($settings['cqp']['pattributes'])); exit;
	};

?>