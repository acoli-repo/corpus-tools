var newcql;
var newparse;
var pretoks = []; var error;
var cqpid = ''; var defid = 'cqlfld';
var warnings = '';

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
        if ( flds[i].nodeName == "INPUT" ) {
			var matchtype = document.querySelector('[name="matches['+parse[2]+']"]').value;
			if ( matchtype == 'contains' ) {
				val = '.*' + val + '.*';
			} else if ( matchtype == 'startswith' ) {
				val = val + '.*';
			} else if ( matchtype == 'endsin' ) {
				val = '.*' + val;
			};
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
	
	var totext = []; totext['|'] = 'or'; totext['&'] = 'and';
	if ( typeof(totext[text]) != "undefined" ) text = totext[text]; 

	if ( typeof(jstrans) == "undefined" ) trans = text; 
	else trans = jstrans[text];
	if ( typeof(trans) == "undefined" ) trans = text; 
	return trans;
};

function cqlparse(cql, divid) {
	
	var div = document.getElementById(divid);
	if ( typeof(div) == "undefined" ) return;

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

	warnings = '';
	
	// Run the parser (PEGJS)
	var parser = PARSER; var parsed;
 	try {
 		parsed = parser.parse(cql);
 	} catch (err) {
 		parsed = { 'items': [] };
 		warnings += '<li>' + err;
 	};
	
	var globaltxt = i18n('globals');
	if ( cql.match(/^<text> \[\]/ ) ) globaltxt = i18n('Document Search'); else {
		var listdiv = showtokenlist(parsed.items);
			listdiv.style['display'] = 'inline-block';
		div.appendChild(listdiv);
	};
	
	
	if ( parsed.globals != null && parsed.globals != '' ) {
		var tokdiv = document.createElement("div");
		tokdiv.className = 'globdiv';
		tokdiv.innerHTML += '<p class="caption" style="margin-top: -6px;">'+ globaltxt +'</p><p>' + showtokenexpression(parsed.globals) + '</p>';
		div.appendChild(tokdiv);
	} else if ( cql == '<text> []' ) {
		var tokdiv = document.createElement("div");
		tokdiv.className = 'globdiv';
		tokdiv.innerHTML += '<p class="caption" style="margin-top: -6px;">'+ globaltxt +'</p><p>' + i18n('List all documents') + '</p>';
		div.appendChild(tokdiv);
	};
		
	if ( warnings != '' ) {
		div.innerHTML += '<div style="font-size: smaller;">Errors: <ul>' + warnings + '</ul></div>';
	};	
		
};

function showtokenlist ( list ) {

	var div = document.createElement("div");

	// TODO: how to number tokens within a group?
	var toknr = 0;	

	for ( var i=0; i < list.length; i++ ) {
		var item = list[i];
		
		if ( item.type == 'group' ) {
			var tokdiv = document.createElement("div");
			tokdiv.className = 'tokdiv';
			var listdiv = document.createElement("div");
			for ( var i=0; i<item.items.length; i++ ) {
				var li = item.items[i];
				var newblock = showtokenlist(li.expr);
				if ( li.join ) {
					var tmp = document.createElement("div");
					tmp.className = 'blocksep';
					tmp.innerHTML = i18n(li.join);
					listdiv.appendChild(tmp);
				};
				listdiv.appendChild(newblock);
			};
			tokdiv.innerHTML += '<p class="caption" style="margin-top: -6px;">'+ i18n('group') +'</p><p>' + listdiv.innerHTML + '</p>';
			div.appendChild(tokdiv);
		} else if ( item.type == 'token' ) {
			toknr++;
			var tokdiv = document.createElement("div");
			tokdiv.className = 'tokdiv';
			var moretxt = '';
			if ( item.name != null ) moretxt += " " + i18n('name') + ': ' + item.name + "";
			if ( item.multiplier != null ) moretxt += " (" + multitxt(item.multiplier) + ")";
			var tokendef = showtokenexpression(item.rule);
			if ( tokendef == '' ) tokendef = '<i>' + i18n('any token') + '</i>';
			tokdiv.innerHTML += '<p class="caption" style="margin-top: -6px;">'+ toknr + moretxt +'</p><p>' + tokendef + '</p>';
			div.appendChild(tokdiv);
		} else if ( item.type == 'region' ) {
			var tokdiv = document.createElement("div");
			tokdiv.className = 'tokdiv';
			var moretxt = '';
			if ( item.multiplier == '' ) moretxt += " (" + multitxt(item.multiplier) + ")";
			var regionname = item.name;
			if ( item.rule ) {
				left = patt2name(item.rule[0], regionname);
				right = patt2name(item.rule[2]);
				var tokendef = left + " " + item.rule[1] + " " + right; 
				tokdiv.innerHTML += '<p class="caption" style="margin-top: -6px;">region : ' + regionname + '</p><p>' + tokendef + '</p>';
			} else {
				tokdiv.innerHTML += '<p class="caption" style="margin-top: -6px;">region : ' + regionname + '</p>';
			};
			div.appendChild(tokdiv);
			tokdiv.style.backgroundColor = '#ffffee';
		};
		
	};
	
	return div;

};

