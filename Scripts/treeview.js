var deflineheight = 100; var base = 30; 
var defdiv = 'svgdiv';
var svg, maxheight, maxwidth, toknr, toks, lvls, children, levw, menubox, hpos, maxlevel, showroot, hlbox, hpadding, vpadding;
var defspacing = 40; var defpadding = 5; var ungrouping = 50;
const svgns = 'http://www.w3.org/2000/svg';
var children = {}; var svgcontainers = {}; var hasatts = {}; var trees = {}; var levw = [];
if (  typeof(debug) == 'undefined' ) { var debug = 0; };
if (  typeof(showroot) == 'undefined' ) { var showroot = 0; };


function drawsvg(elm, divid = null, opts = {} ) {

	if ( typeof(divid) == 'undefined' || !document.getElementById(divid) ) { divid = defdiv; };
	div = document.getElementById(divid);
	if ( typeof(div) == 'undefined' ) { console.log('No such DIV: ' + divid); return -1; };

	if ( elm == null && trees[divid] ) elm = trees[divid];
	if ( elm == null ) { console.log('No tree provided'); return false; }
	trees[divid] = elm;

	divopts = trees[divid]['options'];
	if ( typeof(divopts) == 'undefined' ) divopts = {};
	for ( i in Object.keys(opts) ) { key = Object.keys(opts)[i]; divopts[key] = opts[key]; };
	if ( Object.keys(divopts).length == null && typeof(options) != 'undefined' ) { divopts = options; };
	
	treewords = trees[divid]['words']; 
	if ( typeof(treewords) == 'undefined' ) treewords = [];

	spacing = parseFloat(getvar('spacing', divid)); if ( !spacing ) spacing = defspacing;
	lineheight = parseFloat(getvar('lineheight', divid)); if ( !lineheight ) lineheight = deflineheight;
	hpadding = parseFloat(getvar('hpadding', divid)); if ( !hpadding ) hpadding = defpadding;
	vpadding = parseFloat(getvar('vpadding', divid)); if ( !vpadding ) vpadding = defpadding;

	div.setAttribute('svgdiv', 1);
	svgcontainer = svgcontainers[divid];
	var buts = {};
	if ( typeof(ctree) == 'undefined' ) ctree = divopts['ctree'];
	if ( divopts['type'] && divopts['type'] == 'constituency' ) ctree = 1;
	if ( typeof(ctree) == 'undefined' ) ctree = 0;

	if ( typeof(svgcontainer) == 'undefined' ) { 
		svgcontainer = document.createElement('div');
		svgcontainer.setAttribute('id', 'svgcontainer');
		mendiv = document.createElement('div');
		mendiv.setAttribute('id', 'treeopts');
		mtxtdiv = document.createElement('div');
		mtxtdiv.setAttribute('id', 'mtxtdiv');
		div.appendChild(mendiv);
		div.appendChild(mtxtdiv);
		div.appendChild(svgcontainer);
		treeicon = '<div style=\'font-size: 24px; text-align: right\' onClick="this.style.display=\'none\'; this.parentNode.children[1].style.display=\'block\';">≡</div>';
		treeopts = '<div class=\'helpbox\' style=\'display: none; padding-left: 5px padding-left: 20px;\'><span style="float: right" onClick="this.parentNode.style.display=\'none\'; this.parentNode.parentNode.children[0].style.display=\'block\';">x</span> \
			<h2>Tree Options</h2> \
			<p><button style=\'background-color: #ffffff;\' onClick="vtoggle(this);	return false;" name=\'boxed\' value=\'0\'>show boxes</button></p> \
			<p><button style=\'background-color: #ffffff;\' onClick="vtoggle(this);	return false;" name=\'showsent\' value=\'0\'>show sentence</button></p> \
			<p><button style=\'background-color: #ffffff;\' onClick="vtoggle(this);	return false;" name=\'wordorder\' var=\'hpos\' value=\'0\'>word order</button></p> \
			<p><button style=\'background-color: #ffffff;\' onClick="vtoggle(this);	return false;" name=\'wordsdown\' var=\'hpos\' value=\'0\'>words down</button></p> \
			<p><button style=\'background-color: #ffffff;\' onClick="vtoggle(this);	return false;" name=\'hidelabs\' value=\'0\'>hide labels</button></p> \
			<p><button style=\'background-color: #ffffff;\' onClick="vtoggle(this);	return false;" name=\'punct\' value=\'0\'>show punctuation</button></p> \
			<p><button style=\'background-color: #ffffff;\' onClick="vtoggle(this);	return false;" name=\'showroot\' value=\'0\'>show root</button></p> \
			<p><button onClick="vchange(this);" factor="0.8" name=\'spacing\'>-</button> spacing <button onClick="vchange(this);	return false;" factor="1.2" name=\'spacing\'>+</button></p> \
			<p><button onClick="vchange(this);" factor="0.8" name=\'lineheight\'>-</button> lineheight <button onClick="vchange(this);	return false;" factor="1.2" name=\'lineheight\'>+</button></p> \
			<p><button onClick="downloadSVG(\'svgtree\');	return false;" factor="1.2" name=\'lineheight\'>download SVG</button></p></div> </div>';
		mendiv.setAttribute('style', 'position: relative; float: right; z-index: 2000;');
		mendiv.innerHTML = treeicon + treeopts;		
		svgcontainers[divid] = svgcontainer;
	};
	for ( i in mendiv.getElementsByTagName('button') ) {
		but = mendiv.getElementsByTagName('button')[i];
		if ( typeof(but) != 'object') continue;
		name = but.getAttribute('name');
		buts[name] = but;
	};

	// See if we got pre-defined settings
	parseopts(divopts, div, buts);

	// Create the SVG
	svg = document.createElementNS(svgns, 'svg');
	svg.setAttribute('id', 'svgtree');
	svg.setAttribute('style', 'z-index: 2; position: absolute');
	
	while ( svgcontainer.firstChild ) { svgcontainer.removeChild(svgcontainer.firstChild); };
	svgcontainer.appendChild(svg);
	
	maxheight = 0; maxwidth = 0; maxlevel = 0;
	toks = {}; toknr = 0;
	lvls = []; lastlvl = 0;
	children = {}; levw = [];
	hasatts[divid] = {}
	hasatts[divid]['punct'] = 0; hasatts[divid]['labs'] = 0;

	hpos = getvar('hpos', divid);
	if ( !hpos ) { setvar('hpos', 'branch', divid, false); };

	rootlvl = 0;
	if ( getvar('showroot', divid) == 1 ) { 
		newtok = document.createElementNS(svgns, 'text');
		newtok.innerHTML = 'root';
		newtok.setAttribute('y', base);
		newtok.setAttribute('x', 10);
		toknr = 1;
		newtok.setAttribute('lvl', 0);
		newtok.setAttribute('text-anchor', 'left');
		newtok.setAttribute('font-size', '10pt');
		newtok.setAttribute('fill', '#666666');
		newtok.setAttribute('type', 'root');
		toks['tn-1'] = newtok;
		children['tn-1'] = ['tn-2']; 
		svg.appendChild(newtok);
		lvls[0] = ['tn-1'];
		if ( typeof(treewords) != 'undefined' && treewords[0] != 'tn-1' ) { treewords.unshift('tn-1'); };
		rootlvl = 1; 
	} else if ( typeof(treewords) != 'undefined' && treewords[0] == 'tn-1' ) { 
		treewords.shift();
	};
	
	// place text elements for all nodes in the tree - arbitrarily placed
	putchildren(elm, svg, rootlvl);
	
	// highlight any nodes if asked
	if ( typeof(jmp) != 'undefined' && toks[jmp] ) {
		toks[jmp].setAttribute('fill', '#aa2200');
		toks[jmp].setAttribute('font-weight', 'bold');
	};
	
	// do initial placement
	for ( i in lvls ) {
		hi = 100;
		lasthead = 0;
		for ( h in lvls[i] ) {
			var hid = lvls[i][h];
			var bb = toks[hid].getBBox();
			thishead = toks[hid].getAttribute('head');
			toks[hid].setAttribute('x', hi);
			hi = hi + bb['width'] + spacing;
			if ( lasthead && lasthead != thishead ) { hi = hi + ungrouping; }; // extra spacing between non-siblings
			if ( maxwidth < hi ) { 
				maxwidth = hi;
				maxlevel = i;
			};
			lasthead = thishead;
		};
		levw[i] = hi;
		lastlvl = i;
	};
	
	// do horizontal distribution
	if ( getvar('hpos', divid) == 'wordorder' && ctree == 0 && treewords.length > 0 ) { 


		// Go through word order, and place each token on the first available position
		var th = spacing;
		for ( i in treewords ) {
			t = treewords[i]; 
			if ( !toks[t] ) { continue; }; // deal with hidden punctuation
			bb = toks[t].getBBox();
			toks[t].setAttribute('x', th);
			th = th + bb['width'] + spacing;
		};
		
		
	} else if ( getvar('hpos', divid) == 'wordsdown' && ctree == 1 ) {

		rh = base + lineheight * lastlvl;
		lvls[lastlvl] = [];
		// put all terminal nodes down
		for ( tid in toks ) {	
			if ( children[tid].length == 0  ) {
				node = toks[tid];
				node.setAttribute('y', rh);
				tokid = node.getAttribute('id').replace('node-', '');
				tlvl = node.getAttribute('lvl');
				if ( tlvl != lastlvl ) {
					lvls[tlvl].splice(lvls[tlvl].indexOf(tokid), 1);				
				};
				lvls[lastlvl].push(tokid);
			};
		};
		th = spacing;
		for ( i in lvls[lastlvl] ) {
			tid = lvls[lastlvl][i];
			tok = toks[tid];
			tok.setAttribute('x', th);
			bb = tok.getBBox();
			th = th + bb['width'] + spacing;
			
			hid = tok.getAttribute('head');
			hb = toks[hid].getBBox();
			hp = bb['x'] + (bb['width']-hb['width'])/2;
			toks[hid].setAttribute('x', hp);
		};	
		for ( i = lastlvl-1; i>=0; i-- ) {
			for ( h in lvls[i] ) {
				var hid = lvls[i][h];
				c1 = toks[children[hid][0]];
				c2 = toks[children[hid].at(-1)];
				if ( !c1 || !c2 || !toks[hid] ) { continue; };
				b1 = c1.getBBox();
				b2 = c2.getBBox();
				bb = toks[hid].getBBox();
				x1 = b1['x']; x2 = b2['x'] + b2['width'];
				xm = ( x1 + x2 ) / 2; 
				tx = xm - (bb['width']/2);
				toks[hid].setAttribute('x', tx);
			};
		};
		
	} else if ( getvar('hpos', divid) == 'sentorder' ) { 

		// Go through word order, and place each token on the first available position
		var hi = spacing;
		var lh = [];
		t = toks[treewords[0]];
		if ( t ) t.setAttribute('x', hi); // place the very first token to the left
		for ( i in treewords ) {
			if ( i == 0 ) { continue; }; // deal with hidden punctuation
			t = treewords[i]; 
			if ( !toks[t] ) { continue; }; // deal with hidden punctuation
			tl = parseInt(toks[t].getAttribute('lvl'));
			th = hi + spacing;
			if ( lh[tl] && lh[tl] > th ) { th = lh[tl]; };
			toks[t].setAttribute('x', th);
			hi = th;
			bb = toks[t].getBBox();
			lh[tl] = th + bb['width'] + spacing;
		};
	
	} else { //  if ( hpos == 'branch' ) {
	
		// Repeatedly move token under their parent until stabalised (max 20 iterations in case of loops)
		hm = 1; lcnt = 0; lmax = 50;
		while ( hm ) {
			hm = 0; lcnt = lcnt + 1;
			if ( lcnt > lmax ) { console.log('emergency break - looping in branch'); break; };
			for ( i = lastlvl; i>=0; i-- ) {
				for ( h in lvls[i] ) {
					var hid = lvls[i][h];
					c1 = toks[children[hid][0]];
					c2 = toks[children[hid].at(-1)];
					if ( !c1 || !c2 || !toks[hid] ) { continue; };
					b1 = c1.getBBox();
					b2 = c2.getBBox();
					bb = toks[hid].getBBox();
					x1 = b1['x']; x2 = b2['x'] + b2['width'];
					xm = ( x1 + x2 ) / 2; 
					tx = xm - (bb['width']/2);
					if ( tx > bb['x'] ) {
						// move parent to right
						hm = 1;
						toks[hid].setAttribute('x', tx);
						unoverlap(i);
					} else if ( tx < bb['x'] ) {
						// move first child to right (rest will push along)
						hm = 1;
						dx = tx - bb['x'];
						c1.setAttribute('x', c1.getAttribute('x') - dx);
						unoverlap(parseInt(i)+1);
					};
				};
			};
		};
				
	};
	
	
	if ( typeof(window.posttree) === 'function' ) { posttree(); }; // if needed, run post scripts, pe to make things clickable again
		
	// check maxwidth and negative offsets (and repair)
	minwidth = 0;
	for ( t in toks ) {
		bb = toks[t].getBBox();
		left = bb['x'];
		right = bb['x'] + bb['width'];
		if ( right > maxwidth ) { maxwidth = right; };
		if ( left < minwidth ) { minwidth = left; };
	};				
	if ( minwidth < 0 ) {
		maxwidth = maxwidth - minwidth;
		dx = ( 0 - minwidth ) + 20;
		for ( t in toks ) {
			newx = parseInt(toks[t].getAttribute('x')) + dx;
			toks[t].setAttribute('x', newx);
		};
	};
									
	// draw the lines and place sublabels
	moreh = {}; // 
	for ( t in toks ) {
		tok = toks[t];
		sublabel = tok.getAttribute('sublabel');
		tokid = tok.getAttribute('id'); if ( !tokid ) { tokid = t; };
		sublh = 0; newtext = 0; 
		if ( sublabel ) {
			hasatts[divid]['labs'] = 1;
			if ( getvar('hidelabs', divid) != 1 ) {
			bb = tok.getBBox(); x = bb['x'] + (bb['width']/2); y = bb['y'] + bb['height'] + 12;
			newtext = document.createElementNS(svgns, 'text');
			newtext.innerHTML = sublabel;
			newtext.setAttribute('id', 'sublabel-'+tokid);
			newtext.setAttribute('x', x);
			newtext.setAttribute('y', y);
			newtext.setAttribute('text-anchor', 'middle');
			newtext.setAttribute('fill', '#ff8866');
			newtext.setAttribute('font-size', '9pt');
			svg.appendChild(newtext);
			sublh = newtext.getBBox()['height'];
			moreh[t] = sublh;
		};};
		if ( 1 == 1 ) {
			// place boxes around tokens when asked
			bb = tok.getBBox(); rb = bb;
			if ( typeof(newtext) == 'object' || typeof(newtext) == 'Object' ) {
				db = newtext.getBBox(); 
				if ( db['width'] > rb['width'] ) { rb['x'] = db['x']; rb['width'] = db['width'];  };
			};
			newrect = document.createElementNS(svgns, 'rect');
			newrect.setAttribute('x', rb['x'] - hpadding );
			newrect.setAttribute('y', rb['y']  - vpadding );
			if ( ctree == 1 && children[t].length ) { newrect.setAttribute('rx', '15'); };
			newrect.setAttribute('width',  rb['width'] + hpadding*2 );
			newrect.setAttribute('height', rb['height'] + vpadding*2 + sublh );
			newrect.setAttribute('fill', 'none');
			newrect.setAttribute('id', 'box-'+tok.getAttribute('id'));
			if ( getvar('boxed',divid) == 1 ) newrect.setAttribute('style', 'stroke: #bbbbbb; stroke-width:0.4');
			svg.appendChild(newrect);
		};
		head = tok.getAttribute('head');
		if ( head && toks[head] ) {
			htok = toks[head];
			b1 = tok.getBBox(); x1 = b1['x'] + (b1['width']/2); y1 = b1['y'] - vpadding;
			if ( moreh[head] ) headsub = moreh[head]; else headsub = 0;
			b2 = htok.getBBox(); x2 = b2['x'] + (b2['width']/2); y2 = b2['y'] + b2['height'] + vpadding + headsub;
			newline = document.createElementNS(svgns, 'line');
			newline.setAttribute('x1', x1);
			newline.setAttribute('y1', y1);
			newline.setAttribute('x2', x2);
			newline.setAttribute('y2', y2);
			newline.setAttribute('style', 'stroke: #996633;stroke-width:0.5');
			svg.appendChild(newline);
		};
		// remove and add to get the rectangle below the text
		svg.removeChild(tok);
		svg.appendChild(tok);
		if ( newtext ) {
			svg.removeChild(newtext);
			svg.appendChild(newtext);
		};
	};
	svg.setAttribute('height', maxheight);
	div.style.height = maxheight + 'px';
	svg.setAttribute('width', maxwidth + 100 );

	// show the sentence above the tree if so desired
	if ( treewords.length > 0 && document.getElementById('mtxt') == null ) {
		if ( getvar('showsent', divid) == 1 ) {
			senthtml = '';
			for ( i in treewords ) {
				tokid = treewords[i];
				tok = toks[tokid];
				if ( typeof(tok) != 'object' ) continue;
				senthtml = senthtml + '<span id="wn-'+tokid+'">' + tok.textContent + '</span> ';
			};
			mtxtdiv.innerHTML = senthtml;
			mtxtdiv.style.display = 'block';
		} else {
			mtxtdiv.innerHTML = '';
			mtxtdiv.style.display = 'none';
		};
		tmp = buts['showsent'];
		if ( tmp ) tmp.style.display = 'block'; 
	} else { 
		mtxtdiv.innerHTML = '';
		mtxtdiv.style.display = 'none';
		tmp = buts['showsent'];
		if ( tmp ) tmp.style.display = 'none'; 
	};
	
	// Hide (/show) irrelevant button
	tmp = buts['punct'];
	if ( tmp ) {
		if ( hasatts[divid]['punct'] ) { tmp.style.display = 'block'; }
		else { tmp.style.display = 'none'; };
	};
	tmp = buts['hidelabs'];
	if ( tmp ) {
		if ( hasatts[divid]['labs'] ) { tmp.style.display = 'block'; }
		else { tmp.style.display = 'none'; };
	};
	tmp = buts['wordorder'];
	if ( tmp ) {
		if ( ctree == 1 ) { tmp.style.display = 'none'; }
		else { tmp.style.display = 'block'; };
	};
	tmp = buts['showroot'];
	if ( tmp ) {
		if ( ctree == 1 ) { tmp.style.display = 'none'; }
		else { tmp.style.display = 'block'; };
	};
	tmp = buts['wordsdown'];
	if ( tmp ) {
		if ( ctree == 1 ) { tmp.style.display = 'block';  }
		else { tmp.style.display = 'none'; };
	};
	if ( typeof makeinteract === "function" ) {
		makeinteract();
	};
};

