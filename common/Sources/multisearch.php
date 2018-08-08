<?php

	// Search to highlight inside a specific XML document
	// or on the map when possible and no XML document is given
	// (c) Maarten Janssen, 2018

	// Deal with named queries
	// From the stored user queries
	$useridtxt = $shortuserid;
	if ( file_exists("Users/cql_$useridtxt.xml") ) {
		$xmlq = simplexml_load_file("Users/cql_$useridtxt.xml");
		foreach ( $xmlq->xpath("//query") as $sq ) {
			$cql = $sq['cql']; $cqltxt = str_replace('"', "&quot;", $cql);
			$name = $sq['name'] or $name = $sq['display'] or $name = $sq['cql'];
			$querylist .= "<tr><td><a onClick=\"addquery('$cqltxt', '$name')\">$name</a><td><span style='color: #cccccc'>$cql</span>";
		};
	};
	// From the settings
	foreach ( $settings['cqp']['queries'] as $sq ) {
		$cql = $sq['cql']; $cqltxt = str_replace('"', "&quot;", $cql);
		$name = $sq['name'] or $name = $sq['display'] or $name = $cql;
		$querylist .= "<tr><td><a onClick=\"addquery('$cqltxt', '$name')\">$name</a><td><span style='color: #cccccc'>$cql</span>";
	};
	if ( $querylist ) {
		$querylist = "<h2>{%Use named queries}</h2><table>$querylist</table>
		<script language=Javascript>
			function addquery (cql, name ) {
				// Find the first empty query line
				var i=0; fnd = 0;
				var namefld; var cqlfld;
				while ( !fnd ) {
					namefld = document.getElementById('name'+i);
					cqlfld = document.getElementById('query'+i);
					if ( !cqlfld ) {
						fnd = 1;
					} else if ( cqlfld.value == '' ) {
						fnd = 1;
					};
					i++;
				};
				if ( !cqlfld ) {
					var newnr = addline();
					namefld = document.getElementById('name'+newnr);
					cqlfld = document.getElementById('query'+newnr);
				};

				cqlfld.value = decodeURI(cql);
				namefld.value = name;
			};
			function addline() {
				// Add a new line
				var table = document.getElementById('inputtable');
				newnr = table.rows.length;
				
				var tr = document.createElement('tr');
				table.appendChild(tr);				
				
				var td1 = document.createElement('td');
				tr.appendChild(td1);
				var input = document.createElement('input');
				input.id = 'name' + newnr;
				input.type = 'text';
				input.name = 'myqueries[' + newnr + '][display]';
				namefld = input; 
				td1.appendChild(input);
				
				var td2 = document.createElement('td');
				tr.appendChild(td2);
				var txt = document.createTextNode('some other value');
				input = document.createElement('input');
				input.id = 'query' + newnr;
				input.type = 'text';
				input.name = 'myqueries[' + newnr + '][cql]';
				input.size = 80;
				cqlfld = input; 
				td2.appendChild(input);
				
				return newnr;
			};
		</script>";
	};

	if ( $_GET['cid'] || $_GET['id'] ) {

		$pagetit = "Document Search"; 
		require ("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();
		$subtit .= "<h2>".$ttxml->title()."</h2>"; 
		$subtit .= $ttxml->tableheader(); 
	
		$postaction = "index.php?action=text&cid=".$ttxml->fileid;
	
		$explanation = getlangfile("docsearchtext", true);
	
	
	} else if ( $settings['geomap'] && $act == "map" ) {

		$pagetit = "Map search"; 
	
		$postaction = "index.php?action=geomap";

		$explanation = getlangfile("mapsearchtext", true);
						
	} else {
		
		$pagetit = "Multiple search"; 
	
		$postaction = "index.php?action=visualize&act=compare";

		$explanation = getlangfile("multisearchtext", true);
		
	};

	for ( $i=0; $i<3; $i++ ) {
		$querylines .= "<tr><td><input id='name$i' name=myqueries[$i][display] size=20><td><input name=myqueries[$i][cql] size=80 id='query$i'>";
	};	

	$maintext .= "<h1>{%$pagetit}</h1>

		$subtit
		$explanation

		<form action='$postaction' method=post>
		<table id='inputtable'>
		<tr><th>{%Query Name}<th>{%CQL Query}
		$querylines
		</table>
		<span onClick=\"addline('', '')\" style='font-size: 12pt; color: #666666;' title='{%add line}'>&nbsp; ⊕</span>
		<p><input type=submit value=\"{%Search}\">
		</form>
		$querylist
		";
	

?>