function multitxt ( ex ) {
	var txt = '';
	if ( ex == '+' ) {
		txt = '1 ' + i18n('or more');	
	} else if ( ex == '*' ) {
		txt = '0 ' + i18n('or more');	
	} else if ( ex == '?' ) {
		txt = i18n('optional');	
	} else if ( ex.match(/{\d+,\d+}/ ) ) {
		txt = ex; // between a and b	
	} else {
		txt = ex;
	};
	
	return txt;
};

function showtokenexpression ( list ) {
	var result = ''; var sep = '';
	
	for ( var i=0; i<list.length; i++ ) {
		var left = ""; var right = "";
		var expr = list[i].expr;
		if ( expr.group ) {
			var join = ''; if ( list[i].join != null ) join = ' <span class="andor">' + i18n(list[i].join) + '</span> ';
			result += sep + join + '<p style="inline-block; border: 1px solid #aaaaaa; padding: 3px; ">' + showtokenexpression(expr.group) + '</p>'; sep = '<br>';
		} else {
			left = patt2name(expr[0]);
			right = patt2name(expr[2]);
			var join = ''; if ( list[i].join != null ) join = ' <span class="andor">' + i18n(list[i].join) + '</span> ';
			result += sep + join + left + " " + expr[1] + " " + right; sep = '<br>';
		};
	};

	return result;

};