function unoverlap( lvl ) {
	toklist = lvls[lvl];
	// move overlapping tokens
	var moved = true; var it = 0;
	while ( moved ) {
		ll = 2000; lr = -1000;
		moved = false; it++;
		if ( it > 20 ) {
			// somehow moving loops, overlap rather than crash
			if ( debug ) {
				console.log('emergency break - looping in unoverlap'); 
			};
			return false;
		};
		for ( h in toklist ) {
			var hid = toklist[h];
			if ( !toks[hid] ) { continue; };
			var bb = toks[hid].getBBox();
			left = bb['x']; right = left + bb['width'] + 2*hpadding;
			minleft = lr + spacing; 
			if ( minleft > left ) {  
				toks[hid].setAttribute('x', minleft);
				centerbelow(hid);
				if ( maxwidth < lr + spacing + bb['width'] ) {
					// adjust the width of the entire SVG if needed 
					maxwidth = lr + spacing + bb['width'] + 100;
				};
				moved = true;
			};
			ll = left; lr = right;
		};
	};
};

function centerbelow ( hid ) {
	// Check that the children of a node are below the parent
	c1 = toks[children[hid][0]];
	c2 = toks[children[hid].at(-1)];
	if ( !c1 || !c2 ) { return false; };
	b1 = c1.getBBox();
	b2 = c2.getBBox();
	bb = toks[hid].getBBox();
	x1 = b1['x']; x2 = b2['x'] + b2['width'];
	if ( bb['x'] > b1['x'] ) { 
		xm = ( x1 + x2 ) / 2; 
		hm = bb['x'] + ( bb['width'] / 2 ); 
		dx = hm - xm;
		gomove = 0;
		for ( h in lvls[maxlevel] ) {
			child = lvls[maxlevel][h];
			if ( child == children[hid][0] ) { gomove = 1; };
			if ( gomove ) {
				newx = parseInt(toks[child].getAttribute('x')) + dx;
				toks[child].setAttribute('x', newx);
			};
		};
	};
	lvl = toks[hid].getAttribute('lvl');
	nextlvl = parseInt(lvl)+1;
	// if ( nextlvl && nextlvl <= maxlevel ) { unoverlap(nextlvl); };
};

