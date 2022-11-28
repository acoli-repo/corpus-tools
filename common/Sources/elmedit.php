<?php
	// Script to allow editing non-tok elements
	// <pb/> <lb/> <deco/> <gap/>
	// similar to tokedit.php
	// (c) Maarten Janssen, 2015

	check_login();

	$fileid = $_POST['cid'] or $fileid = $_GET['cid'];
	$tokid = $_POST['tid'] or $tokid = $_GET['tid'];
	
	# $template = "empty";
		
	if ( $fileid ) { 
	
		require ("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();
		
		$file = file_get_contents("$xmlfolder/$fileid"); 
		$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);

		$result = $ttxml->xml->xpath("//*[@id='$tokid']"); 
		$elm = $result[0]; # print_r($token); exit;

		if ( !$elm ) fatal("No such element: $tokid");

		$etype = $elm->getName();


		$maintext .= "<h1>Edit Element</h1>
			<h2>Element ($tokid): ".$etype."</h2>
			
			<form action='index.php?action=toksave' method=post name=tagform id=tagform>
			<input type=hidden name=cid value='$fileid'>
			<input type=hidden name=tid value='$tokid'>
			<table>";


		if ( $settings['xmlfile']['sattributes'][$etype] ) {
			foreach ( $settings['xmlfile']['sattributes'][$etype] as $key => $item ) {
				if ( $key == "implicit" ) $implicit[$etype] = 1;
				if ( !is_array($item) ) continue;
				$itemtxt = $item['display'];
				$atv = $elm[$key]; 
				$maintext .= "<tr><th>$key<td>$itemtxt<td><input size=60 name=atts[$key] id='f$key' value='$atv'>";
			};
		} else {
			$implicit['lb'] = 1;
			$elmatts = Array ( 
			"pb" => Array ( "n" => "Page number", "facs" => "Facsimile image", "admin" => "Admin-only image"  ),
			"lb" => Array ( "n" => "Line number",  ),
			"deco" => Array ( "decoRef" => "decoration ID",  ),
			"gap" => Array ( "extent" => "Gap size", "reason" => "Gap reason",  ),
			);
			
			// Show all the defined attributes
			foreach ( $elmatts[$etype] as $key => $val ) {
				$atv = $elm[$key]; 
				if ( $key == "facs" && file_exists("$sharedfolder/Sources/images.php") ) {
					# Images not working properly - you cannot select from there (but hard-allow local use)
					$maintext .= "<tr><th>$key<td>$val<td><input size=40 name=atts[$key] id='f$key' value='$atv'>
						<a href='index.php?action=images&act=list' target=select>(see list)</a>";
				} else if ( $key == "admin" ) {
					if ( $atv == "1" ) $aon = "selected";
					$maintext .= "<tr><th>$key<td>$val<td><select name=atts[$key] id='f$key'><option value=''>no</option><option value='1' $aon>yes</option></select>";
				} else $maintext .= "<tr><th>$key<td>$val<td><input size=60 name=atts[$key] id='f$key' value='$atv'>";
			};
			if ( $elm['bbox'] ) $maintext .= "<input type=hidden name=atts[bbox] id='fbbox' value='{$elm['bbox']}'>";
		};

		# $txtxml = $ttxml->page(); 
		$txtxml = $ttxml->context($elm['id']); 

		$maintext .= "</table>";
		
		if ( $implicit[$etype] ) {
			$lbxml = $elm->asXML(); $linexml = htmlentities($lbxml);
			$linetxt = ""; 
			$linepos = strpos($ttxml->rawtext, $lbxml);
			$nextlb = strpos($ttxml->rawtext, "<lb", $linepos+1);
			$nextpb = strpos($ttxml->rawtext, "<pb", $linepos+1);
			$lineend = min($nextlb, $nextpb) or $lineend = $nextlb or $lineend = $nextpb;
			if ( !$lineend ) $lineend = strpos($ttxml->rawtext, "</text", $linepos+1);
			$linetxt = substr($ttxml->rawtext, $linepos, $lineend-$linepos);
			$linetxt = preg_replace("/<s [^>]+>/smi", "", $linetxt);

			$maintext .= "<hr><h3>Implicit content of empty element</h3> <div id=mtxt>$linetxt</div>";
		};

		$maintext .= "<hr>
		<input type=submit value=\"Save\">
		<button onClick=\"window.open('index.php?action=file&cid=$fileid', '_self');\">Cancel</button></form>
		$implicitcontext
		<!-- <a href='index.php?action=file&cid=$fileid'>Cancel</a> -->
		<hr><div id=mtxt>".$txtxml."</div>
		<script language=Javascript>
			var telm = document.getElementById('$tokid');
			if ( telm.innerText ) {
				// highlight
				telm.style.backgroundColor = '#ffffaa';
			} else {
				// Place a dummy element before
				 var sp1 = document.createElement('span');
				 var sp1_content = document.createTextNode('[$etype]');
				 sp1.appendChild(sp1_content);
				 sp1.style['color'] = '#0000ff';
				 sp1.style['font-size'] = '11pt';
				telm.insertBefore(sp1, telm.firstChild);
			};
		</script>";


		if ( $elm['bbox']  ) {
		
			# Establish the @facs of this bbox
 			$pb = current($elm->xpath("./preceding::pb"));
			if ( $pb ) {
			
			$imgsrc = $pb['facs'];
			if ( substr($imgsrc,0,4) != "http" ) $imgsrc = "Facsimile/$imgsrc";
			$divheight = 80;
			$maintext .= "<hr>Edit bounding box (by dragging the corners):
				<div bbox='{$elm['bbox']}' onMouseUp='posbox();' class='resize' id='elmdiv' tid='{$elm['id']}' style='width: 100%; height: {$divheight}px; background-image: url(\"$imgsrc\"); background-size: cover;'></div>
				";

			$maintext .= "<img src='$imgsrc' style='display: none;' id='facs'/>";
		
			$maintext .= "			
			<script language=Javascript src='https://cdnjs.cloudflare.com/ajax/libs/interact.js/1.10.11/interact.min.js'></script>
			<script language=Javascript>
				function showlines () {
					var bbox = linediv.getAttribute('bbox').split(' ');
					// Never scale more than 50% up
					var imgscale  = Math.min(1.5, linediv.offsetWidth/(bbox[2]-bbox[0]));

					var biw = facsimg.naturalWidth*imgscale;
					var bih = biw*(facsimg.naturalHeight/facsimg.naturalWidth);
					var bix = bbox[0]*imgscale;
					var biy = bbox[1]*imgscale;

					linediv.style.width = (bbox[2]-bbox[0])*imgscale + 'px'; // We might have made the div too wide
					linediv.style.height = (bbox[3]-bbox[1])*imgscale + 'px';
					linediv.style['background-size'] = biw+'px '+bih+'px';
					linediv.style['background-position'] = '-'+bix+'px -'+biy+'px';
					linediv.setAttribute('orgbpos', '-'+bix+'px -'+biy+'px');
				};

				var facsimg = document.getElementById('facs');
				var linediv = document.getElementById('elmdiv');
				
				facsimg.onload = function () {
					// Wait until image is loaded before resizing the background
					redraw();
					showlines();
				};
			</script>";
	
			$maintext .= "
			<script language=Javascript src='$jsurl/pagetrans.js'></script>
			<script language=Javascript>
			var orgx = linediv.getBoundingClientRect().left;
			var orgy = linediv.getBoundingClientRect().top;
			posbox();
			
			function posbox() {
				linediv.style.position = 'absolute';
				var trans = linediv.style.transform;
				var regExp = /\((.*)px,(.*)px\)/;
				var matches = regExp.exec(trans);
				var ox = 0; var oy = 0;
				if ( matches ) {
					ox = matches[1];
					// oy = matches[2];
				};
				var newx = orgx - ox;
				linediv.style.left = newx + 'px';
				var newy = orgy - oy;
				linediv.style.top = newy + 'px';
			};

			interact('.resize')
			  .resizable({
				// resize from all edges and corners
				edges: { left: true, right: true, bottom: true, top: true },

			  })
			  .on('resizemove', function (event) {
				var target = event.target,
					x = (parseFloat(target.getAttribute('data-x')) || 0),
					y = (parseFloat(target.getAttribute('data-y')) || 0);

				// update the element's style
				target.style.width  = event.rect.width + 'px';
				target.style.height = event.rect.height + 'px';

				// translate when resizing from top or left edges
				x += event.deltaRect.left;
				y += event.deltaRect.top;
	
				target.style.webkitTransform = target.style.transform =
					'translate(' + x + 'px,' + y + 'px)';
	
				var bsize = target.style['background-size'].replace(/px/g,'').split(' ');
				var imgscale = bsize[0]/document.getElementById('facs').naturalWidth;

				bpos = target.getAttribute('orgbpos').replace(/px/g,'').split(' ');
				bpos[0] = bpos[0]*1 - x*1;
				bpos[1] = bpos[1]*1 - y*1;
				target.style['margin-bottom'] = y+'px';
				target.style['margin-top'] = (0-y)+'px';
				target.style['background-position'] = bpos[0]+'px '+bpos[1]+'px';
				var nl = (0-bpos[0])/imgscale;
				var nt = (0-bpos[1])/imgscale;
				var nr = nl + target.offsetWidth/imgscale;
				var nb = nt + target.offsetHeight/imgscale;
				var newbbox = nl+' '+nt+' '+nr+' '+nb;
				document.getElementById('fbbox').value=newbbox;

				target.setAttribute('data-x', x);
				target.setAttribute('data-y', y);
				
			  });

			</script>";
			};
		};

	
	
	} else {
		fatal("No XML file selected"); 
	};
	
?>