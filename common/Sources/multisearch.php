<?php

	// Deal with stored queries, and multiple queries
	// and visualize them on the map, in the visualization, or in a document
	// (c) Maarten Janssen, 2018

	if ( $act == "store" ) {

		// Store a CQL query
		
		if ( !$_POST ) $_POST = $_GET;
		else $tostored = 1;
		
		if ( !$_SESSION['myqueries'] ) $_SESSION['myqueries'] = array();
		$sq['display'] = $_POST['cqltit'];
		$sq['name'] = $_POST['name'];
		$sq['cql'] = $_POST['cql'];
		
		$_SESSION['myqueries'][urlencode($_GET['cql'])] = $sq;

		if ( $tostored ) print "<p>CQL query stored - reloading to stored queries
			<script language=Javascript>top.location='index.php?action=$action&act=stored';</script>";
		else print "<p>CQL query stored - reloading to search results
			<script language=Javascript>top.location='index.php?action=cqp&cql=".urlencode($_POST['cql'])."';</script>";
		exit;		

	} else if ( $act == "storedit" ) {

		require_once ("$ttroot/common/Sources/querybuilder.php");
	
		$sq = $_SESSION['myqueries'][urlencode($_GET['cql'])];
		$maintext .= "<h1>{%Edit stored CQL query}</h1>
			<script language=Javascript>$prescript</script>
			<form action='index.php?action=$action&act=store&cql={$_GET['cql']}' method=post>
			<table>
			<tr><th>{%Name}<td><input size=30 name=name value='{$sq['name']}'>
			<tr><th>{%Description}<td><input size=80 name=cqltit value='{$sq['display']}'>
			<tr><th>{%CQL Query}<td><input size=80 name=cql id=cqlfld value='{$sq['cql']}'> <a onClick=\"showqb('cqlfld');\" title=\"{%define a CQL query}\">{%query builder}</a>
					| <a onClick=\"showcql('cqlfld');\" title=\"{%visualize your CQL query}\">{%visualize}</a>
			</table>
			<p><input type=submit value='{%Save}'> &bull; <a href='index.php?action=$action&act=stored'>{%cancel}</a>
			</form>
			<div style='display: none;' class='helpbox' id='cqlview'></div>
			<div style='display: none;' class='helpbox' id='qbframe'><span style='margin-right: -5px; float: right;' onClick=\"this.parentNode.style.display = 'none';\">&times;</span>$querytext</div>
			<script language='Javascript' src=\"$jsurl/querybuilder.js\"></script>";
	
	} else if ( $act == "stored" ) {
	
		// Show stored CQL queries

		$maintext .= "<h1>{%Stored CQL queries}</h1>
			<form action='index.php?action=visualize&act=compare' id='visualize' name='visualize' method=post>
			<table>";
		
		$useridtxt = $shortuserid;
		if ( file_exists("Users/cql_$useridtxt.xml") ) {
			$xmlq = simplexml_load_file("Users/cql_$useridtxt.xml");
			$maintext .= "<tr><th colspan=4>{%Permanently stored queries}";
			foreach ( $xmlq->xpath("//query") as $sq ) {
				$done[$sq['cql'].""] = 1; 
				$display = $sq['name'] or $display = $sq['display'] or $display = $sq['cql'];
				if ( $sq['display'] && $sq['name'] ) $desc = "<span title='".urldecode($sq['cql'])."'>{$sq['display']}</span>"; else $desc = $sq['display'] or $desc = $cql; if ( $desc == $display ) $desc = "";
				$cqltxt = urlencode($sq['cql']);
				$maintext .= "<tr><td><input type=checkbox name='myqueries[$cqltxt]' value='1'><td>$display<td><a href='index.php?action=$action&act=storedit&cql=$cqltxt'>{%edit}</a><td><a href='index.php?action=cqp&cql=$cqltxt'>{%view}</a><td>$desc";
			};
		};
		if ( $_SESSION['myqueries'] ) {
			foreach ( $_SESSION['myqueries'] as $cql => $sq ) {
				if ( $done[$sq['cql']] ) continue;
				$display = $sq['name'] or $display = $sq['display'] or $display = $cql;
				if ( $sq['display'] && $sq['name'] ) $desc = "<span title='".urldecode($sq['cql'])."'>{$sq['display']}</span>"; else $desc = $cql; if ( $desc == $display ) $desc = "";
				$cqltxt = $cql;
				$morelines .= "<tr><td><input type=checkbox name='myqueries[$cqltxt]' value='1'><td>$display<td><a href='index.php?action=$action&act=storedit&cql=$cqltxt'>{%edit}</a><td><a href='index.php?action=cqp&cql=$cqltxt'>{%view}</a><td>$desc";
			};
			if ( $morelines ) $maintext .= "<tr><th colspan=4>{%Session-based queries}$morelines";
		};
		
		
		$maintext .= "</table><hr>
			<script language=Javascript>
				function doqueries(url) {
					document.visualize.action = url;
					document.visualize.submit();
				};
			</script>";

		$maintext .= "<a onClick=\"doqueries('index.php?action=visualize&act=compare');\">{%Compare queries}</a>";
		if ( $settings['geomap'] ) {
			if ( $subtit ) $cqptit = "&cqptit=".urlencode($subtit);
			$maintext .= " - <a onClick=\"doqueries('index.php?action=geomap');\">{%Visualize on the map}</a>";
		};

	} else {

		// Load the Query Builder
		require_once ("$ttroot/common/Sources/querybuilder.php");

		// Deal with named queries
		// From the stored user queries
		$useridtxt = $shortuserid;
		if ( file_exists("Users/cql_$useridtxt.xml") ) {
			$xmlq = simplexml_load_file("Users/cql_$useridtxt.xml");
			foreach ( $xmlq->xpath("//query") as $sq ) {
				$cql = $sq['cql']; $cqltxt = str_replace('"', "&quot;", $cql);
				$name = $sq['name'] or $name = $sq['display'] or $name = $sq['cql'];
				$cqltxt = urlencode($sq['cql']);
				$querylist .= "<tr><td><a href=\"index.php?action=$action&act=storedit&cql=$cqltxt\">{%edit}</a><td><a onClick=\"addquery('$cqltxt', '$name')\">{%use}</a><td>$name<td><span style='color: #cccccc'>$cql</span>";
			};
			if ( $settings['cqp']['queries'] ) $querylist .= "<tr><td colspan=4><hr>";
		};
		// From the settings
		foreach ( $settings['cqp']['queries'] as $sq ) {
			$cql = $sq['cql']; $cqltxt = str_replace('"', "&quot;", $cql);
			$name = $sq['name'] or $name = $sq['display'] or $name = $cql;
			$querylist .= "<tr><td><td><a onClick=\"addquery('$cqltxt', '$name')\">{%use}</a><td>$name<td><span style='color: #cccccc'>$cql</span>";
		};
		$maintext .= "	
			<script language=Javascript>
				var cqlfld;
				function addquery ( cql, name ) {
					// Find the first empty query line
					var i=0; fnd = 0;
					var namefld; 
					while ( !fnd ) {
						namefld = document.getElementById('name'+i);
						cqlfld = document.getElementById('query'+i);
						console.log(namefld);
						if ( !cqlfld ) {
							fnd = 1; // Make sure to exit if there are no more fields
						} else if ( cqlfld.value == '' ) {
							fnd = 1;
						};
						console.log(fnd);
						console.log(cqlfld);
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
			</script>";
		if ( $querylist ) {
			$querylist = "<h2>{%Stored CQL queries}</h2><table>$querylist</table>
			<script language=Javascript>
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
	
		$cqpp = explode("||", $_GET['cql']);
		$cqppt = explode("||", $_GET['cqlname']);
		$startrows = max(3, count($cqpp));

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

		for ( $i=0; $i<$startrows; $i++ ) {
			$querylines .= "<tr><td><input id='name$i' name=myqueries[$i][display] size=20 value='{$cqppt[$i]}'><td><input name=myqueries[$i][cql] size=80 id='query$i'  value='{$cqpp[$i]}'>";
		};	

		require_once("$ttroot/common/Sources/querymng.php");
		$qrlist = getqlist("cqp");
		if ( $qrlist ) {
			foreach ( $qrlist as $id => $item ) {
				$val = str_replace('"', "&quot;", $item['query']);
				$optlist .= "<option value=\"$val\">{$item['name']}</option>";
			};
			$qselect = " &bull; {%stored queries}: <select name=query onChange=\"var qname = this.options[this.selectedIndex].text; addquery(this.value, qname);\"><option value=''>[{%select}]</option>$optlist</select>";
		};
		
		$maintext .= "<h1>{%$pagetit}</h1>

			$subtit
			$explanation

			<script language=Javascript>
				$prescript
				function doqb() {
					addquery ( '', '' ) // Adding a query will set cqlfld to the first empty CQL field
					var cqlid = cqlfld.getAttribute('id');
					showqb(cqlid);
				};
			</script>
			<form action='$postaction' method=post>
			<table id='inputtable'>
			<tr><th>{%Query Name}<th>{%CQL Query}
			$querylines
			</table>
			<span onClick=\"addline('', '')\" style='font-size: 12pt; color: #666666;' title='{%add line}'>&nbsp; ⊕</span>
			<p><input type=submit value=\"{%Search}\">
					<a onClick=\"doqb();\" title=\"{%define a CQL query}\">{%query builder}</a>
					$qselect
			</form>
			<div style='display: none;' class='helpbox' id='qbframe'><span style='margin-right: -5px; float: right;' onClick=\"this.parentNode.style.display = 'none';\">&times;</span>$querytext</div>
			$querylist
			<script language='Javascript' src=\"$jsurl/querybuilder.js\"></script>
			";


	};	

?>