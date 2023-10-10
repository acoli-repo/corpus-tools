<?php

	# Get corpus/file data via AJAX
	header('Content-type: application/json');
	
	if ( $_GET['cid'] ) {
		require ("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML( $_GET['cid'], false);
	};

	if ( $_GET['cqp'] || $cqptype[$_GET['data']] ) {
		# Lookup all occurrences
		include ("$ttroot/common/Sources/cwcqp.php");
		$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
		$cqpfolder = $settings['cqp']['cqpfolder'] or $cqpfolder = "cqp";
		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");
	};
	
	if ( $_GET['data'] == "page" ) {
	
		$pagehtml = getlangfile("{$_GET['id']}");		
		print $pagehtml; exit;
	
	} else if ( $_GET['data'] == "doctable" ) {


		$cqpquery = "Matches = <text> [] :: match.{$_GET['cqp']}";
		$results = $cqp->exec($cqpquery); 
	
		$acnt = $bcnt = 0;
		foreach ( $settings['cqp']['sattributes']['text'] as $key => $item ) {
			if ( $key == $class ) continue;
			if ( strstr('_', $key ) ) { $xkey = $key; } else { $xkey = "text_$key"; };
			$val = $item['display']; # $val = $item['long'] or
			if ( $item['type'] == "group" ) {
				$fldval = $val; # substr($key,4);
				if ( $fldval != "" ) $fldtxt = " ($fldval)";
				else $fldtxt = "";
			} else if ( $item['noshow'] ) {
				# Ignore items that are not to be shown
			} else if ( $key != "id" ) {
				$moreatts .= ", match $xkey";
				$moreth .= "<th>{%$val}";
				$atttik[$bcnt] = $key; $bcnt++;
				$atttit[$acnt] = $val;
				$acnt++;
			};
		}; 
		if ( $settings['defaults']['browser']['style'] == "facs" && $settings['cqp']['pattributes']['facs'] ) {
			$withfacs = 1;
			$moreatts .= ", match facs";
		};  
		$rescnt = $cqp->exec("size Matches"); if ( $rescnt == 0 ) { exit; };
		
		$cqpquery = "tabulate Matches $start $stop match text_id$moreatts";
		$results = $cqp->exec($cqpquery);
		
		$resarr = explode ( "\n", $results ); $scnt = count($resarr);
		$maintext .= "<h2>{%Documents}</h2>";
		if ( $scnt < $cnt ) {
			$maintext .= " &bull; {%!showing} $start - $stop";
		};
		if ( $start > 0 ) $maintext .= " &bull; <a onclick=\"document.getElementById('rsstart').value ='$before'; document.resubmit.submit();\">{%previous}</a>";
		if ( $stop < $cnt ) $maintext .= " &bull; <a onclick=\"document.getElementById('rsstart').value ='$stop'; document.resubmit.submit();\">{%next}</a>";
		if ( $settings['defaults']['browser']['style'] == "facs" ) {
			$maintext .= "<hr style='color: #cccccc; background-color: #cccccc; margin-top: 6px; margin-bottom: 6px;'>
				<table id=facstable>";
		} else { 
			$maintext .= "<hr style='color: #cccccc; background-color: #cccccc; margin-top: 6px; margin-bottom: 6px;'>
				<table><tr><th>ID$moreth";
		};
		if ( !$settings['defaults']['browser']['title'] ) $settings['defaults']['browser']['title'] = "title";
		foreach ( $resarr as $line ) {
			$fatts = explode ( "\t", $line ); $fid = array_shift($fatts);
			if ( !$fid ) continue; # Skip empty rows
			if ( $admin ) {
				$fidtxt = preg_replace("/^\//", "", $fid );
			} else {
				$fidtxt = preg_replace("/.*\//", "", $fid );
			};
			# Translate the columns where needed
			foreach ( $fatts as $key => $fatt ) {
				if ( $key == $class ) continue;
				$attit = $atttik[$key];
				if ( $attit == $settings['defaults']['browser']['title'] ) {
					$titelm = $fatt;
					unset($fatts[$key]);
				};
				$tmp = $settings['cqp']['sattributes']['text'][$attit]['type'];
				if ( $settings['cqp']['sattributes']['text'][$attit]['type'] == "kselect" || $settings['cqp']['sattributes']['text'][$attit]['translate'] ) {
					if ( $settings['cqp']['sattributes']['text'][$attit]['values'] == "multi" ) {
						$fatts[$key] = ""; $sep = "";
						foreach ( explode(",", $fatt) as $fattp ) { $fatts[$key] .= "$sep{%$attit-$fattp}"; $sep = ", "; };
					} else $fatts[$key] = "{%$attit-$fatt}";
				};
			};
			$maintext .= "<tr><td><a href='index.php?action=file&cid={$fid}'>{$fidtxt}</a><td style='padding-left: 6px; padding-right: 6px; border-left: 1px solid #dddddd;'>".join ( "<td style='padding-left: 6px; padding-right: 6px; border-left: 1px solid #dddddd;'>", $fatts );
		};
		$maintext .= "</table>";
		print i18n($maintext); 
	
	} else if ( $_GET['data'] == "facs" ) {

		# Get the list of facsimile images from an XML file
		if ( !$ttxml->xml ) { print "{\"error\": \"unable to load XML file\"]}"; exit; }

		$facslist = array();
		foreach ( $ttxml->xpath("//pb[@facs]") as $pb  ) {
			$facs = $pb['facs'];
			if ( file_exists("Thumbnails/$facs") ) $ffolder = "Thumbnails"; else   $ffolder = "Facsimile";
			$facs = $ffolder."/".$facs;
			array_push($facslist, $facs);
		};
			$maintext .= "<hr><a href='index.php?action=$action'>{%back to list}</a> &bull; ".$ttxml->viewswitch();
		
		print "{\"cid\": \"{$_GET['cid']}\", \"facs\": [\"".join("\", \"", $facslist)."\"]}"; 

	} else if ( $_GET['data'] == "docinfo" ) {
		
		$popup = 1;
		if ( !$ttxml->xml ) { $output = "<i>{%Document not found}</i>"; }
		else $output = "<table width=100% style='margin-bottom: -4px;'><tr><th><b>".$ttxml->title()."</b></th><tr></table>".$ttxml->tableheader("", false);
		print i18n($output);
		exit;
		
	} else {
		print "{\"error\": \"no data selected\"]}"; 
	};
	exit;
	
?>