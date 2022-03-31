var newcql;
var newparse;
var pretoks = []; var error;
var cqpid = ''; var defid = 'cqlfld';
var warnings = '';

function addtoken() {

	// Build the actual CQL query
	var toksep = ''; var glsep = ''; var tokq = ''; var glq = ''; var globaltype = '';
    var flds = document.getElementById('querybuilder').elements;   
    for(i = 0; i < flds.length; i++) {                    
        var name = flds[i].getAttribute('name');  
        var val = flds[i].value; 
        if ( val == '' ) continue;
        var parse = /(.*?)\[(.*?)\]/g.exec(name);                     
        if ( parse == null ) continue;
        var pattn = parse[2].replace('][', '');
        var udfeat = '';
        if ( parse[1] == 'vals' ) {
			var tmp = document.querySelector('[name="matches['+parse[2]+']"]');
			var matchtype;
			if ( tmp ) matchtype = tmp.value; else matchtype = '';
	        if ( flds[i].nodeName == "INPUT" ) {
				if ( matchtype == 'contains' ) {
					val = '.*' + val + '.*';
				} else if ( matchtype == 'startswith' ) {
					val = val + '.*';
				} else if ( matchtype == 'endsin' ) {
					val = '.*' + val;
				};
	        } else if ( flds[i].nodeName == "SELECT" ) {
				if ( typeof(pattname) != 'undefined' && typeof(pattname[parse[2]]) != 'undefined' 
								&& pattname[parse[2]].values == 'multi' ) {
					if ( typeof(mvsep) == 'undefined' ) var mvsep = ',';
					val = '(.*'+mvsep+')?' + val + '('+mvsep+'.*)?';
				};
				if ( matchtype == 'udfeats' ) {
					var tmp = /(.*):(.*)/g.exec(pattn);       
					if ( tmp != null ) {
						pattn = tmp[1];
						var udfeat = tmp[2];
						if ( typeof(udsep) == 'undefined' ) var udsep = '[|]';
						val = '(.*'+udsep+')?' + udfeat + '=' + val + '('+udsep+'.*)?';
					};    
				};
	        };
        	tokq += toksep + pattn + ' = "' + val + '"';
        	toksep = ' & ';

        	flds[i].value = '';
			
 		};
    }	
    
    if ( tokq != '' ) {
    	tokq = '[' + tokq + '] ';
		document.getElementById('toklist').value += tokq;
		cqlparse(document.getElementById('toklist').value, 'cqltoks');
    };
    

};

function escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); // $& means the whole matched string
}

function showcql() {

	if ( cqpid == '' ) cqpid = defid;
	var cql = document.getElementById(cqpid).value; 
	if ( cql == '' ) return;

	var divid = 'cqlview';

	cqlparse(cql, divid);

	// Unhide the visualization
	if ( document.getElementById(divid) ) { document.getElementById(divid).style.display = 'block'; };

	// Hide the qb
	if ( document.getElementById('qbframe') ) { document.getElementById('qbframe').style.display = 'none'; };
	
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
	if ( document.getElementById('qbframe') ) { document.getElementById('qbframe').style.display = 'block'; };

	// Hide the visualization
	if ( document.getElementById('cqlview') ) { document.getElementById('cqlview').style.display = 'none'; };
	

};

function setquery(newcql, nodirect = false) {

	var cqpfld = document.getElementById(cqpid); 
	if ( typeof(cqpfld) == "undefined" ) { return false; }; // In case the field does not exist
    
	cqpfld.value = newcql;
	// Copy to #code as well if there is one
	if ( typeof(code) == 'object') { 
		code.innerText = newcql; 
		dohighlight(code);
	};
	if ( document.getElementById('fromqb') != null ) {
		document.getElementById('fromqb').value = '1';
	}
	
	// If the CQL field is hidden, auto submit
	if ( typeof(direct) != "undefined"  ) {
		if ( !nodirect ) cqpfld.form.submit();
	} else {
		document.getElementById('qbframe').style.display = "none";
	};

	return false; // Always fail - we do not want to actually execute this form
		
};

