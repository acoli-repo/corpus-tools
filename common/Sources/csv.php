<?php
	// A GUI for the 2 perl script to export and import TEI to CSV
	// csv2tei.pl and tei2csv.pl
	// (c) Maarten Janssen, 2016
	
	check_login();

	$date = date("ymd-his");
	
	# semi-protect files (keep them local)
	$file = str_replace("..", "", $_GET['file']);
	$file = preg_replace("/^\//", "", $file);

	$maintext .= "<h1>Batch edit XML headers</h1>";

if ( $act == "export" && $_POST['queries']  ) {
	// create a CSV file
	if ( $_POST['type'] ) $type = "_".$_POST['type'];

	$filename = "export_{$date}$type.csv";
	$cmd = "perl $ttroot/common/Scripts/tei2csv.pl --xmlfolder='{$_POST['xmlfolder']}' --restrfld='{$_POST['restrfld']}' --restrval='{$_POST['restrval']}' --queries='{$_POST['queries']}' --header --info --csvfile='tmp/$filename' > /dev/null 2>tmp/export.error &";
	exec($cmd);
	
	$newurl = "index.php?action=$action&act=wait&file=tmp/$filename";
	$maintext .= "<p>You export is currently being created. You will be redirected to the waiting area.
		If it does not open, click <a href='$newurl'>here</a>.
		";	
	header( "refresh:1;url=$newurl" );
	
	$maintext .= "<p>$cmd";

} else if ( $act == "wait" && $file  ) {
	
	if ( !file_exists($file) ) fatal ( "No such CSV export: $file" );
	$tmp = shell_exec("wc '$file'"); $cnt = preg_replace("/^\s*(\d+)/", "\\1", $tmp ) -1;

	if ( file_exists("$file.info") ) {
		$next = "index.php?action=$action&act=view&file=$file";
		print  "Process finished. Reloading to <a href='$next'>$next</a>.
			<script>top.location = '$next';</script>";
		exit;
	} else {
		$maintext .= "<h2>Waiting for Processes</h2>
			<p>This page will refresh every 3 seconds until the export is finished.
			<p>Files already exported: $cnt";
		header( "refresh:3" );
	};
	
} else if ( $act == "upload" ) {

	$maintext .= "<h2>Import CSV</h2>
		<p>After you created a CSV via this interface, you can download the CSV, modify it externally using a texteditor
			or spreadsheet program, and then paste the modified CSV here. The CSV will not be saved directly,
			but the modifed data will be loaded into the edit table, from where you can verify the data are correct
			and save.
			
			<form action='index.php?action=$action&act=edit&file=$file' method=post>
			<textarea name=csv></textarea>
			<input type=submit value=Submit>
			</form>";
		
} else if ( $act == "export" ) {
	// Define a new export	
	
	// read the items from teiHeader-edit.tpl
	foreach ( $settings['teiheader'] as $key => $val ) {
		$xpathselect .= "<option value='{$val['xpath']}'>{$val['display']}</option>";
	};
	
	// read the subfolders of xmlfiles
	foreach ( subdirs("xmlfiles") as $dir ) {
		$folderselect .= "<option value=\"$dir\">$dir</option>";
	};
	
	$maintext .= "
	<p>Define below which fields of which files you want to export to a CSV file
	
	<hr>
	<form action='index.php?action=$action&act=export' method=post>

	<h2>Fields</h2>
	<p>Define xpath queries (comma separated) for the fields from each XML file you want to export (or select from the teiHeader-edit template)
	<p><input name=queries id=queries size=100> 
	<p>Select from teiHeader definition: <select name=selector onChange=\"addxp(this);\"><option value=\"\">[select]</option>$xpathselect</select>
	<h2>Folder</h2>
	<p>Define the folder from which you want to select the XML files
	<p><select name=xmlfolder><option value=\"xmlfiles\">xmlfiles</option>$folderselect</select>
	<h2>Restrictions</h2>
	<p>Restrict which XML files from the selected folder are exported (optional)
	<p>Field: <input name=restrfld size=60> - a field that has to exist for the XML to be include (XPath)
	<p>Value: <input name=restrval size=60> - a value the restriction field has to have (regular expression)
	<hr>
	<p> <input type=submit value=Create> <a href='index.php?action=$action'>cancel</a>
	</form>
	<script>
		function addxp(elm) {
			var fld = document.getElementById('queries');
			if (fld.value) fld.value += ',';
			fld.value += elm.value;
		};
	</script>
	";
	
} else if ( ( $act == "view" || $act == "edit" ) && $file ) {
	// Define a new export	
		
	$lines = explode ( "\n", file_get_contents($file) );
	
	// If the first line contains [fn] it is a header
	if ( strpos( $lines[1], "[fn]" ) !== FALSE ) {
		$desc = explode ( "\t", array_shift($lines) );
		$header = explode ( "\t", array_shift($lines) );
	} else if ( strpos( $lines[0], "[fn]" ) !== FALSE ) {
		$header = explode ( "\t", array_shift($lines) );
	} else {
		$header = array();
	};
	
	if ( $_POST['csv'] ) {
		$postcsv = explode ( "\n", $_POST['csv'] );
	
		// If the first line contains [fn] it is a header
		if ( strpos( $postcsv[1], "[fn]" ) !== FALSE ) {
			$desc = explode ( "\t", array_shift($postcsv) );
			$postheader = explode ( "\t", array_shift($postcsv) );
		} else if ( strpos( $postcsv[0], "[fn]" ) !== FALSE ) {
			$postheader = explode ( "\t", array_shift($postcsv) );
		} else {
			$postheader = array();
		};
	}; 
	
	
	$infofile = $file.".info";
	if ( !file_exists($file) ) {
		fatal("No such CSV file: $file");
	} else if ( file_exists($infofile) ) {
		$tmp = file_get_contents($infofile);
		$infoxml = simplexml_load_string($tmp);
		
		$maintext .= "<table>";
		foreach ( $infoxml->children() as $key => $childnode ) {
			$name = $childnode['display'] or $name = $key;
			$maintext .= "<tr><th>$name<td>".$childnode;
		};
		$maintext .= "</table><hr>";
		
	} else {
		$maintext .= "<p>File used: $file
			<p style='color: #992000; font-weight: bold;'>No .info file found - probably creation of the CSV file
				has not yet terminated.
			</p><hr>";
	};

	if ( $act == "edit" && count($lines) > 1000 ) {
		$act = "view";
		$maintext .= "<p style='font-weight: bold; color: #992200'>Editing has been disabled because there are more than 1000 records
			in this CSV file, which cannot be processed in an HTML form in normal browsers. 
			Please restrict the number of files or use csv2tei and tei2csv from the command line.</p><hr>";
	} else if ( $act == "edit" ) {
		$maintext .= "
			<form action=\"index.php?action=$action&act=save\" method=post>
			<input type=hidden name=file value=\"".str_replace('"', '&quot;', $file)."\">
			";
	};

	$maintext .= "<table><tr><td>";
	
	// Show the header line if there is one
	if ( count($header) ) {
		foreach ( $header as $nr => $fld ) {
			$thxp = "//teiheader/item[@xpath=".xpath_quote($fld)."]";
			if ( $desc[$nr] != "" ) {
				$maintext .= "<th title='$fld'>{$desc[$nr]}";				
			} else if ( $setnode = current($settingsxml->xpath($thxp)) ) {
				$maintext .= "<th title='$fld'>{$setnode['display']}";				
			} else {
				$maintext .= "<th>$fld";
			};
			if ( $headedit ) {
				$maintext .= "<input size=40 name=head[$nr] value=\"$fld\">";			
			} else {
				$maintext .= "<input type=hidden name=head[$nr] value=\"".str_replace('"', '&quot;', $fld)."\">";			
			};
		};	
	};
	
	foreach ( $lines as $idx => $line ) {
		if ( !$line ) continue;
			$maintext .= "<tr>";
			$linea = explode("\t", $line);
			$posta = explode("\t", $postcsv[$idx]);
			$maintext .= "<td><input type=checkbox name='sel[$idx]' id='$idx' class=selbox>";
		foreach ( $linea as $nr => $fld ) {
			if ( $header[$nr] == "[fn]" ) { 
				$filename = preg_replace("/.*\//", "", $fld);
				$fldtxt = "<a href='index.php?action=file&cid=$fld' target=edit>$filename</a>";
				$fldtxt .= "<input type=hidden size=40 name=vals[$idx][$nr] value=\"$fld\">";			
			} else if ( $act == "edit" && $header[$nr] ) {
				if ( $posta[0] == $linea[0] ) {	
					$fld = $posta[$nr];
				};
				$fldtxt = "<input size=40 name=vals[$idx][$nr] id='valfld-$idx' value=\"$fld\">";			
			} else {
				$fldtxt = $fld;
			};
			$maintext .= "<td>$fldtxt";
		};	
	};
			
	$cnt = count($lines); $rcnt = count($lines[0]);
	$maintext .= "</table><hr>$cnt rows";
	
	if ( $act == "edit" ) {
		$maintext .= "
			<p><input type=submit value=\"Save Changes\"> <a href='index.php?action=$action'>cancel</a> | <a href='index.php?action=$action&act=upload&file=$file'>upload modified CSV</a> </form>";
			
		# Only for one lines
		if ( $rcnt==1 ) $maintext .= "<hr><p>Change selected rows - from: <input size=25 id=fromre> - to: <input size=25 id=tore> <button onClick='multichange();'>Change</button> <a onClick='selall(1);'>Select all</a> &bull; <a onClick='selall(0);'>Select empty fields</a>
			<script language=Javascript>
				function selall(ala) {
				  checkboxes = document.getElementsByClassName('selbox');
				  for (i=0; i<checkboxes.length; i++) {
				    checkbox = checkboxes[i];
					var id = checkbox.getAttribute('id');
					var varfld = document.getElementById('valfld-'+id);
					if ( ala || varfld.value == '' ) { checkbox.checked = true; };	
				  };	
				};
				function multichange() {
					var fromval = document.getElementById('fromre').value;
					var tore = document.getElementById('tore').value;
					if ( tore != '' ) { 
					  checkboxes = document.getElementsByClassName('selbox');
					  for (i=0; i<checkboxes.length; i++) {
						checkbox = checkboxes[i];
						var id = checkbox.getAttribute('id');
						var varfld = document.getElementById('valfld-'+id);
						if ( checkbox.checked ) {
							var fromre = new RegExp(fromval);
							if ( ( fromval == '' && varfld.value == '' ) || ( fromval != '' && varfld.value.match(fromre) ) ) {
								if ( fromval == '' ) {
									toval = tore;
								} else {
									toval = varfld.value.replace(fromre, tore);
								};
								varfld.value = toval;
							};
						};		
					  };	
					};
				};
			</script>";
	} else {
		$maintext .= " &bull; <a href='index.php?action=$action&act=choose'>back to file list</a>";
		if ( count($lines) < 1000 ) $maintext .= " &bull; <a href='index.php?action=$action&act=edit&file=$file'>edit</a>";
		else $maintext .= " - <i>editing not possible with selections of > 1000 files</i>";
	};
		$maintext .= "</p>";
	
} else if ( $act == "save" ) {
	// save a modified CSV file
	
	$filename = $_POST['file'];
	if ( file_exists($filename) ) {
		$orgname = $filename.".org";
		if ( !file_exists($orgname) ) {
			copy ( $filename, $orgname );
		};
		$orglines = explode("\n", file_get_contents($orgname));
	};
	
	$filetext .= join ( "\t", $_POST['head'] )."\n";
	
	foreach ( $_POST['vals'] as $i => $row ) {
		$rowtext = join ( "\t", $row );
		$filetext .= "$rowtext\n";
		if ( $rowtext != $orglines[$i+1] ) {
			$changetext .= "<tr><td style='color: #009900;'>$rowtext<td style='color: #990000;'>".$orglines[$i+1];
		};
	};
	if ( !$changetext ) $changetext = "<p><i>No rows were modified</i>";
	else {
		$changetext = "<tr><th>New value<th>Original".$changetext;
		$somedone = 1;
	};
	 
	file_put_contents($filename, $filetext);
	
	$maintext .= "<p>Your modified CSV file has been saved. Below is a list of the modification wrt to the initial 
		export.<p>Filename:  $filename
		<hr>
		<table>
		$changetext
		</table>
		<hr>
		<ul>";
	if ( $somedone ) $maintext .= "<li> Click <a href='index.php?action=$action&act=import&file=$filename'>here</a> to apply the changes to the XML files";
	$maintext .= "<li> Click <a href='index.php?action=$action&act=edit&file=$filename'>here</a> to edit the CSV file again
		</ul>
		";
		
} else if ( $act == "choose" ) {
	// choose an existing CSV file	
	
	$maintext .= "
		<p>Choose a CSV file from the list below</p><hr>
		<table>";

	$tmp = glob ("tmp/*.csv");
	foreach ( $tmp as $file ) {
		$name = str_replace("tmp/", "", $file);
		$maintext .= "<tr>
			<td><a href='index.php?action=$action&act=download&file=$file'>download</a>
			<td><a href='index.php?action=$action&act=edit&file=$file'>edit</a>
			<td><a href='index.php?action=$action&act=view&file=$file'>view</a>
			<td>$name";
	};
	if ( !$tmp ) $maintext .= "<p><i> -- no CSV files yet</i>";
	$maintext .= "</table>
		<hr><p><a href='index.php?action=$action'>back to module index</a>";
	
} else if ( $act == "download" && $file ) {
	// download a CSV file
		$name = str_replace("tmp/", "", $file);
	
		header("Content-type: text/csv"); 
		header('Content-disposition: attachment; filename="'.$name.'"');
		
		print file_get_contents($file);
		exit;
	
} else if ( $act == "import" && $file ) {
	// import a modified CSV file
	
	$filename = $_GET['file'];
	$cmd = "perl $ttroot/common/Scripts/csv2tei.pl --csvfile='$filename' --debug";
	$maintext .= "<p>The changes in $filename are being applied to the corresponding XML files - transcript is below<hr>";

	$maintext .= "<pre>".shell_exec($cmd)."</pre>
		<hr><p><a href='index.php?action=$action'>back to module index</a>
			&bull; <a href='index.php?action=$action&act=export'>create a new CSV</a>";
	
} else {

	$maintext .= "
		<p>TEITOK provides two script to interact with sets of XML files in a TEITOK corpus using a spreadsheet: 
			csv2tei.pl and tei2csv.pl. These scripts are best used from the command line, especially with larger corpora
			containing many XML files. First, export a number of fields from the XML files to a CSV file, then open that 
			CSV file in a text editor or spreadsheet program, and then import the modified data back into the XML files.
		<p>For simple change, you can also use this interface to indicate what you want to change, make the changes in a 
			simple HTML form, after which the changes will be automatically applied to all the XML files. This allows you
			to quickly change information in the teiHeader of various files at the same time. 
			
		<hr>
		
		<ul>
		<li> <a href='index.php?action=$action&act=choose'>use an existing CSV file</a>
		<li> <a href='index.php?action=$action&act=export'>create a new CSV file</a>
		</ul>
		
		";

};

function subdirs ( $dir ) {
	$array = array ( );
	foreach ( scandir ( $dir ) as $item ) {
		if ( substr($item, 0, 1) == "." ) { continue; };
		if ( is_dir("$dir/$item") ) {
			array_push($array, "$dir/$item");
			$array = array_merge($array, subdirs("$dir/$item"));
		};
	};
	return $array;
};

function xpath_quote(string $value):string{
    if(false===strpos($value,'"')){
        return '"'.$value.'"';
    }
    if(false===strpos($value,'\'')){
        return '\''.$value.'\'';
    }
    // if the value contains both single and double quotes, construct an
    // expression that concatenates all non-double-quote substrings with
    // the quotes, e.g.:
    //
    //    concat("'foo'", '"', "bar")
    $sb='concat(';
    $substrings=explode('"',$value);
    for($i=0;$i<count($substrings);++$i){
        $needComma=($i>0);
        if($substrings[$i]!==''){
            if($i>0){
                $sb.=', ';
            }
            $sb.='"'.$substrings[$i].'"';
            $needComma=true;
        }
        if($i < (count($substrings) -1)){
            if($needComma){
                $sb.=', ';
            }
            $sb.="'\"'";
        }
    }
    $sb.=')';
    return $sb;
}

?>