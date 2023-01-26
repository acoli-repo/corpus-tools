var lineheight = 100; var base = 30; var rootlvl = 0;
var defdiv = 'svgdiv';
var svg, maxheight, maxwidth, toknr, toks, lvls, children, levw, menubox, svgcontainer, haspunct, hidelabs, haslabs, hpos;
var spacing = 50; var ungrouping = 50;
const svgns = 'http://www.w3.org/2000/svg';
var children = {}; var levw = [];
if (  typeof(punct) == 'undefined' ) { var punct = 0; };
if (  typeof(debug) == 'undefined' ) { var debug = 0; };

function drawsvg(elm, divid = null ) {

	if ( typeof(divid) == 'undefined' || !document.getElementById(divid) ) { divid = defdiv; };
	defdiv = divid;

	div = document.getElementById(divid);
	if ( typeof(svgcontainer) == 'undefined' ) { 
		svgcontainer = document.createElement('div');
		svgcontainer.setAttribute('id', 'svgcontainer');
		mendiv = document.createElement('div');
		mendiv.setAttribute('id', 'treeopts');
		div.appendChild(mendiv);
		div.appendChild(svgcontainer);
		treeicon = '<div style=\'font-size: 24px; text-align: right\' id=\'treemicon\' onClick="this.style.display=\'none\'; this.parentNode.children[1].style.display=\'block\';">≡</div>';
		treeopts = '<div class=\'helpbox\' style=\'display: none; padding-left: 5px padding-left: 20px;\'> <h2>Tree Options</h2> <p><button style=\'background-color: #ffffff;\' onClick="vtoggle(this);" name=\'boxed\' value=\'0\'>show boxes</button></p> <p><button style=\'background-color: #ffffff;\' id=\'labbut\' onClick="vtoggle(this);" name=\'hidelabs\' value=\'0\'>hide labels</button></p> <p><button style=\'background-color: #ffffff;\' onClick="vtoggle(this);" id=\'punctbut\' name=\'punct\' value=\'0\'>show punctuation</button></p> <p><button onClick="vchange(this);" factor="0.8" name=\'spacing\'>-</button> spacing <button onClick="vchange(this);" factor="1.2" name=\'spacing\'>+</button></p> <p><button onClick="vchange(this);" factor="0.8" name=\'lineheight\'>-</button> lineheight <button onClick="vchange(this);" factor="1.2" name=\'lineheight\'>+</button></p> </div> </div>';
		mendiv.setAttribute('style', 'position: inline; float: right; z-index: 2000;');
		mendiv.innerHTML = treeicon + treeopts;		
	};
	
	// Create the SVG
	svg = document.createElementNS(svgns, 'svg');
	svg.setAttribute('id', 'svgtree');
	svg.setAttribute('style', 'z-index: 2; position: absolute');
	
	while ( svgcontainer.firstChild ) { svgcontainer.removeChild(svgcontainer.firstChild); };
	svgcontainer.appendChild(svg);
	
	maxheight = 0; maxwidth = 0;
	toks = {}; toknr = 0;
	lvls = [];
	children = {}; levw = [];
	haspunct = 0; haslabs = 0;

	putchildren(elm, svg, rootlvl);
	
	if ( typeof(hpos) == 'undefined' ) { hpos = 'branch'; };

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
	
	if ( hpos == 'wordorder' && typeof(wordarray) != 'undefined' ) {
		var hi = 0;
		var lh = [];
		for ( i in wordarray ) {
			t = wordarray[i]; 
			if ( !toks[t] ) { continue; }; // deal with hidden punctuation
			tl = parseInt(toks[t].getAttribute('lvl'));
			th = hi + spacing;
			if ( lh[tl] && lh[tl] > th ) { th = lh[tl]; };
			toks[t].setAttribute('x', th);
			hi = th;
			bb = toks[t].getBBox();
			lh[tl] = th + bb['width'] + spacing;
		};
	} else if ( hpos == 'narrow' ) {
		for ( i in lvls ) {				
			var dx = ( levw[maxlevel] - levw[i] ) / 2 ;
			for ( h in lvls[i] ) {
				var hid = lvls[i][h];
				curx = toks[hid].getBBox()['x'];
				toks[hid].setAttribute('x', curx+dx);
			};
		};
	} else  { // if ( hpos == 'branch' )
		// redraw starting from longest line
		for ( h in lvls[maxlevel-1] ) {
			// move right if there are children on the longest line left of their parent (due to non-child items)
			hid = lvls[maxlevel-1][h];
			c1 = toks[children[hid][0]];
			c2 = toks[children[hid].at(-1)];
			if ( !c1 || !c2 ) { continue; };
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
		};
		for ( i = maxlevel-1; i>=0; i-- ) {
			// Center parent above their children starting from longest line
			for ( h in lvls[i] ) {
				var hid = lvls[i][h];
				c1 = toks[children[hid][0]];
				c2 = toks[children[hid].at(-1)];
				if ( !c1 || !c2 ) { continue; };
				b1 = c1.getBBox();
				b2 = c2.getBBox();
				bb = toks[hid].getBBox();
				x1 = b1['x']; x2 = b2['x'] + b2['width'];
				xm = ( x1 + x2 ) / 2; 
				tx = xm - (bb['width']/2);
				toks[hid].setAttribute('x', tx);
			};
			unoverlap(i);
		};
		for ( i = maxlevel; i<=lastlvl; i++ ) {
			// Redistribute children under their parent starting from longest line
			for ( h in lvls[i] ) {
				var hid = lvls[i][h];
				bb = toks[hid].getBBox();
				tm = bb['x'] + (bb['width']/2);
				var cw = 0;
				for  ( t in children[hid] ) {
					childid = children[hid][t]; 
					cw = cw + spacing + toks[childid].getBBox()['width'];
				};
				hi = tm - ((cw-spacing)/2);
				for  ( t in children[hid] ) {
					tid = children[hid][t];
					newx = hi;
					hi = hi + spacing + toks[tid].getBBox()['width'];
					toks[tid].setAttribute('x', newx);
				};
			};
			unoverlap(i);
			unoverlap(parseInt(i) + 1);
		};
		unoverlap(lastlvl);
	};
	
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
									
	// draw the lines and place deprels
	for ( t in toks ) {
		tok = toks[t];
		vpadding = 5;
		hpadding = spacing/3; // horizontal spacing in boxes depends on node spacing
		deprel = tok.getAttribute('deprel');
		sublh = 0;
		if ( deprel && !hidelabs ) {
			bb = tok.getBBox(); x = bb['x'] + (bb['width']/2); y = bb['y'] + bb['height'] + 12;
			newtext = document.createElementNS(svgns, 'text');
			newtext.innerHTML = deprel;
			newtext.setAttribute('id', 'deprel-'+tok);
			newtext.setAttribute('x', x);
			newtext.setAttribute('y', y);
			newtext.setAttribute('text-anchor', 'middle');
			newtext.setAttribute('fill', '#ff8866');
			newtext.setAttribute('font-size', '9pt');
			svg.appendChild(newtext);
			sublh = newtext.getBBox()['height'];
		};
		head = tok.getAttribute('head');
		if ( head ) {
			htok = toks[head];
			b1 = tok.getBBox(); x1 = b1['x'] + (b1['width']/2); y1 = b1['y'] - vpadding;
			if ( typeof(newtext) != 'undefined' ) {
			};
			b2 = htok.getBBox(); x2 = b2['x'] + (b2['width']/2); y2 = b2['y'] + b2['height'] + vpadding + sublh;
			newline = document.createElementNS(svgns, 'line');
			newline.setAttribute('x1', x1);
			newline.setAttribute('y1', y1);
			newline.setAttribute('x2', x2);
			newline.setAttribute('y2', y2);
			newline.setAttribute('style', 'stroke: #aa2200;stroke-width:0.5');
			svg.appendChild(newline);
		};
		if ( window['boxed'] ) {
			// place boxes around tokens when asked
			bb = tok.getBBox(); rb = bb;
			if ( typeof(newtext) != 'undefined' ) {
				db = newtext.getBBox(); if ( db['width'] > rb['width'] ) { rb['x'] = db['x']; rb['width'] = db['width'];  };
			};
			newrect = document.createElementNS(svgns, 'rect');
			newrect.setAttribute('x', rb['x'] - hpadding );
			newrect.setAttribute('y', rb['y']  - vpadding );
			newrect.setAttribute('width',  rb['width'] + hpadding*2 );
			newrect.setAttribute('height', rb['height'] + vpadding*2 + sublh );
			newrect.setAttribute('fill', 'none');
			newrect.setAttribute('style', 'stroke: #bbbbbb; stroke-width:0.4');
			svg.appendChild(newrect);
		};
	};
	svg.setAttribute('height', maxheight);
	div.style.height = maxheight + 'px';
	svg.setAttribute('width', maxwidth + 100 );
	tmp = document.getElementById('punctbut');
	if ( tmp ) {
		if ( haspunct ) { tmp.style.display = 'block'; }
		else { tmp.style.display = 'none'; };
	};
	tmp = document.getElementById('labbut');
	if ( tmp ) {
		if ( haslabs ) { labbut.style.display = 'block'; }
		else { labbut.style.display = 'none'; };
	};
};