function patt2name (it, region='') {
	var name = '';
	if ( it.patt ) {
		if ( typeof(pattname) == "undefined" ) return it.patt; 
		var patt = it.patt;
		if ( region ) patt = region + '_' + patt;
		if ( pattname[patt].display ) name =  pattname[patt].display;
		else name = pattname[patt]; 
		if ( typeof(name) == "undefined" ) {
			if ( it.patt == "word" ) {
				name = '<i>' + it.patt + '</i>';
				warnings += '<li>Non-recommendable pattribute : <b>' + it.patt + '</b>';
			} else {
				name = '<i class=wrong>' + it.patt + '</i>';
				warnings += '<li>Undefined pattribute : <b>' + it.patt + '</b>';
			};
		};
	} else if ( it.satt ) {
		if ( typeof(pattname) == "undefined"  ) return it.satt.patt; 
		patt = it.satt.patt;
		if ( pattname[patt].display ) name =  pattname[patt].display;
		else name = pattname[it.satt.patt];
		if ( typeof(name) == "undefined" ) {
			name = '<i class=wrong>' + it.satt.patt + '</i>';
			warnings += '<li>Undefined sattribute : <b>' + it.satt.patt + '</b>';
		};
	} else if ( it.number ) {
		name = '<b>' + it.number + '</b>';
	} else if ( typeof(it.re) != 'undefined' ) {
		console.log(it.re);
		if ( it.re == ''  ) {
			name = '(' + i18n('empty') + ')';
		} else if ( it.re.match(/^([a-zA-Z0-9]+)\.\*$/) ) {
	        var tmp = /^([a-zA-Z0-9]+)\.\*$/.exec(it.re);                     
			name = '[' + i18n('starts with') + '] ' + '<i>' + tmp[1] + '</i>';
		} else if ( it.re.match(/^\.\*([a-zA-Z0-9]+)$/) ) {
	        var tmp = /^\.\*([a-zA-Z0-9]+)$/.exec(it.re);                     
			name = '[' + i18n('ends in') + '] ' + '<i>' + tmp[1] + '</i>';
		} else if ( it.re.match(/^\.\*([a-zA-Z0-9]+)\.\*$/) ) {
	        var tmp = /^\.\*([a-zA-Z0-9]+)\.\*$/.exec(it.re);                     
			name = '[' + i18n('contains') + '] ' + '<i>' + tmp[1] + '</i>';
		} else name = '<i>' + it.re + '</i>';
	};
	return name;
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

function updatequery(nodirect = false) {

	if ( cqpid == '' ) cqpid = defid;
	var docquery = false; // This is a document-only query
	
	// Determine which field to update
	var cqpfld = document.getElementById(cqpid); 
	if ( typeof(cqpfld) == "undefined" ) { return false; }; // In case the field does not exist

	// Build the actual CQL query
	var toksep = ''; var glsep = ''; var tokq = ''; var glq = ''; var globaltype = '';
    var flds = document.getElementById('querybuilder').elements;   
    for(i = 0; i < flds.length; i++) {                    
        var name = flds[i].getAttribute('name');  
        var val = flds[i].value; 
        if ( val == '' ) continue;
        var parse = /(.*)\[(.*)\]/g.exec(name);                     
        if ( parse == null ) continue;
        if ( parse[1] == 'vals' ) {
	        if ( flds[i].nodeName == "INPUT" ) {
				var tmp = document.querySelector('[name="matches['+parse[2]+']"]');
				var matchtype;
				if ( tmp ) matchtype = tmp.value; else matchtype = '';
				if ( matchtype == 'contains' ) {
					val = '.*' + val + '.*';
				} else if ( matchtype == 'startswith' ) {
					val = val + '.*';
				} else if ( matchtype == 'endsin' ) {
					val = '.*' + val;
				};
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
				val = '.*' + val;
			} else if ( typeof(pattname) != 'undefined' && typeof(pattname[parse[2]]) != 'undefined' 
							&& pattname[parse[2]].values == 'multi' ) {
				val = '.*' + val + '.*'; // TODO: '(.+,|)' + val + '(,.+|)'; - and this should not visualize as [contains] (maybe)
			};
        	var tmp = /^(.*?)_(.*)$/.exec(parse[2]);
        	var gltype = tmp[1]; var glatt = tmp[2];
        	if ( globaltype == '' ) globaltype = gltype; else if ( globaltype != gltype ) globaltype = '---'; 
        	var tmp = /^(.*?):(.*)$/.exec(parse[2]);
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
    
    var wif = document.getElementById('within');
    
    // Make it a text-based search if there are only token restrictions
    if ( newcql == '' && tokq == '' && ( wif == null || typeof(wif) == 'undefined' ) ) {
		if ( globaltype == 'text' || globaltype == '' ) {
			newcql = '<text> []';
			docquery = true;
		} else {
			if ( globaltype != '---' ) {
				newcql = '<'+globaltype+'> []+'; // TODO: We should do something with region-based searches
				glq += ' within ' + globaltype;
			};
		};
    };

    if ( tokq != '' || newcql == '' ) newcql += '[' + tokq + ']';
    
    // Add the global query
    if ( glq != '' ) newcql += ' :: ' + glq;
    
    if ( wif != null ) {
    	if ( newcql == '' && tokq == '' ) { newcql = '[]'; };
    	newcql += ' within ' + wif.value;
    };
    
    // Unless there is a within, add within text
	if ( !newcql.match(/ within /) && !docquery ) newcql += ' within text';
    
	cqpfld.value = newcql;
	
	// If the CQL field is hidden, auto submit
	if ( typeof(direct) != "undefined"  ) {
		if ( !nodirect ) cqpfld.form.submit();
	} else {
		document.getElementById('qbframe').style.display = "none";
	};
		
	return false; // Always fail - we do not want to actually execute this form
	
};

// Tag builder

function tagbuilder (fld) {
	tagfld = fld;
	document.getElementById('tbframe').style.display = 'block';
	// filltag();
};
function filltag (add=0) {
	var fulltag = document.getElementById('mainpos').value;
	var maintag = fulltag; 
	console.log(maintag + ' > ' + taglen[maintag.substr(0,1)]);
	for ( var i=maintag.length-1; i<taglen[maintag.substr(0,1)]; i++ ) {
		var valfld = 'posopt-' + maintag + '-' + (i+1);
		fulltag += document.getElementById(valfld).value;
	}; 
	var newtag = fulltag.replace(/\.+$/, '') + '.*';
	if ( add ) document.getElementById(tagfld).value += '|' + newtag;
	else document.getElementById(tagfld).value = newtag;
	document.getElementById('tbframe').style.display = 'none';
};
function changepos (elm) {
	if (tagprev) document.getElementById('posopt-' + tagprev).style.display = 'none';
	tagprev = elm.value;
	document.getElementById('posopt-' + tagprev).style.display = 'block';
};