function putchildren(node, svg, lvl) {
	var headid = node['id'];
	div = svg.parentNode.parentNode;
	if ( div ) divid = div.getAttribute('id'); else divid = defdiv;
	if ( !headid && lvl > 0  ) { headid = 'tn-' + toknr; };
	children[headid] = [];
	
	for ( i in node.children ) {
		toknr++; 
		child = node.children[i];
		childid = child['id'];
		if ( !childid ) { childid = 'tn-' + toknr; };
		if ( node.children[i]['sublabel'] == 'punct' || node.children[i]['ispunct'] ) { 
			hasatts[divid]['punct'] = 1;
			if ( getvar('punct', divid) == 0 ) { continue; };
		};
		if ( !lvls[lvl] ) { lvls[lvl] = []; };
		lvls[lvl].push(childid);
		children[headid].push(childid);
		newtok = document.createElementNS(svgns, 'text');
		labtxt = child['label'];
		if ( labtxt == '' || typeof(labtxt) == 'undefined' ) { labtxt = '&#8709;'; };
		newtok.innerHTML = labtxt;
		rh = base + lineheight * lvl;
		maxheight = Math.max(maxheight, rh + lineheight );
		newtok.setAttribute('y', rh);
		newtok.setAttribute('x', 10);
		newtok.setAttribute('id', 'node-'+childid);
		newtok.setAttribute('lvl', lvl);
		for ( i in child ) {
			// Copy any additional attributes from the JSON
			var att = child[i];
			if ( i == 'tokid' || i == 'label' || typeof(att) != 'string' ) { continue; };
			newtok.setAttribute(i, att);
		};
		tokid = child['tokid']; if ( !tokid ) { tokid = child['id']; };
		if ( tokid) { newtok.setAttribute('tokid', tokid);};
		if ( typeof(headid) != 'undefined' ) { newtok.setAttribute('head', headid); };
		if ( typeof(child['sublabel']) != 'undefined' ) { newtok.setAttribute('sublabel', child['sublabel']);  };
		newtok.setAttribute('text-anchor', 'left');
		newtok.setAttribute('font-size', '12pt');
		// newtok.setAttribute('type', 'tok');
		toks[childid] = newtok;
		svg.appendChild(newtok);
		putchildren(child, svg, lvl+1);
	};
};