function updatequery( nodirect = false ) {

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
        var parse = /(.*?)\[(.*)\]/g.exec(name);                     
        if ( parse == null ) continue;
        var pattn = parse[2].replace('][', '');
        var udfeat = '';
        if ( parse[1] == 'vals' ) {
			var tmp = document.querySelector('[name="matches['+parse[2]+']"]');
			var matchtype;
			if ( tmp ) matchtype = tmp.value; else matchtype = '';
	        if ( flds[i].nodeName == "INPUT" ) {
				if ( matchtype == 'contains' ) {
					val = '.*' + val + '.*';
				} else if ( matchtype == 'startswith' ) {
					val = val + '.*';
				} else if ( matchtype == 'endsin' ) {
					val = '.*' + val;
				};
	        } else if ( flds[i].nodeName == "SELECT" ) {
				if ( typeof(pattname) != 'undefined' && typeof(pattname[pattn]) != 'undefined' 
								&& pattname[pattn].values == 'multi' ) {
					if ( typeof(mvsep) == 'undefined' ) var mvsep = ',';
					val = '(.*'+mvsep+')?' + val + '('+mvsep+'.*)?';
				};
				if ( matchtype == 'udfeats' ) {
					var tmp = /(.*):(.*)/g.exec(pattname);       
					if ( tmp != null ) {
						pattname = tmp[1];
						var udfeat = tmp[2];
						if ( typeof(udsep) == 'undefined' ) var udsep = '[|]';
						val = '(.*'+udsep+')?' + udfeat + '=' + val + '('+udsep+'.*)?';
					};    
				};
	        };
        	tokq += toksep + pattname + ' = "' + val + '"';
        	toksep = ' & ';
        } else if ( parse[1] == 'atts' ) {
			var matchtype = '';
			if ( document.querySelector('[name="matches['+parse[2]+']"]') ) { matchtype = document.querySelector('[name="matches['+parse[2]+']"]').value; };
			var tmp = /(.*)\]\[/g.exec(parse[2]);  
			if ( flds[i].nodeName == 'SELECT' ) {
				val = escapeRegExp(val);
			};
			if ( tmp && tmp[1] ) {
 				parse[2] = tmp[1];
 				val = getSelectValues(flds[i]).join('|');
			} else if ( matchtype == 'contains' ) {
				val = '.*' + val + '.*';
			} else if ( matchtype == 'startswith' ) {
				val = val + '.*';
			} else if ( matchtype == 'endsin' ) {
				val = '.*' + val;
			};
			if ( typeof(pattname) != 'undefined' && typeof(pattname[pattn]) != 'undefined' 
							&& pattname[pattn].values == 'multi' ) {
				if ( typeof(mvsep) == 'undefined' ) var mvsep = pattname[pattn].multisep;
				if ( typeof(mvsep) == 'undefined' ) var mvsep = ',';
				if ( mvsep == '|' ) mvsep = '[|]';
				val = '(.*'+mvsep+')?(' + val + ')('+mvsep+'.*)?';
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
    
    var wifc = document.getElementById('within');
    var wif = 'text';
    if ( wifc ) { wif = wifc.value; };
    
    
    // Make it a text-based search if there are only token restrictions
    if ( newcql == '' && tokq == '' && wif == 'text' ) {
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
    
    if ( wif != 'text' ) {
    	if ( newcql == '' && tokq == '' ) { newcql = '[]'; };
    	newcql += ' within ' + wif;
    };
    
    // Unless there is a within, add within text
	if ( !newcql.match(/ within /) && !docquery ) newcql += ' within text';
    
	cqpfld.value = newcql;
	// Copy to #code as well if there is one
	if ( typeof(code) == 'object') { 
		code.innerText = newcql; 
		dohighlight(code);
	};
	if ( document.getElementById('fromqb') != null ) {
		document.getElementById('fromqb').value = '1';
	}
	
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
};
function filltag ( add=0, full=0 ) {
	var fulltag = document.getElementById('mainpos').value;
	var maintag = fulltag; 
	for ( var i=maintag.length-1; i<taglen[maintag.substr(0,1)]; i++ ) {
		var valfld = 'posopt-' + maintag + '-' + (i+1);
		if ( typeof(noval) == 'undefined' ) noval = '.';
		posval = noval;
		if ( document.getElementById(valfld) ) posval  = document.getElementById(valfld).value;
		fulltag += posval;
	}; 
	var newtag = fulltag;
	if ( !full ) newtag = newtag.replace(/\.+$/, '') + '.*'
	if ( add ) document.getElementById(tagfld).value += '|' + newtag;
	else document.getElementById(tagfld).value = newtag;
	document.getElementById('tbframe').style.display = 'none';
};
function changepos (elm) {
	if (tagprev) document.getElementById('posopt-' + tagprev).style.display = 'none';
	tagprev = elm.value;
	document.getElementById('posopt-' + tagprev).style.display = 'block';
};

function getSelectValues(select) {
  var result = [];
  var options = select && select.options;
  var opt;

  for (var i=0, iLen=options.length; i<iLen; i++) {
    opt = options[i];

    if (opt.selected) {
      result.push(opt.value || opt.text);
    }
  }
  return result;
}

var cqlerr;
function setcqlerror (txt) {
	cqlerr = txt;
	if ( !document.getElementById('cqlconsole') ) return;
	document.getElementById('cqlconsole').innerHTML = cqlerr;
	document.getElementById('cqlconsole').style.color = '#aa0000';
};
function delcqlerror () {
	cqlerr = '';
	if ( !document.getElementById('cqlconsole') ) return;
	document.getElementById('cqlconsole').innerHTML = cqlerr;
	document.getElementById('cqlconsole').style.color = '#006600';
};

// Functions for the Misbehave/PEG parser
function dohighlight(code) {
	delcqlerror();
	var cql = code.innerText;
	var parser = window.HLPARSER; var parsed;
	var hl = cql;
	if ( cql.match(/\[/) ) { // Only run the parser if we have a CQL type query
		try {
			parsed = parser.parse(cql); // Make HTML safe);
			hl = htmlFrom(parsed);
			if ( typeof(hl) == 'object' ) hl = cql; // Avoid showing [object]
			code.innerHTML = hl;
			checkatts(code);
		} catch (err) {
			parsed = { 'items': [] };
			var start = err.location.start.offset;
			var end = err.location.end.offset;
			hl = hl.replace('<', '&lt;');
			hl = hl.substr(0,start) + '<span class=wrong>' + hl.substr(start,end-start) + '</span>' + hl.substr(end);
			setcqlerror(err.message);
			code.innerHTML = hl;
		};
	};
};

function isArraysOfStrings(a) {
	if (a instanceof Array){
		for(var i=a.length;i--;){
			if (a[i] instanceof Array){
				if (!isArraysOfStrings(a[i])) return false;
			} else if (typeof a[i] !== 'string') return false;
		}
		return true;
	}
}

function flatten(a) {
	var string = '';
	if (a instanceof Array){
		for(var i=0;i<a.length;i++ ){
			if (a[i] instanceof Array){
				string += flatten(a[i]);
			} else if (typeof a[i] == 'string') string += a[i];
		}
	}
	return string;
};

function checkatts (code) {
	patts = code.getElementsByClassName('pAttname');
	for(var i=0;i<patts.length;i++ ){
		patt = patts[i].innerText;
		if ( pattlist instanceof Array && !pattlist.includes(patt) ) setcqlerror('pattribute <b>' + patt + '</b> is not defined in this corpus');

	};
	satts = code.getElementsByClassName('sAttname');
	for(var i=0;i<satts.length;i++ ){
		satt = satts[i].innerText;
		if ( sattlist instanceof Array && !sattlist.includes(satt) ) setcqlerror('sattribute <b>' + satt + '</b> is not defined in this corpus');
	};
	regions = code.getElementsByClassName('Regionname');
	for(var i=0;i<regions.length;i++ ){
		region = regions[i].innerText;
		if ( regionlist instanceof Array && !regionlist.includes(region) ) setcqlerror('region <b>' + region + '</b> is not defined in this corpus');
	};
	toknames = code.getElementsByClassName('Tokname');
	deftoks = [];
	for(var i=0;i<toknames.length;i++ ) {
		tmp = toknames[i].innerText;
		var mtch = tmp.match(/^([a-zA-Z0-9]+):/);
		if (mtch) deftoks.push(mtch[1]);
	}; 
	toknames = code.getElementsByClassName('Tokenname');
	for(var i=0;i<toknames.length;i++ ){
		tokname = toknames[i].innerText;
		if ( tokname == 'match' || tokname == 'matchend' || tokname == 'target' ) {
		} else {
			if ( !deftoks.includes(tokname) ) setcqlerror('token name <b>' + tokname + '</b> is not defined in the query');
		};
	};
};

function htmlFrom(node){
	if ( node && isArraysOfStrings(node.val) ) {
		return '<span class="'+node.elm+'">'+flatten(node.val).replace('<', '&lt;')+'</span>';
	} else if (node instanceof Array){
		return node.map(htmlFrom).join('');		
	} else if (node && node.elm){
		return '<span class="'+node.elm+'">'+htmlFrom(node.val)+'</span>';
	} else{
		return node || "";
	};
};

function elm2txt(helm) {
	var helmtype = helm.getAttribute('class');
	expl = '';
	if ( helmtype == 'Token' ) {
		var multi = helm.querySelector('.Multiplier').innerText;
		if ( multi == '+' ) {
			tokcnt = 'one or more';
		} else if ( multi == '*' ) {
			tokcnt = '0 or more';
		} else if ( res = multi.match(/\{([0-9,]+),\}/) ) {
			tokcnt = res[1] + ' or more';
		} else if ( res = multi.match(/\{,([0-9,]+)\}/) ) {
			tokcnt = 'up to ' + res[1];
		} else if ( res = multi.match(/\{([0-9,]+)\}/) ) {
			tokcnt = res[1].replace(',', ' to ');
		} else if ( multi == '?' ) {
			tokcnt = '1 optional';
		} else {
			tokcnt = '1';
		};
		expl += tokcnt + ' token(s) ';
		var tokname = helm.querySelector('.Tokname').innerText.replace(':', '');
		if ( tokname ) { 
			if ( tokname == '@' ) expl += '[the target token]';
			else expl += '[named ' + tokname +  ']';
		}; 
		var tokexpr = helm.querySelector('.Tokenexpr');
		if ( tokexpr ) {
			var exprelm = tokexpr.cloneNode(true);
			var patts = exprelm.querySelectorAll('.pAttname');
			patts.forEach(
			  function(currentValue) {
				if ( pattname && pattname[currentValue.innerText] ) var tmp = pattname[currentValue.innerText]['display'];
				if ( tmp ) currentValue.innerHTML = tmp;
			  },
			);
			var tmp = exprelm.querySelectorAll('.Flag');
			tmp.forEach(
			  function(currentValue) {
				var tmp = currentValue.innerText;
				var rep = "";
				if ( tmp.match(/c/) && tmp.match(/d/) ) rep = "(case/diacritics insensitive";
				else if ( tmp.match(/c/) ) rep = "(case insensitive";
				else if ( tmp.match(/d/) ) rep = "(diacritics insensitive";
				if ( tmp.match(/l/) ) {
					if ( rep ) { rep += ' - '; } else rep = '(';
					rep += "literal match";
				};
				rep += ')';
				if ( rep ) {
					currentValue.innerHTML = rep;
				};
			  },
			);
			var regs = exprelm.querySelectorAll('.Regex');
			regs.forEach(
			  function(currentValue) {
				var tmp = currentValue.innerText;
				var rep = "";
				if ( reval = tmp.match(/"\.\*(.+)\.\*"/) ) rep = "contains <b>" + reval[1] + "</b>";
				else if ( reval = tmp.match(/"\.\*(.+)"/) ) rep = "ends in <b>" + reval[1] + "</b>";
				else if ( reval = tmp.match(/"(.+)\.\*"/) ) rep = "starts with <b>" + reval[1] + "</b>";
				if ( rep ) {
					currentValue.innerHTML = rep;
					var tmp = currentValue.parentNode.previousSibling;
					if ( tmp.textContent == "=" ) {	
						tmp.textContent = " ";
					};
				};
			  },
			);
			expl += ' where ' + exprelm.innerHTML;
		} else {
			expl += ' of any type';
		};
	} else if ( helmtype == 'regionEdge' ) {
		var startend = 'start';
		var tmp = helm.querySelector('.Endmarker');
		if ( tmp ) startend = 'end';
		expl += startend + ' of sattribute region ';
		var regname = helm.querySelector('.Regionname');
		if ( regname ) { 
			var regnametxt = regname.innerText;
			if ( regionname[regnametxt] ) regnametxt = regionname[regnametxt];
			expl += ' <b>' + regnametxt + '</b>';
		}; 
		var regname = helm.querySelector('.sAttname');
		if ( regname ) { 
			var sattname = regname.innerText;
			var res = sattname.split('_');
			var regnametxt = res[0];
			if ( regionname[regnametxt] ) regnametxt = regionname[regnametxt];
			expl += ' <b>' + regnametxt + '</b> with attribute <b>' + res[1] + '</b>';
			var tmp = helm.querySelector('.Regex');
			if ( tmp ) {
				expl += ' = ' + tmp.outerHTML;
			};
		}; 
	} else if ( helmtype == 'Globalexpr' ) {
		expl += 'restrict results where ';
		var exprelm = helm.cloneNode(true);
		console.log(exprelm);
		var tmp = exprelm.querySelector('.Tokenname');
		if ( tmp ) { 
			var tokname = tmp.innerText; var ttxt = '';
			if ( tokname == 'match' ) {
				ttxt = ' first token in the result ';
			} else if ( tokname == 'matchend' ) {
				varttxt = ' last token in the result ';
			} else if ( tokname == 'target' ) {
				varttxt = ' the target token ';
			} else {
				varttxt = ' the token named <b>' + tokname + '</b> ';
			};
			tmp.innerHTML = ttxt;
			var tmp2 = tmp.nextSibling;
			if ( tmp2.textContent == "." ) {	
				tmp2.textContent = " fulfills ";
			};
		};
		var patts = exprelm.querySelectorAll('.pAttname');
		patts.forEach(
		  function(currentValue) {
			if ( pattname && pattname[currentValue.innerText] ) var tmp = pattname[currentValue.innerText]['display'];
			if ( tmp ) currentValue.innerHTML = '<b>' + tmp + '</b>';
		  },
		);
		var satts = exprelm.querySelectorAll('.sAttname');
		satts.forEach(
		  function(currentValue) {
			var sattname = currentValue.innerText;
			var res = sattname.split('_');
			var regnametxt = res[0];
			if ( regionname[regnametxt] ) regnametxt = regionname[regnametxt];
			ttxt = ' <b>' + regnametxt + '</b> with attribute <b>' + res[1] + '</b>';
			currentValue.innerHTML = ttxt;
		  },
		);
		expl += exprelm.innerHTML;
	} else {
		expl;
	};
	if ( expl ) expl = '<span>'+helm.innerHTML+'</span> <span style="color: grey; font-style: italic;">' + expl + '</span>';
	return expl;
}