function unoverlap( lvl ) {
	toklist = lvls[lvl];
	// move overlapping tokens
	var moved = true; var it = 0;
	while ( moved ) {
		ll = 2000; lr = -1000;
		moved = false; it++;
		if ( it > 10 ) {
			// somehow moving loops, overlap rather than crash
			if ( debug ) {
				console.log('emergency break - looping'); 
			};
			return false;
		};
		for ( h in toklist ) {
			var hid = toklist[h];
			var bb = toks[hid].getBBox();
			left = bb['x']; right = left + bb['width'];
			if ( lr + spacing > left ) {  
				toks[hid].setAttribute('x', lr + spacing);
				if ( maxwidth < lr + spacing + bb['width'] ) { 
					maxwidth = lr + spacing + bb['width'] + 100;
				};
				moved = true;
			};
			ll = left; lr = right;
		};
	};
};

function putchildren(node, svg, lvl) {
	var headid = node['id'];
	children[headid] = [];
	
	for ( childid in node.children ) {
		toknr++; 
		if ( node.children[childid]['rel'] == 'punct' ) { 
			haspunct = 1;
			if ( !punct ) { continue; };
		};
		if ( !lvls[lvl] ) { lvls[lvl] = []; };
		lvls[lvl].push(childid);
		children[headid].push(childid);
		child = node.children[childid];
		if ( !childid ) { childid = 'tok-' + toknr; };
		newtok = document.createElementNS(svgns, 'text');
		newtok.innerHTML = child['label'];
		rh = base + lineheight * lvl;
		maxheight = Math.max(maxheight, rh + lineheight );
		newtok.setAttribute('y', rh);
		newtok.setAttribute('x', 10);
		newtok.setAttribute('id', 'node-'+childid);
		newtok.setAttribute('lvl', lvl);
		newtok.setAttribute('tokid', childid);
		if ( typeof(headid) != 'undefined' ) { newtok.setAttribute('head', headid); };
		if ( typeof(child['rel']) != 'undefined' ) { newtok.setAttribute('deprel', child['rel']); haslabs = 1; };
		if ( typeof(child['sublabel']) != 'undefined' ) { newtok.setAttribute('deprel', child['sublabel']); haslabs = 1; };
		newtok.setAttribute('text-anchor', 'left');
		newtok.setAttribute('font-size', '12pt');
		newtok.setAttribute('type', 'tok');
		toks[childid] = newtok;
		svg.appendChild(newtok);
		putchildren(child, svg, lvl+1);
	};
};

