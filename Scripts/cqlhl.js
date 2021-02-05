    function dohighlight(code) {
    	var cql = code.innerText;
		var parser = PARSER; var parsed;
		document.getElementById('cqlerror').innerHTML = '';
		var hl = cql;
		if ( cql != '' ) {
			try {
				parsed = parser.parse(cql);
				hl = htmlFrom(parsed);
				code.innerHTML = hl;
				checkatts(code);
			} catch (err) {
				parsed = { 'items': [] };
				document.getElementById('cqlerror').innerHTML = err;
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
			if ( pattlist instanceof Array && !pattlist.includes(patt) ) document.getElementById('cqlerror').innerHTML = 'pattribute <b>' + patt + '</b> is not defined in this corpus'

		};
		satts = code.getElementsByClassName('sAttname');
		for(var i=0;i<satts.length;i++ ){
			satt = satts[i].innerText;
			if ( sattlist instanceof Array && !sattlist.includes(satt) ) document.getElementById('cqlerror').innerHTML = 'sattribute <b>' + satt + '</b> is not defined in this corpus'
		};
		regions = code.getElementsByClassName('Regionname');
		for(var i=0;i<regions.length;i++ ){
			region = regions[i].innerText;
			if ( regionlist instanceof Array && !regionlist.includes(region) ) document.getElementById('cqlerror').innerHTML = 'region <b>' + region + '</b> is not defined in this corpus'
		};
	};

	function htmlFrom(node){
		if ( node && isArraysOfStrings(node.val) ) {
			return '<span title="'+node.elm+'" class="'+node.elm+'">'+flatten(node.val)+'</span>';
		} else if (node instanceof Array){
			return node.map(htmlFrom).join('');		
		} else if (node && node.elm){
			return '<span title="'+node.elm+'" class="'+node.elm+'">'+htmlFrom(node.val)+'</span>';
		} else{
			return node || "";
		}
	}
    
    var code = document.getElementById('cqlfld');
    var misbehave = new Misbehave(code, {
      oninput : function() {
        dohighlight(code)
      }
    })

    var pre = document.querySelector('#pre')
    pre.onclick = function() {
      code.focus()
      return false
    }
