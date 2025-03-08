<?php

	require("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();
	$fileid = $ttxml->fileid;
	$xmlid = $ttxml->xmlid;
	$xml = $ttxml->xml;

	# Show sentence view
	$stype = $_GET['elm'] or $stype = getset("xmlfile/translation/element", "s");
	$tratt = $_GET['tr'] or $tratt = getset("xmlfile/translation/attribute", "gloss");
	

	$maintext .= "<h2>{%Translation view}</h2><h1>".$ttxml->title()."</h1>";
	$maintext .= $ttxml->tableheader();
	$maintext .= $ttxml->topswitch();
	$tmp = $ttxml->viewopts();

	$editxml = $ttxml->asXML();

	$maintext .= "<table>
		<tr><th>{%Original}</th><th>{%Translation}</th></tr>
		<tr><td id=mtxt valign=top>$editxml</th><td id=trans valign=top></th></tr>
		</table>
		
		<style>
			#trans s { text-decoration: none; };
		</style>
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
			
			function sEvent() {
				setHl(this, hlcolor);	
			};
			function sOut(s) {
				setHl(this, '');	
			};
			function sClick(s) {
				var sid = this.getAttribute('id');
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
				trs.style['background-color'] = hlcolor;
				trs.style.backgroundColor= hlcolor; 	
			};
		</script><hr>".$ttxml->viewswitch();
		
		
?>