function conllu2tree(conll) {
	tmp = document.getElementById(conll);
	if ( tmp ) {
		conll = tmp.innerText;
	};
	
	trees = {}; root = -1;
	lines = conll.split('\n');
	for ( i in lines ) {
		line = lines[i];
		if ( line[0] == '#' ) {
		} else if ( line == '' ) {	
			if ( trees[root] ) {
				trees['root'] = { children: {} };
				trees['root']['children'][rootid] = trees[root];
				return trees['root'];
			};
		} else {
			fields = line.split("\t");
			ord = fields[0];
			head = fields[6];
			deprel = fields[7];
			
			tokid = ord;
			if ( fields[9] != '_' ) {
				tokid = fields[9];
			}; 
			
			if ( deprel == 'root' ) { root = ord; rootid = tokid; };
			if ( typeof(trees[ord]) == 'undefined' ) { trees[ord] = {'children': {}};}
			trees[ord]['label'] = fields[1];
			trees[ord]['rel'] = deprel;
			trees[ord]['id'] = tokid;
			
			if ( typeof(trees[head]) == 'undefined' ) { trees[head] = {'children': {}};}
			trees[head]['children'][tokid] = trees[ord];
		};
	};
};

function setvar(vname, vvalue) {
	window[vname] = vvalue;
	drawsvg(tree);
};
function vtoggle(button) {
	vname = button.name;
	if ( window[vname] ) {
		setvar(vname, 0);
		button.style.backgroundColor = '#ffffff';
	} else {
		setvar(vname, 1);
		button.style.backgroundColor = '#66ff66';
	};
};
function vchange(button) {
	vname = button.name;
	factor = parseFloat(button.getAttribute('factor'));
	setvar(vname, window[vname]*factor );
};

