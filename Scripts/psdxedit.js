document.onclick = clickEvent; 
var selnode; var lastxml;
function clickEvent(evt) { 
	element = evt.toElement;
	if ( !element ) { element = evt.target; };
	if ( !element ) { console.log('No element found - try Chrome or Firefox'); console.log(evt); return -1; };

    if ( element.tagName.toLowerCase() == "nodename" && element.parentNode.tagName.toLowerCase() == "etree" ) {
    	if ( selnode ) { document.getElementById(selnode).firstChild.style.backgroundColor = '#eeeeee'; };
    	selnode = element.parentNode.getAttribute('id');
    	element.style.backgroundColor = '#ffff77';
    	// Lookup the tree in the original XML
    	var seltree = treexml.getElementById(selnode);
    	// Check which buttons became available
    	// Unless directly under the root, we can move first and last children up a level (and delete a potential empty parent)
    	if ( seltree.parentNode.parentNode.tagName != "forest" ) {
	    	if ( seltree == seltree.parentNode.firstElementChild ) { document.getElementById('moveup').disabled = false; }
    		else if ( seltree == seltree.parentNode.lastElementChild ) { document.getElementById('moveup').disabled = false; } 
    		else { document.getElementById('moveup').disabled = true; };
    	};
    	// Find the next node and see if it is not a pre-terminal node we can move the current node there
    	var firstbranch = seltree.parentNode; var previous = seltree;
    	while ( firstbranch.children.length == 1 ) { previous = firstbranch; firstbranch = firstbranch.parentNode; };
		if ( firstbranch.lastElementChild == previous && nonterminal(firstbranch.nextElementSibling) ) { document.getElementById('moveright').disabled = false; }
			else if ( previous.nextElementSibling && nonterminal(previous.nextElementSibling) ) { document.getElementById('moveright').disabled = false; }
    		else { document.getElementById('moveright').disabled = true; };
		if ( firstbranch.firstElementChild == previous && nonterminal(firstbranch.previousElementSibling) ) { document.getElementById('moveleft').disabled = false; }
			else if ( previous.previousElementSibling && nonterminal(previous.previousElementSibling) ) { document.getElementById('moveleft').disabled = false; }
    		else { document.getElementById('moveleft').disabled = true; };
		if ( nonterminal(seltree) ) { hidebut('insert', false); }
    		else { hidebut('insert', true); };
    	document.getElementById('movedown').disabled = false;
		document.getElementById('changetag').style.display = 'block';
		document.getElementById('tagtxt').value = seltree.getAttribute('Label');
		
		
    };
};
function nodeClick(clicknode) {
		selnode = clicknode;
		var hlnode = document.evaluate('//*[@nodeid=\"'+selnode+'\"]', document, null, XPathResult.ANY_TYPE, null).iterateNext();
		if ( hlnode ) {
			hlnode.setAttribute('fill', '#992200');
			hlnode.setAttribute('font-weight', 'bold');
		};
    	// Lookup the tree in the original XML
    	var seltree = treexml.getElementById(selnode);
    	// Check which buttons became available
    	// Unless directly under the root, we can move first and last children up a level (and delete a potential empty parent)
    	if ( seltree.parentNode.parentNode.tagName != "forest" ) {
	    	if ( seltree == seltree.parentNode.firstElementChild ) { document.getElementById('moveup').disabled = false; }
    		else if ( seltree == seltree.parentNode.lastElementChild ) { document.getElementById('moveup').disabled = false; } 
    		else { document.getElementById('moveup').disabled = true; };
    	};
    	// Find the next node and see if it is not a pre-terminal node we can move the current node there
    	var firstbranch = seltree.parentNode; var previous = seltree;
    	while ( firstbranch.children.length == 1 ) { previous = firstbranch; firstbranch = firstbranch.parentNode; };
		if ( firstbranch.lastElementChild == previous && nonterminal(firstbranch.nextElementSibling) ) { document.getElementById('moveright').disabled = false; }
			else if ( previous.nextElementSibling && nonterminal(previous.nextElementSibling) ) { document.getElementById('moveright').disabled = false; }
    		else { document.getElementById('moveright').disabled = true; };
		if ( firstbranch.firstElementChild == previous && nonterminal(firstbranch.previousElementSibling) ) { document.getElementById('moveleft').disabled = false; }
			else if ( previous.previousElementSibling && nonterminal(previous.previousElementSibling) ) { document.getElementById('moveleft').disabled = false; }
    		else { document.getElementById('moveleft').disabled = true; };
		if ( nonterminal(seltree) ) { hidebut('insert', false); }
    		else { hidebut('insert', true); };
    	document.getElementById('movedown').disabled = false;
		document.getElementById('changetag').style.display = 'block';
		document.getElementById('tagtxt').value = seltree.getAttribute('Label');
};
function moveup() {
	lastxml = treetxt;
	var seltree = treexml.getElementById(selnode);
	var parent = seltree.parentNode;
	if ( seltree.parentNode.children.length == 1 ) {
		seltree.parentNode.parentNode.insertBefore(seltree, seltree.parentNode);
		parent.parentNode.removeChild(parent);
	} else if ( seltree == seltree.parentNode.firstElementChild ) {
		seltree.parentNode.parentNode.insertBefore(seltree, seltree.parentNode);
	} else if ( seltree == seltree.parentNode.lastElementChild ) {
		if ( seltree.parentNode.nextSibling ) {
			seltree.parentNode.parentNode.insertBefore(seltree, seltree.parentNode.nextSibling);
		} else {
			seltree.parentNode.parentNode.appendChild(seltree);
		};
	};
	update();
};
function moveleft() {
	lastxml = treetxt;
	var seltree = treexml.getElementById(selnode);
	var parent = seltree.parentNode;
	var firstbranch = seltree.parentNode; var previous = seltree;
	var target;
	while ( firstbranch.children.length == 1 ) { previous = firstbranch; firstbranch = firstbranch.parentNode; };
	if ( firstbranch.firstElementChild == previous && nonterminal(firstbranch.previousElementSibling) ) {
		target = firstbranch.previousElementSibling;
	} else if ( nonterminal(previous.previousElementSibling) ) {
		target = previous.previousElementSibling;
	};
	while ( nonterminal(target.lastElementChild) ) { target = target.lastElementChild; };
	target.appendChild(seltree);
	if ( parent.children.length == 0 ) { parent.parentNode.removeChild(parent); };
	update();
};
function moveright() {
	lastxml = treetxt;
	var seltree = treexml.getElementById(selnode);
	var parent = seltree.parentNode;
	var target;
	var firstbranch = seltree.parentNode; var previous = seltree;
	while ( firstbranch.children.length == 1 ) { previous = firstbranch; firstbranch = firstbranch.parentNode; };
	if ( firstbranch.lastElementChild == previous && nonterminal(firstbranch.nextElementSibling) ) {
		target = firstbranch.nextElementSibling;
	} else if ( previous.nextElementSibling && nonterminal(previous.nextElementSibling) ) {
		target = previous.nextElementSibling;
	};
	while ( nonterminal(target.firstElementChild) ) { target = target.firstElementChild; };
	target.insertBefore(seltree, target.firstElementChild);
	if ( parent.children.length == 0 ) { parent.parentNode.removeChild(parent); };
	update();
};
function movedown() {
	lastxml = treetxt;
	var seltree = treexml.getElementById(selnode);
	var newnode = document.createElementNS('', "eTree");
	newnode.setAttribute('Label', 'NAME_LATER');
	newnode.setAttribute('id', 'newon-'+seltree.getAttribute('id'));
	seltree.parentNode.insertBefore(newnode, seltree);
	newnode.appendChild(seltree);
	update();
};
function insertempty() {
	lastxml = treetxt;
	var seltree = treexml.getElementById(selnode);
	var newnode = document.createElementNS('', "eTree");
	newnode.setAttribute('Label', 'NAME_LATER');
	newnode.setAttribute('id', 'newon-'+seltree.getAttribute('id'));
	var newleaf = document.createElementNS('', "eLeaf");
	newleaf.setAttribute('Text', '');
	newnode.appendChild(newleaf);
	seltree.insertBefore(newnode, seltree.firstElementChild);
	update();
};
function update() {
	treetxt = new XMLSerializer().serializeToString(treexml);
	document.getElementById('tree').innerHTML = treetxt;
	document.submitxml.newxml.value = treetxt;
	maketext();
	selnode = ''; alloff();
	document.getElementById('undo').disabled = false;
	document.getElementById('save').disabled = false;
};
function tagchange() {
	lastxml = treetxt;
	var seltree = treexml.getElementById(selnode);
	seltree.setAttribute('Label', document.getElementById('tagtxt').value);
	update();
	selnode = ''; alloff();
	document.getElementById('undo').disabled = false;
	document.getElementById('save').disabled = false;
};
function undo() {
	treetxt = lastxml;
	treexml = parser.parseFromString(treetxt,'text/xml');
	document.getElementById('tree').innerHTML = treetxt;
	maketext();
	selnode = ''; alloff();
	document.getElementById('undo').disabled = true;
};
function alloff() {
	hidebut('moveup', true);
	hidebut('movedown', true);
	hidebut('moveleft', true);
	hidebut('moveright', true);
	hidebut('insert', true);
	document.getElementById('changetag').style.display = 'none';
};
function hidebut(but, value) {
	document.getElementById(but).disabled = value;
};
function savetree() {
	document.submitxml.submit();
};
function nonterminal(node) {
	// Check whether node is a terminal node
	if ( !node ) { return false; };
	if ( !node.firstElementChild.tagName ) { return false; };
	if ( node.firstElementChild.tagName.toLowerCase() != "eleaf" ) { return true; };
	return false;
};
function updatefromraw() {
	testxml = parser.parseFromString(document.submitxml.newxml.value,'text/xml');
	if ( testxml.firstElementChild.tagName.toLowerCase() != "parsererror" ) {
		treetxt = document.submitxml.newxml.value;
		treexml = parser.parseFromString(treetxt,'text/xml');
		update();
	} else {
		alert(testxml.firstElementChild.textContent );
	};
};
function togglesource() {
	document.getElementById('update').style.display = 'inline';
	document.submitxml.style.display = 'block';
	
	// Jump to the select node in the source
	if ( selnode ) {
		var seltree = treexml.getElementById(selnode);
		var searchWord = new XMLSerializer().serializeToString(seltree);
		var text = document.getElementById('newxml').value;
		var s1 = text.indexOf(searchWord.substring(0,30));
		if (s1==-1) { console.log('Not found: '+searchWord.substring(0,30)); };	
		document.getElementById('newxml').focus();		
		document.getElementById('newxml').setSelectionRange(s1,s1+searchWord.length);					
	};
};