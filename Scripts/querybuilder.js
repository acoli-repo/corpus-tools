var newcql;
var newparse;
var pretoks = [];
var cqpid = ''; var defid = 'cqlfld';

function addtoken() {

	// Build the actual CQL query
	var toksep = ''; var glsep = ''; var tokq = ''; var glq = '';
    var flds = document.getElementById('querybuilder').elements;   
    for(i = 0; i < flds.length; i++) {                    
        var name = flds[i].getAttribute('name');  
        var val = flds[i].value; 
        if ( val == '' ) continue;
        var parse = /(.*)\[(.*)\]/g.exec(name);                     
        if ( parse == null ) continue;
        var matchtype = document.querySelector('[name="matches['+parse[2]+']"]').value;
        if ( matchtype == 'contains' ) {
        	val = '.*' + val + '.*';
        } else if ( matchtype == 'startswith' ) {
        	val = val + '.*';
        } else if ( matchtype == 'endsin' ) {
        	val = val + '.*';
        };
        if ( parse[1] == 'vals' ) {
        	tokq += toksep + parse[2] + ' = "' + val + '"';
        	toksep = ' & ';
        	flds[i].value = '';
        };
    }	
    
    if ( tokq != '' ) {
    	tokq = '[' + tokq + '] ';
    };
    
	document.getElementById('toklist').value += tokq;
	
	cqlparse(document.getElementById('toklist').value, 'cqltoks');

};

function showcql() {

	if ( cqpid == '' ) cqpid = defid;
	var cql = document.getElementById(cqpid).value; 
	if ( cql == '' ) return;

	var divid = 'cqlview';

	cqlparse(cql, divid);

	document.getElementById(divid).style.display = 'block';
	
};

function cqlparse(cql, divid) {
	
	var div = document.getElementById(divid);
	if ( typeof(div) == "undefined" ) return;
	if ( typeof(cqltit) == "undefined" ) var cqltit = 'CQL Query Visualization';
	
	if ( divid != 'cqltoks' ) {
		div.innerHTML = '<span style="margin-top: -6px; float: right;" onClick="this.parentNode.style.display = \'none\';">x</span><p class=\"caption\">'+cqltit+'</p>';
	} else {
		div.innerHTML = '';
	};
	
	console.log(cql);
	console.log(divid);
	console.log(div);
	
	var tmp = cql.split("::"); 
	var parts = tmp[0]; var global = tmp[1];
	var warnings = '';
	
	// Parse the main query
	var i = 0;
	while ( parts.match(/^\s*\[([^\]]*)\]/) ) {
		if ( parts.match(/^\s*\[([^\]]*)\]/) ) {
			i++; // This is a token
			var tmp = /^\s*\[([^\]]*)\]/.exec(parts); var tok = tmp[1];
			var tokdiv = document.createElement("div");
			tokdiv.className = 'tokdiv';
			tokdiv.title = '['+tok+']';
			var rlist = tok.split ( ' & ' );
				var para = document.createElement("p");
				var node = document.createTextNode('part ' + i);
				para.appendChild(node);				
				para.className = 'caption';
				para.style['margin-top'] = '-6px';
				tokdiv.appendChild(para);
			for ( i=0; i<rlist.length; i++ ) {
				var tmp = /^(.*?) *(!?=) *(.*)$/.exec(rlist[i]); 
				var left = tmp[1].trim(); var eq = tmp[2]; var right = tmp[3];
				leftname = pattname[left]; 
				if ( typeof(leftname) == 'undefined' ) {
					if ( left == 'word' ) {
						leftname = 'word';
					} else {
						leftname = '<span class=wrong>'+left+'</span>';
						warnings += '<li>Undefined pattribute: <b>' + left + '</b></li>';
					};
				};
				var rtxt = leftname + ' ' + eq + ' ' + right;
				var para = document.createElement("p");
				para.innerHTML = rtxt;				
				tokdiv.appendChild(para);
			}; 
			div.appendChild(tokdiv);
		};
		parts = parts.replace(/^\s*\[([^\]]*)\]/, '');
	};
	
	// Parse the global restrictions
	if ( typeof(global) != 'undefined' && global != '' ) {
		var globdiv = document.createElement("div");
		globdiv.className = 'globdiv';
				var para = document.createElement("p");
				var node = document.createTextNode('global');
				para.appendChild(node);				
				para.className = 'caption';
				para.style['margin-bottom'] = '-5px';
				globdiv.appendChild(para);
		var rlist = global.split ( ' & ' );
		for ( i=0; i<rlist.length; i++ ) {
			var tmp = /^(.*) *(!?=) * (.*)$/.exec(rlist[i]); 
			var leftx = tmp[1].trim(); var eq = tmp[2]; var right = tmp[3];
			var tmp = leftx.split('.'); var leftm = tmp[0]; var left = tmp[1];
			var rtxt = pattname[left] + ' ' + eq + ' ' + right;
			var para = document.createElement("p");
			var node = document.createTextNode(rtxt);
			para.appendChild(node);				
			globdiv.appendChild(para);
		}; 
		div.appendChild(globdiv);
	};
			
	if ( warnings != '' ) {
		div.innerHTML += '<div style="font-size: smaller;">Errors: <ul>' + warnings + '</ul></div>';
	};	
		
};

function showqb(useid = '') {

	if ( useid != '' ) cqpid = useid;
	if ( cqpid == '' ) cqpid = defid;
	
	// parse the query

	// Unhide the qbframe
	document.getElementById('qbframe').style.display = 'block';

};

function updatequery() {

	if ( cqpid == '' ) cqpid = defid;
	
	// Determine which field to update
	var cqpfld = document.getElementById(cqpid); 
	if ( typeof(cqpfld) == "undefined" ) { return false; }; // In case the field does not exist

	// Build the actual CQL query
	var toksep = ''; var glsep = ''; var tokq = ''; var glq = '';
    var flds = document.getElementById('querybuilder').elements;   
    for(i = 0; i < flds.length; i++) {                    
        var name = flds[i].getAttribute('name');  
        var val = flds[i].value; 
        if ( val == '' ) continue;
        var parse = /(.*)\[(.*)\]/g.exec(name);                     
        if ( parse == null ) continue;
        if ( parse[1] == 'vals' ) {
			var matchtype = document.querySelector('[name="matches['+parse[2]+']"]').value;
			if ( matchtype == 'contains' ) {
				val = '.*' + val + '.*';
			} else if ( matchtype == 'startswith' ) {
				val = val + '.*';
			} else if ( matchtype == 'endsin' ) {
				val = val + '.*';
			};
        	tokq += toksep + parse[2] + ' = "' + val + '"';
        	toksep = ' & ';
        } else if ( parse[1] == 'atts' ) {
        	glq += glsep + 'match.' + parse[2] + ' = "' + val + '"';
        	glsep = ' & ';
        };
    }	
    newcql = document.getElementById('toklist').value;
    if ( tokq != '' || newcql == '' ) newcql += '[' + tokq + ']';
    if ( glq != '' ) newcql += ' :: ' + glq;
	cqpfld.value = newcql;
	
	// If the CQL field is hidden, auto submit
	if ( cqpfld.form.style['display'] == "none" ) {
		cqpfld.form.submit();
	} else {
		document.getElementById('qbframe').style.display = "none";
	};
		
	return false; // Always fail - we do not want to actually execute this form
	
};