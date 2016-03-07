document.onclick = clickEvent; 
var ttsep = ''; var twsep = ''; var newrow = 0;

function addtok(tokid) { 
	document.getElementById('newann').style.display = 'block';
	highlight(tokid, '#00ffff');
	document.getElementById('newann-toklist').value += ttsep + tokid;		
	document.getElementById('newann-wrdlist').value += twsep + document.getElementById(tokid).textContent;		
	ttsep = ','; twsep = ' ';			
};

function clearnewann() {
	unhighlight();
	document.getElementById('newann-toklist').value = '';		
	document.getElementById('newann-wrdlist').value = '';		
	document.getElementById('newann').style.display = 'none'; 
	ttsep = ''; twsep = '';
};

function makenewann() {
	newrow++;
	document.getElementById('newrow-'+newrow).style.display = 'table-row';
	document.getElementById("news["+newrow+"][tokens]").value = document.getElementById('newann-toklist').value;
	document.getElementById("news["+newrow+"][text]").value = document.getElementById('newann-wrdlist').value;
	if ( newrow > 9 ) { document.getElementById('newann').innerHTML = 'Maximum number of new annotations reach. Please save and reload to continue'; };
	clearnewann();
};

function clickEvent(evt) { 
	element = evt.toElement;
	if ( !element ) { element = evt.target; };
	if ( !element ) { console.log('No element found - try Chrome or Firefox'); console.log(evt); return -1; };
	// We might be hovering over a child of our TOK
	if ( element.parentNode.tagName == "TOK" ) { element = element.parentNode; };
	if ( element.parentNode.parentNode.tagName == "TOK" ) { element = element.parentNode.parentNode; };

	if (element.tagName == "TOK" ) {
		// element.style['background-color'] == '' && - this needs to become more browser-independent
		if ( element.style.backgroundColor != 'rgb(0, 255, 255)' ) {
			addtok(element.getAttribute('id'));
		} else { console.log(element.getAttribute('id') + ' already selected: '+element.style.backgroundColor) };
	};
};