function conllu2tree(conll, makewords = true) {
	treesc = {}; root = -1;
	lines = conll.split('\n');
	treewords = [];
	fldlist = ['ord', 'word', 'lemma', 'upos', 'xpos', 'feats', 'head', 'deprel', 'deps', 'misc'];
	
	for ( i in lines ) {
		line = lines[i];
		if ( line[0] == '#' ) {
		} else if ( line == '' ) {	
			break;
		} else {
			fields = line.split("\t");
			
			ord = fields[0]; 
			tokid = 'tok-'+ord;
			if ( typeof(treesc[tokid]) == 'undefined' ) { treesc[tokid] = {'children': {}};}
			for ( i in fldlist ) {
				fn = fldlist[i];
				if ( fn && fields[i] != '_' ) {
					treesc[tokid][fn] = fields[i];
				};
			};
			
			treewords.push(tokid);
			
			if ( treesc[tokid]['deprel'] == 'root' ) { root = tokid; rootid = tokid; };

			treesc[tokid]['label'] = treesc[tokid]['word'];
			treesc[tokid]['sublabel'] = treesc[tokid]['deprel'];
			treesc[tokid]['id'] = tokid;

			if ( treesc[tokid]['deprel'] == 'punct' ) { treesc[tokid]['ispunct'] = 1; };

			headid = 'tok-' + treesc[tokid]['head'];
			if ( typeof(treesc[headid]) == 'undefined' ) { 
				treesc[headid] = {'children': {}};
			} else if ( typeof(treesc[headid]['children'] ) == 'undefined' ) { 
				treesc[headid]['children'] = {}; 
			};
			treesc[headid]['children'][tokid] = treesc[tokid];
		};
	};
	if ( treesc[root] ) {
		treesc['root'] = { children: {} };
		treesc['root']['children'][rootid] = treesc[root];
	};
	if ( !treesc['root'] ) {
		console.log('No root in CoNLL-U');
		return false;
	};
	if ( makewords ) { treesc['root']['words'] = treewords; };
	return treesc['root'];
};

