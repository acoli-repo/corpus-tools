<?php

	# Admin module to edit the character conversions

	if ( $act == "save" ) {
	

	} else {
	
		$maintext .= "<h1>Hard to type characters</h1>
			<p>TEITOK provides the option to convert hard-to-type symbols on-the-fly, meaning you can define
				symbols that are easy to type, but not used in your corpus to function as stand-in for those 
				symbols you do need. For instance, mediaeval texts often use a long s (ſ) which is not easy to 
				type. If your corpus does not contain a German sharp s (ß), which on many keyboards you can type
				as alt-s, then you can use ß to mean ſ in your corpus - that is to say, have TEITOK convert every
				ß to ſ. In the page-by-page transcription, you can have those all be replaced when you convert your
				pre-TEI document to TEI, or you can have them be replaced as you type. Below is the list 
				of character conversionscurrently defined in this project.";
				
		if ( $act == "edit" ) $maintext .= "<form action='index.php?action=$action&act=save' method=post>";
		$maintext .= "<table width='100%'>
		<tr><td valign=top>On-the-fly conversions
		<table>
		<tr><th>Source<th>Target"; $i = 0;
		foreach ( getset('input/replace', array()) as $key => $item ) {
			$val = $item['value'];
			$chareqjs .= "$sep $key = $val"; 
			$charlist .= "ces['$key'] = '$val';";
			$sep = ","; $i++;
			if ( $act == "edit" ) 
				$maintext .= "<tr><td><input size=5 name='input[$i]' value='$key'><td><input size=5 name='output[$i]' value='{$item['value']}'>";
			else 
				$maintext .= "<tr><td>$key<td>{$item['value']}";
		};
		if ( $act == "edit" )  for ( $j=0; $j<5; $j++ ) {
			$i++;
			$maintext .= "<tr><td><input size=5 name='input[$i]' value=''><td><input size=5 name='output[$i]' value=''>";
		};

		$maintext .= "</table><td valign=top>Textual simplifications
		<table>
		<tr><th>Source<th>Target"; $i = 0;
		foreach ( getset('input/simplify', array()) as $key => $item ) {
			$val = $item['value'];
			$chareqjs .= "$sep $key = $val"; 
			$charlist .= "ces['$key'] = '$val';";
			$sep = ","; $i++;
			if ( $act == "edit" ) 
				$maintext .= "<tr><td><input size=5 name='complex[$i]' value='$key'><td><input size=5 name='simple[$i]' value='{$item['value']}'>";
			else 
				$maintext .= "<tr><td>$key<td>{$item['value']}";
		};
		if ( $act == "edit" )  for ( $j=0; $j<5; $j++ ) {
			$i++;
			$maintext .= "<tr><td><input size=5 name='complex[$i]' value=''><td><input size=5 name='simple[$i]' value=''>";
		};
		$maintext .= "</table></table>";
		if ( $act == "edit" ) $maintext .= "<p><input type=submit value=Save></form>";
		
	};
	
?>