var newcql;
var newparse;
var pretoks = []; var error;
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

	// Unhide the visualization
	document.getElementById(divid).style.display = 'block';

	// Hide the qb
	document.getElementById('qbframe').style.display = 'none';
	
};

function i18n ( text ) {
	var trans;
	if ( typeof(jstrans) == "undefined" ) trans = text; 
	else trans = jstrans[text];
	if ( typeof(trans) == "undefined" ) trans = text; 
	return trans;
};

function cqlparse(cql, divid) {
	
	var div = document.getElementById(divid);
	if ( typeof(div) == "undefined" ) return;

	cql = cql.replace(/ within text/, '');

	// Some default texts that we want to have translated
	cqltit = i18n('CQL Query Visualization');
	anytok = i18n('any token');
	
	if ( divid != 'cqltoks' ) {
		div.innerHTML = '<span style="margin-top: -2px; margin-right: -5px; float: right;" onClick="this.parentNode.style.display = \'none\';">x</span><p class=\"caption\">'+cqltit+'</p>';
	} else {
		div.innerHTML = '';
	};
	
	var tmp = cql.split("::"); 
	var parts = tmp[0]; var global = tmp[1];
	var warnings = '';
	
	// Parse the main query
	var i = 0;	

	var partpat = /^\s*(@|[a-z0-9]+:)?\[([^\]]*)\]([*?+]|{\d+,\d+})?|\s*<([^>]*)>/;
	while ( parts.match(partpat) ) {
		if ( parts.match(/^\s*<(\/?)([^>]*)>/) ) {
			var tmp = /^\s*<(\/?)([^>]*)>/.exec(parts); var reg = tmp[2];
			var begend = 'start'; if ( tmp[1] == '/' ) begend = 'end';
			
			if ( reg.match(/(.*)_(.*)(!?[=<>]*)(.*)/) ) {
				var tmp = /(.*)_([^=]*) *(!?[=<>]*) *(.*?)$/.exec(reg); 
				var reg = tmp[2]; // set the main region
				reg = tmp[1] + '<p>'+tmp[2]+' '+tmp[3]+' '+tmp[4]; // Add the region restriction 
			};
			
			var tokdiv = document.createElement("div");
			tokdiv.className = 'tokdiv';
			tokdiv.innerHTML += '<p class="caption" style="margin-top: -6px;">'+i18n('region '+begend)+'</p><p>'+i18n('region')+': ' + reg + '</p>';
			div.appendChild(tokdiv);
			tokdiv.style.backgroundColor = '#ffffee';
			
		} else if ( parts.match(/^\s*(@|[a-z0-9]+:)?\[([^\]]*)\]([*?+]|{\d+,\d+})?/) ) {
			i++; // This is a token
			var tokparts = /^\s*(@|[a-z0-9]+:)?\[([^\]]*)\]([*?+]|{\d+,\d+})?/.exec(parts); 
			var tok = tokparts[2];
			var tokdiv = document.createElement("div");
			tokdiv.className = 'tokdiv';
			tokdiv.title = '['+tok+']';
			var rlist = tok.split ( ' & ' );
				
			// Add the caption
			var morec = ''; // caption additions
			if ( tokparts[1] != undefined ) {
				if( tokparts[1] == '@' ) { morec += ' (target)'; } else if (tokparts[1]!= '' )  { morec += ' ('+tokparts[1].replace(/:$/, '')+')'; } 
			};
			if ( tokparts[3] != undefined && tokparts[3] != ''  ) { 
				var reptxt = '';
				if ( tokparts[3] == '?' ) reptxt = i18n('optional'); 
				if ( tokparts[3] == '*' ) reptxt = i18n('optional (multiple)'); 
				if ( tokparts[3] == '+' ) reptxt = i18n('1 or more'); 
				morec += ' - ' + reptxt; 
			}; 
			tokdiv.innerHTML += '<p class="caption" style="margin-top: -6px;">' + i +  morec + '</p>' ;

			if ( tok == "" ) {
				var para = document.createElement("p");
				para.innerHTML = '<i>' + anytok + '<i>';				
				tokdiv.appendChild(para);
			} else {
				for ( i=0; i<rlist.length; i++ ) {	
					if ( rlist[i] == '' ) continue; 
					var tmp = /^(.*?) *(!?[=>]+) *(.*)$/.exec(rlist[i]); 
					var left = tmp[1].trim(); var eq = tmp[2]; var right = tmp[3];
					if ( typeof(pattname) != "undefined" ) leftname = pattname[left]; 
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
			};
			div.appendChild(tokdiv);
		};
		parts = parts.replace(partpat, '');
	};
	if ( !parts.match(/^\s*$/) ) {
		// We are left with unparsable material
		warnings += '<li>Unparsable segment: <b>' + parts.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</b></li>';
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
			var tmp = /^(.*?) *(!?[<>=]+) * (.*)$/.exec(rlist[i]); 
			var leftx = tmp[1].trim(); 
			leftx = leftx.replace(/int\((.*)\)/, "$1"); 
			var eq = tmp[2]; var right = tmp[3];
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

function showqb( useid = '' ) {

	if ( useid != '' ) cqpid = useid;
	if ( cqpid == '' ) cqpid = defid;
	
	// parse the query

	// Unhide the qbframe
	document.getElementById('qbframe').style.display = 'block';

	// Hide the visualization
	document.getElementById('cqlview').style.display = 'none';
	

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
			var matchtype = '';
			if ( document.querySelector('[name="matches['+parse[2]+']"]') ) { matchtype = document.querySelector('[name="matches['+parse[2]+']"]').value; };
			if ( matchtype == 'contains' ) {
				val = '.*' + val + '.*';
			} else if ( matchtype == 'startswith' ) {
				val = val + '.*';
			} else if ( matchtype == 'endsin' ) {
				val = val + '.*';
			};
        	var tmp = /^(.*):(.*)/.exec(parse[2]);
        	if ( tmp != null ) {
        		if ( tmp[2] == "start" ) {
		        	glq += glsep + 'int(match.' + tmp[1] + ') >= ' + val + '';
        		} else if ( tmp[2] == "end" ) {
		        	glq += glsep + 'int(match.' + tmp[1] + ') <= ' + val + '';
        		} else {
        			error = 'Unknown construction: ' + parse[2];
        		};
        	} else {
	        	glq += glsep + 'match.' + parse[2] + ' = "' + val + '"';
	        };
        	glsep = ' & ';
        };
    }	
    newcql = document.getElementById('toklist').value;
    if ( tokq != '' || newcql == '' ) newcql += '[' + tokq + ']';
    if ( glq != '' ) newcql += ' :: ' + glq;
	cqpfld.value = newcql;
	
	// If the CQL field is hidden, auto submit
	if ( typeof(direct) != "undefined" ) {
		cqpfld.form.submit();
	} else {
		document.getElementById('qbframe').style.display = "none";
	};
		
	return false; // Always fail - we do not want to actually execute this form
	
};