<?php

	require("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();
	$fileid = $ttxml->fileid;
	$xmlid = $ttxml->xmlid;
	$xml = $ttxml->xml;

	# Show sentence view
	$transs = array();
	foreach ( getset("xmlfile/sattributes") as $lvl => $val ) {
		foreach ( $val as $key => $val2 ) {
			if ( !is_array($val2) ) continue;
			if ( $val2['type'] == "trans" ) {
				$transs[$lvl.":".$key] = $val2['display'];
			}
		};
	};
	$transdef = $_GET['trans'];
	if ( !$transdef || !$transs[$transdef] ) $transdef = array_keys($transs)[0];
	if ( !$transdef && getset("xmlfile/sattributes/s/gloss") ) $transdef = "s:gloss";
	if ( !$transdef ) fatal("No translation level defined");
	list ( $stype, $tratt ) = explode(":", $transdef);

	if ( count($transs) > 1 ) {
		$transopts = "";
		foreach ( $transs as $key => $val ) {
			$transopts .= " - <a href='index.php?action=$action&cid=$fileid&trans=$key'>$val</a>";
		};
	};

	$transname = getset("xmlfile/sattributes/$stype/$tratt/display", "Translation");

	$maintext .= "<h2>{%Translation view}</h2><h1>".$ttxml->title()."</h1>";
	$maintext .= $ttxml->tableheader();
	$maintext .= $ttxml->topswitch();
	$tmp = $ttxml->viewopts();

	$editxml = $ttxml->mtxt();

	$maintext .= "<table>
		<tr><th>{%Original}</th><th>{%$transname}$transopts</th></tr>
		<tr><td id=mtxt valign=top>$editxml</th><td id=trans valign=top></th></tr>
		</table>
		
		<style>
			#trans s { text-decoration: none; };
		</style>
		<script src='$jsurl/tokview.js'></script>
		<script src='$jsurl/tokedit.js'></script>
		<script>
			var selm = '$stype';
			var cid = '$fileid';
			var tratt = '$tratt';
			var hlcolor = '#ffffaa';
			var username = '$username';
			var ss = document.getElementById('mtxt').getElementsByTagName(selm);
			for ( var a = 0; a<ss.length; a++ ) {
				var s = ss[a];
				s.onmouseover = sEvent;
				s.onmouseout = sOut;
				s.onclick = sClick;
			};
			var org = document.getElementById('mtxt').innerHTML;
			var trdiv = document.getElementById('trans');
			trdiv.innerHTML = org;
			var ss = trdiv.getElementsByTagName(selm);
			for ( var a = 0; a<ss.length; a++ ) {
				var s = ss[a];
				var sid = s.getAttribute('id');
				s.setAttribute('id', 'trans-' + sid);
				var gloss = s.getAttribute(tratt);
				s.innerHTML = gloss;
				s.onmouseover = sEvent;
				s.onmouseout = sOut;
				s.onclick = sClick;
			};
			var imgs = trdiv.getElementsByTagName('img');
			for ( var a = 0; a<imgs.length; a++ ) {
				var img = imgs[a];
				img.style.display = 'none';
			};
			
			function sEvent() {
				setHl(this, hlcolor);	
			};
			function sOut(s) {
				setHl(this, '');	
			};
			function sClick(s) {
				var sid = this.getAttribute('id');
				if ( sid.substr(0,6) == 'trans-' ) {
					sid = sid.substr(6);
				};
				if ( username ) {
					window.open('index.php?action=sentedit&elm='+selm+'&cid='+cid+'&sid='+sid, 'edit');
				};	
			};
			function setHl(elm, hlcolor) {
				elm.style['background-color'] = hlcolor;
				elm.style.backgroundColor= hlcolor; 	
				var sid = elm.getAttribute('id');
				var trid = '';
				if ( sid.substr(0,6) == 'trans-' ) {
					trid = sid.substr(6);
				} else {
					trid = 'trans-'+sid;
				};
				var trs = document.getElementById(trid);
				if ( trs ) {
					trs.style['background-color'] = hlcolor;
					trs.style.backgroundColor= hlcolor; 	
				};
			};
		</script><hr>".$ttxml->viewswitch();
		
		
?>