function parseteitok(sent, makewords = true) {
	treesc = {}; root = -1; rootid = null;
	treewords = [];
	if ( typeof(sent) == 'string' ) sent = new DOMParser().parseFromString(sent, "text/html");
	toks = sent.getElementsByTagName('tok'); 
	ord = 0;
	for ( i in toks ) {
		tok = toks[i];
		if ( typeof(tok) != 'object' ) continue;

		tokid = tok.getAttribute('id');
		treewords.push(tokid);
		if ( typeof(treesc[tokid]) == 'undefined' ) { treesc[tokid] = {'children': {}};}

		for ( i in tok.attributes ) {
			att = tok.attributes[i];
			if ( att.value ) {
				treesc[tokid][att.name] = att.value;
			};
		}; 

		word = tok.getAttribute('form'); if (!word) word = tok.innerText;
		head = tok.getAttribute('head'); 
		
		if ( treesc[tokid]['deprel'] == 'root' && ( !head || !rootid ) ) { 
			root = tokid; rootid = tokid; 
		};
		
		treesc[tokid]['form'] = word;
		treesc[tokid]['label'] = word;
		treesc[tokid]['sublabel'] = treesc[tokid]['deprel'];

		if ( treesc[tokid]['deprel'] == 'punct' ) { treesc[tokid]['ispunct'] = 1; };
		
		if ( typeof(treesc[head]) == 'undefined' ) { treesc[head] = {'children': {}};}
		treesc[head]['children'][tokid] = treesc[tokid];

	};
	
	if ( !rootid ) {
	}

	treesc['root'] = { children: {} };
	treesc['root']['children'][rootid] = treesc[root];
	if ( makewords ) { treesc['root']['words'] = treewords; };
	return treesc['root'];
};

function tab2tree(string) {
	lvls = {}; 
	lines = string.split('\n');
	json = '{ "options": {"type": "constituency"}, "children": [ '; last = -1;
	for ( i in lines ) {
		line = lines[i];
		lvl = 0;
		while (  line[0] == ' ' ) { line = line.substr(1); lvl = lvl + 1; };
		if ( line == '' ) continue;
		if ( lvl <= last ) {
			for ( j=last; j>=lvl; j-- ) { 
				json = json + '] } ';
				if ( j > lvl ) lvls[j] = 0;
			};
		};
		if ( lvls[lvl] ) {
			json = json + ', ';
		};
		json = json + '{ "label": "'+line+'", "children": [ ';
		lvls[lvl] = 1;
		last = lvl;
	};
	for ( j=last; j>=0; j-- ) { 
		json = json + '] } ';
		lvls[j] = 0;
	};
	json = json + '] }';
	var tree = JSON.parse(json);
	return tree;
};

function etree2tree(string, altlab = 'word,form,text,notext') {
	string = string.replace(/[\n\r]/gsmi, ''); // remove whitespaces
	string = string.replace(/>\s+</gsmi, '><'); // remove whitespaces
	doc = new DOMParser().parseFromString(string, "text/xml");
	nodes = doc.querySelectorAll("*");
	for ( i in nodes ) {
		node = nodes[i]; 
		if ( typeof(node.getAttribute) != 'function' ) { continue; };
		nn = node.nodeName;
		if ( nn == 'etree' || nn == 'eleaf' || nn == 'eTree' || nn == 'eLeaf' ) {
			tmp = node.getElementsByTagName('label')[0];
			if ( tmp ) {
				node.setAttribute('label', tmp.textContent);
				node.removeChild(tmp);
			};
		};
		if ( nn == 'w' ) {
			nodetxt = '';
			chlds = node.childNodes;
			console.log(node);
			for ( i in chlds ) {
				ch = chlds[i];
				
				if ( ch.nodeType ) console.log(ch);
				if ( ch.nodeType == 3 ) {
					nodetxt = nodetxt + ch.textContent;
					node.removeChild(ch);
					console.log('removing');
				};
			};
			if ( !node.getAttribute('label') ) node.setAttribute('label', nodetxt);
		};
		if ( nn == 'phr' && !node.getAttribute('label') ) {
			node.setAttribute('label', node.getAttribute('type'));
		};
	};
	rootnode = doc.firstChild;
	if ( rootnode.nodeName == 'forest' ) rootnode = rootnode.firstChild; 
	tree = {"options": {"type": "constituency"}};
	tree['children'] = [];
	chj = node2subtree(rootnode, altlab);
	tree['children'].push(chj);
	return tree;
};
function xml2tree(string, altlab = 'word,form,text,notext', subl = '', wrdats = '') {
	// Generic XML tree
	string = string.replace(/[\n\r]/gsmi, ''); // remove whitespaces
	string = string.replace(/>\s+</gsmi, '><'); // remove whitespaces
	doc = new DOMParser().parseFromString(string, "text/xml");
	if ( subl != '' ) {
		suba = subl.split(',');
		nodes = doc.querySelectorAll("*");
		for ( i in nodes ) {
			node = nodes[i]; 
			if ( !node.getAttribute || node.getAttribute('sublabel') )  continue;
			for ( j in suba ) {
				subo = suba[j];
				if ( node.getAttribute(subo) ) {
					node.setAttribute('sublabel', node.getAttribute(subo) );
					break;
				};
			};
		};
	};
	words = [];
	if ( wrdats != '' ) {
		wrdar = wrdats.split(',');
		nodes = doc.querySelectorAll("*");
		for ( i in nodes ) {
			node = nodes[i]; 
			if ( !node.getAttribute )  continue;
			for ( j in wrdar ) {
				wrdat = wrdar[j];
				if ( node.getAttribute(wrdat) ) {
					id = 'w-'+(words.length+1);
					node.setAttribute('id', id);
					words.push(id);
					break;
				};
			};
		};
	};
	console.log(words);
	rootnode = doc.firstChild;
	if ( rootnode.nodeName == 'alpino_ds' ) rootnode = rootnode.firstChild; 
	tree = {"options": {}};
	tree['children'] = [];
	if ( words ) tree['words'] = words;
	chj = node2subtree(rootnode, altlab);
	tree['children'].push(chj);
	return tree;
};
function psd2tree(string) {
	if ( typeof(string) != 'string' ) return false;
	string = string.replaceAll('\n', ' ');
	string = string.replace(/^.*\(0/gsmi, '(0');
	string = string.replace(/\((\d+) ([^ ()<>]+)/gsmi, '<node id="node-$1" label="$2">');
	string = string.replace(/\((\d+) /gsmi, '<node id="node-$1">');
	string = string.replace(/ ([^<>()]+)\)/gsmi, '<node label="$1"/></node>');
	string = string.replaceAll(' <node', '<node');
	string = string.replaceAll('(', '<node>');
	string = string.replaceAll(')', '</node>');
	string = string.replace(/<node>([^<>() ]+)/gsmi, '<node label="$1">');
	string = string.replaceAll('> <', '><');
	doc = new DOMParser().parseFromString(string, "text/xml");
	for ( i in doc.getElementsByTagName('node') ) { 
		node = doc.getElementsByTagName('node')[i]; nodelabel = '';
		if ( typeof(node) == 'object' ) nodelabel = node.getAttribute('label');
		if ( isPunct(nodelabel) ) {
			node.setAttribute('ispunct', '1');
			// If the punct is below a punct node, hide that as well
			if ( node.parentNode.children.length == 1 ) node.parentNode.setAttribute('ispunct', '1');
		};
	}; 
	rootnode = doc.firstChild;
	if ( rootnode.children[1] && rootnode.children[1].getAttribute('label') == 'ID' ) { rootnode = rootnode.firstChild; };
	tree = {"options": {"type": "constituency"}};
	tree['children'] = [];
	chj = node2subtree(rootnode);
	if ( chj ) { tree['children'].push(chj); };
	return tree;
};
function node2subtree(node, altlab = 'word,form,text' ) {
	altarr = altlab.split(',');
	if ( node.nodeType && node.nodeType == 3 ) { return {"label": node.textContent}; };
	if ( typeof(node.getAttribute) != 'function' ) { console.log('Incorrect child node - skipping'); return false; };
	var tree = {};
	nn = node.nodeName;
	if ( nn != 'node' ) { tree['nodeName'] = nn; };
	for ( i in node.attributes ) {
		att = node.attributes[i];
		if ( att.value ) {
			tree[att.name.toLowerCase()] = att.value;
		};
	}; 
	if ( !tree['label'] ) {
		altlabel = ''; // Use some common denominators as the label
		for ( i in altarr ) {
			altatt = altarr[i];
			if ( tree[altatt] ) {
				altlabel = tree[altatt];
				break;
			};
		};
		if ( altlabel != '' ) tree['label'] = altlabel;
		else {
			tree['label'] = tree['nodeName'];
			delete(tree['nodeName']);
		};
	};
	if ( typeof(tree['ispunct']) == 'undefined' && isPunct(tree['label']) ) tree['ispunct'] = '1';
	if ( !node.firstChild ) return tree;
	tree['children'] = [];
	for(var child=node.firstChild; child!==null; child=child.nextSibling) {
		chj = node2subtree(child, altlab);
		tree['children'].push(chj);	
	};
	return tree;
};

function getwords() {
	words = [];
	mtxt = document.getElementById('mtxt');
	if ( !mtxt ) return false;
	var toks = mtxt.getElementsByTagName("tok");
	for ( i in toks ) {
		tok = toks[i];
		if ( typeof(tok) != 'object' ) continue;
		dtoks = tok.getElementsByTagName("dtok");
		if ( dtoks.length > 0 ) {
			for ( j in dtoks ) {
				dtok = dtoks[j];
				if ( typeof(dtok) == 'object' ) words.push(dtok.getAttribute('id'));
			};
		} else {
			words.push(toks[i].getAttribute('id'));
		};
	};
	return words;
};

function setvar(vname, vvalue, divid = defdiv, redraw = true) {
	svgdiv = document.getElementById(divid);
	if ( typeof(svgdiv) == 'undefined' ) return -1;
	svgdiv.setAttribute(vname, vvalue);
	divid = svgdiv.getAttribute('id');
	// submit the 
  	var url = 'index.php?action=sessionrenew&type=setopt&var='+vname+'&val='+vvalue;
	var xhr = new XMLHttpRequest();
	xhr.open("GET", url);
	xhr.send();
	if ( redraw ) drawsvg(trees[divid], divid, {});
};
function getvar(vname, divid = defdiv) {
	svgdiv = document.getElementById(divid);
	vval = svgdiv.getAttribute(vname);
	if ( typeof(vval) == 'undefined' || vval == null ) return 0;
	return vval;
	return 0;
};

function vtoggle(button, redraw = true) {
	vname = button.getAttribute('var');
	svgdiv = findAncestor(button, 'svgdiv');
	divid = svgdiv.getAttribute('id');
	if ( vname ) {
		val = button.name;
	} else {
		vname = button.name;
		val = 1;
	};
	if ( getvar(vname, divid) == val ) {
		setvar(vname, 0, divid, redraw);
		button.style.backgroundColor = '#ffffff';
	} else {
		setvar(vname, val, divid, redraw);
		button.style.backgroundColor = '#66ff66';
	};
};
function vchange(button) {
	vname = button.name;
	svgdiv = findAncestor(button, 'svgdiv');
	divid = svgdiv.getAttribute('id');
	factor = parseFloat(button.getAttribute('factor'));
	oldval = getvar(vname, divid); if ( !oldval ) oldval = window[vname];
	newval = oldval*factor;
	setvar(vname, newval, divid);
};

function downloadSVG(id, filename = null) {
  if ( typeof(filename) == 'undefined' || filename == null ) { 
  	filename  = 'tree';
    if ( typeof(treeid) != 'undefined' ) {  filename  = filename + '-'+treeid; };
  	filename  = filename + '.svg';
  };
  document.getElementById(id).setAttribute('xmlns', 'http://www.w3.org/2000/svg')
  const svg = document.getElementById(id).outerHTML;
  const blob = new Blob([svg.toString()]);
  const element = document.createElement("a");
  element.download = filename;
  element.href = window.URL.createObjectURL(blob);
  element.click();
  element.remove();
};

function posttok(inout, evt, tokid) {
	if ( typeof(tokid) == 'undefined' ) return -1;
	if ( inout == 'out' ) {
		if ( hlbox ) {
			hlbox.setAttribute('fill', 'none')
			hlbox = null;
		};
	} else {
		tmp = document.getElementById('box-'+tokid);
		if ( tmp ) {
			hlbox = tmp;
			hlbox.setAttribute('fill', '#ffff00');
		};
	};
};

function parseopts(options, div, buts) {
	if ( typeof(options) == 'undefined' ) return -1;
	for ( key in options ) {
		butdone = 0;
		val = options[key];
		for ( i in buts ) {
			but = buts[i];
			butvar = but.getAttribute('var'); 
			if ( butvar ) {
				butval = but.getAttribute('name');
			} else {
				butvar = but.getAttribute('name');
				butval = 1;
			};
			if ( key == butvar && val == butval ) {
				vtoggle(but, false);
				butdone = 1;
			};
		};
		if ( !butdone ) {
			setvar(key, val, div.getAttribute('id'), false);
		};
	};
};

function findAncestor (el, sel) {
    while ((el = el.parentElement) && !(el.getAttribute(sel) ) );
    return el;
}

var onlyPunctuation = /^(?:[!-#%-\*,-/:;\?@\[-\]_\{\}\xA1\xA7\xAB\xB6\xB7\xBB\xBF\u037E\u0387\u055A-\u055F\u0589\u058A\u05BE\u05C0\u05C3\u05C6\u05F3\u05F4\u0609\u060A\u060C\u060D\u061B\u061E\u061F\u066A-\u066D\u06D4\u0700-\u070D\u07F7-\u07F9\u0830-\u083E\u085E\u0964\u0965\u0970\u0AF0\u0DF4\u0E4F\u0E5A\u0E5B\u0F04-\u0F12\u0F14\u0F3A-\u0F3D\u0F85\u0FD0-\u0FD4\u0FD9\u0FDA\u104A-\u104F\u10FB\u1360-\u1368\u1400\u166D\u166E\u169B\u169C\u16EB-\u16ED\u1735\u1736\u17D4-\u17D6\u17D8-\u17DA\u1800-\u180A\u1944\u1945\u1A1E\u1A1F\u1AA0-\u1AA6\u1AA8-\u1AAD\u1B5A-\u1B60\u1BFC-\u1BFF\u1C3B-\u1C3F\u1C7E\u1C7F\u1CC0-\u1CC7\u1CD3\u2010-\u2027\u2030-\u2043\u2045-\u2051\u2053-\u205E\u207D\u207E\u208D\u208E\u2308-\u230B\u2329\u232A\u2768-\u2775\u27C5\u27C6\u27E6-\u27EF\u2983-\u2998\u29D8-\u29DB\u29FC\u29FD\u2CF9-\u2CFC\u2CFE\u2CFF\u2D70\u2E00-\u2E2E\u2E30-\u2E44\u3001-\u3003\u3008-\u3011\u3014-\u301F\u3030\u303D\u30A0\u30FB\uA4FE\uA4FF\uA60D-\uA60F\uA673\uA67E\uA6F2-\uA6F7\uA874-\uA877\uA8CE\uA8CF\uA8F8-\uA8FA\uA8FC\uA92E\uA92F\uA95F\uA9C1-\uA9CD\uA9DE\uA9DF\uAA5C-\uAA5F\uAADE\uAADF\uAAF0\uAAF1\uABEB\uFD3E\uFD3F\uFE10-\uFE19\uFE30-\uFE52\uFE54-\uFE61\uFE63\uFE68\uFE6A\uFE6B\uFF01-\uFF03\uFF05-\uFF0A\uFF0C-\uFF0F\uFF1A\uFF1B\uFF1F\uFF20\uFF3B-\uFF3D\uFF3F\uFF5B\uFF5D\uFF5F-\uFF65]|\uD800[\uDD00-\uDD02\uDF9F\uDFD0]|\uD801\uDD6F|\uD802[\uDC57\uDD1F\uDD3F\uDE50-\uDE58\uDE7F\uDEF0-\uDEF6\uDF39-\uDF3F\uDF99-\uDF9C]|\uD804[\uDC47-\uDC4D\uDCBB\uDCBC\uDCBE-\uDCC1\uDD40-\uDD43\uDD74\uDD75\uDDC5-\uDDC9\uDDCD\uDDDB\uDDDD-\uDDDF\uDE38-\uDE3D\uDEA9]|\uD805[\uDC4B-\uDC4F\uDC5B\uDC5D\uDCC6\uDDC1-\uDDD7\uDE41-\uDE43\uDE60-\uDE6C\uDF3C-\uDF3E]|\uD807[\uDC41-\uDC45\uDC70\uDC71]|\uD809[\uDC70-\uDC74]|\uD81A[\uDE6E\uDE6F\uDEF5\uDF37-\uDF3B\uDF44]|\uD82F\uDC9F|\uD836[\uDE87-\uDE8B]|\uD83A[\uDD5E\uDD5F])+$/
function isPunct (string) {
	if ( string == null || typeof(string) != 'string' ) return false;
    return onlyPunctuation.test(string)
}

if ( typeof(document.onmouseover) !== 'function' ) { 
	var hllist = [];
	if ( !document.getElementById('tokinfo') ) {
		var tokinfo = document.createElement("div"); 
		tokinfo.setAttribute('id', 'tokinfo');
		document.body.appendChild(tokinfo);
		tokinfo.style.display = 'none';
		tokinfo.style['z-index'] = '4000';
		tokinfo.style['position'] = 'absolute';
		tokinfo.style['background-color'] = 'white';
	};
	document.onclick = clickEvent; 
	document.onmouseover = mouseEvent; 
	document.onmouseout = mouseOut; 
	function clickEvent(evt) { 
		element = evt.toElement;
	};
	function mouseEvent(evt) { 
		element = evt.toElement;
		var ignore = ['x', 'y', 'label', 'sublabel', 'text-anchor', 'lvl', 'font-size', 'id', 'type', 'head', 'tokid'];
		nn = element.nodeName;
		if ( nn == 'SPAN' || nn == 'TEXT' || nn == 'span' || nn == 'text' ) {
			tokid = element.getAttribute('id');
			if ( !tokid ) {
				return false;
			};
			if ( tokid.substr(0,3) == 'wn-' ) { tokid = tokid.substr(3); };
			if ( tokid.substr(0,9) == 'sublabel-' ) { tokid = tokid.substr(9); };
			highLight('box-'+tokid);
			highLight('wn-'+tokid);
			tok = document.getElementById(tokid);
			if ( tok && tok.nodeName == 'text' ) {
				rows = '';
				for ( i in tok.attributes ) {
					att = tok.attributes[i];
					if ( ignore.includes(att.name) ) continue;
					if ( att.value ) {
						rows = rows + '<tr><th>'+att.name+'</th><td>'+att.value+'</td></tr>';
					};
				}; 
				if ( rows == '' ) return false;
				html = '<table>'+rows+'</table>';
				tokinfo.innerHTML = html;
				tokinfo.style.display = 'block';
				poselement = element;
				if ( nn == 'text' ) poselement = document.getElementById('box-'+tokid);
				var foffset = offset(poselement);
				tokinfo.style.left = Math.min ( foffset.left, window.innerWidth - tokinfo.offsetWidth + window.pageXOffset ) + 'px'; 
				osh = poselement.offsetHeight;
				if ( !osh ) osh = poselement.getBBox()['height'];
				tokinfo.style.top = ( foffset.top + osh + 5 ) + 'px';
			};
		};
	};
	function highLight(elmid) { 
		elm = document.getElementById(elmid);
		if ( !elm ) return false;
		hllist.push(elm);
		if ( elm.nodeName == 'SPAN' ) elm.style.backgroundColor = '#ffff00';
		else elm.setAttribute('fill', '#ffff00');
	};
	function mouseOut(evt) { 
		element = evt.toElement;
		for ( i in hllist ) {
			elm = hllist[i];
			if ( elm.nodeName == 'SPAN' ) elm.style.backgroundColor = null;
			else elm.setAttribute('fill', '#ffffff');
		};
		hllist = [];
		tokinfo.style.display = 'none';
	};
	function offset(elem) {
		if(!elem) elem = this;

		var x = elem.offsetLeft;
		var y = elem.offsetTop;

		if ( typeof(x) == "undefined" ) {

			bbr = elem.getBoundingClientRect();
			x = bbr.left + window.pageXOffset;
			y = bbr.top + window.pageYOffset;

		} else {

			while (elem = elem.offsetParent) {
				x += elem.offsetLeft;
				y += elem.offsetTop;
			}
	
		};
	
		return { left: x, top: y };
	}    
}; // if needed, run post scripts, pe to make things clickable again
