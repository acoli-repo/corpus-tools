	var imgscale = imgdiv.offsetWidth/imgfacs.naturalWidth;
	var i; var toputs = []; var regsel = '';

	function showregions (regs,r,g,b) {
		
		regsel = regs;
		shiftx = 0; shifty = 10;
		document.getElementById('autoplace').style.display = 'none';

		// Remove the old regions
		var elms = imgdiv.getElementsByTagName('region');
		var tot = elms.length;
		for (i = tot; i > -1; i--) {
			elm = elms[i]; 
			if ( !elm ) { continue; };
			imgdiv.removeChild(elm);
		};
		toputs = [];
					
		var mtxt = document.getElementById('mtxt');
		var rl = regs.split(',');
		for (j = 0; j < rl.length; j++) {
			reg = rl[j]; 
			var elms = mtxt.getElementsByTagName(reg);
			for (i = 0; i < elms.length; i++) {
				elm = elms[i]; 
				if ( !elm ) { continue; };
				hlelm(elm, r, g, b);
			}; 
		};
		
		if ( toputs.length > 0 ) {
			document.getElementById('autoplace').style.display = 'block';
		};
	};
	
	function savexml () {
		var saveform = document.getElementById('xmlsave');
		saveform.rawxmlta.value = new XMLSerializer().serializeToString(xmlDoc);
		saveform.submit();
	};
	
	var shiftx = 0; var shifty = 10;
	function hlelm ( elm, r, g, b ) {
		var newelm = document.createElement('region');
		newelm.setAttribute('id','region_'+elm.getAttribute('id'));
		newelm.setAttribute('tid',elm.getAttribute('id'));

		var tmp = elm.getAttribute('bbox');
		var bbox; 
		if ( tmp ) { 
			bbox = tmp.split(' '); 
			if ( isNaN(bbox[0]) || isNaN(bbox[1]) || isNaN(bbox[2]) || isNaN(bbox[3]) ) { tmp = false; }
		}
		if ( !tmp ) { 
			// Show the unused element below the image
			var imgheight = imgdiv.offsetHeight/imgscale;
			var imgwidth = imgdiv.offsetWidth/imgscale;
			var divwidth = imgwidth/10;
			var divheight = 0.5*divwidth;
			newx = 0 + shiftx; newy = imgheight + shifty;
			shiftx += divwidth*1.2; if ( shiftx + divwidth > imgwidth ) { shiftx = 0; shifty += divheight*1.2; };
			bbox = [newx,newy,newx+divwidth,newy+divheight]; 
			newelm.style.marginBottom = '20px';
			
			toputs.push(newelm.getAttribute('id'));
		} else { 
			bbox = tmp.split(' ');
			
			// reposition in case we got off-screen
			if ( bbox[0] > imgdiv.offsetWidth ) { bbox[0] = imgdiv.offsetWidth * 0.9; }; 
			if ( bbox[1] > imgdiv.offsetHeight ) { bbox[1] = imgdiv.offsetHeight * 0.9; };
			if ( bbox[0] < 0 ) { bbox[0] = 0; }; 
			if ( bbox[1] < 0  ) { bbox[1] = 0; };
			if ( bbox[2] > imgdiv.offsetWidth ) { bbox[2] = imgdiv.offsetWidth; }; 
			if ( bbox[3] > imgdiv.offsetHeight ) { bbox[3] = imgdiv.offsetHeight; };
		};
		
		newelm.style.position = 'absolute';
		newelm.style.overflow = 'hidden';
		newelm.style.zIndex = 1000;
		newelm.style.height = (bbox[3]-bbox[1])*imgscale + 'px';
		newelm.style.width = (bbox[2]-bbox[0])*imgscale  + 'px';
		newelm.style.left = bbox[0]*imgscale  + 'px';
		newelm.style.top = bbox[1]*imgscale  + 'px';
		newelm.style.backgroundColor = 'rgba('+r+','+g+','+b+',0.4)';
		newelm.style.color = 'rgba(0,0,0,0.4)';
		newelm.classList.add('resize-drag');
		newelm.setAttribute('title',elm.getAttribute('id')+': '+elm.innerText);
		newelm.innerHTML = getinnertext(elm);
		newelm.setAttribute('bbox',elm.getAttribute('bbox'));
		imgdiv.appendChild(newelm);
	};
	
	var scl = 1;
	function scale(pls) {
		scl = scl * pls;
		imgdiv.style.transform = 'scale('+scl+','+scl+')';
		imgdiv.style['transform-origin'] = 'left top';
	};

	function getinnertext ( elm ) {
		if ( elm.tagName == "LB" ) {
			var lid = elm.getAttribute('id');
			var init = rawxml.indexOf('id="'+lid+'"'); 
			var rawltxt = rawxml.substring(init,init+300);
			if ( rawltxt.indexOf('<lb') > 0 ) { rawltxt = rawltxt.substring(0,rawltxt.indexOf('<lb')); };
			if ( rawltxt.indexOf('</p>') > 0 ) { rawltxt = rawltxt.substring(0,rawltxt.indexOf('</p>')); }; // This should not happen with proper use of <lb/> - but it does
			var ltxt = rawltxt.replace(/<[^>]+>/,'');
			var ltxt = ltxt.replace(/^[^>]+>/,'');
			return ltxt;
		} else {
			return elm.innerText;
		};
	};
	
  function autoplace () {
  	for ( var i=0; i<toputs.length; i++ ) {
  		toput = toputs[i];
  		var putelm = document.getElementById(toput);
		var imgheight = imgdiv.offsetHeight;
		var imgwidth = imgdiv.offsetWidth;
		if ( regsel.substr(0,2) == "p," ) {
			putelm.style.width = imgwidth*0.9  + 'px'; // 10% from the top margin
			putelm.style.left = imgwidth*0.05  + 'px'; // 10% from the left margin
			putelm.style.top = imgheight*0.05 + i*(imgheight/toputs.length)*0.9  + 'px';
			putelm.style.height = (imgheight/toputs.length)*0.85 + 'px';
		} else if ( regsel.substr(0,3) == "lb," ) {
			// TODO: These should be placed within their respective <p>
			putelm.style.width = imgwidth*0.9  + 'px'; // 10% from the top margin
			putelm.style.left = imgwidth*0.05  + 'px'; // 10% from the left margin
			putelm.style.top = imgheight*0.05 + i*(imgheight/toputs.length)*0.9  + 'px';
			putelm.style.height = (imgheight/toputs.length)*0.85 + 'px';			
		} else if ( regsel.substr(0,4) == "tok," ) {
		} else {
			console.log(regsel);
		};
  	};
  };
  
  function updateelm ( target ) {
    var tid = target.getAttribute('tid');
    var baseelm = xmlDoc.getElementById(tid);
    
    
    var x = target.getAttribute('data-x')*1; if ( isNaN(x) ) { x = 0; };
    var y = target.getAttribute('data-y')*1; if ( isNaN(y) ) { y = 0; };
    
    var newleft = (target.style.left.replace('px','')*1 + x)/imgscale;
    var newtop = (target.style.top.replace('px','')*1 + y)/imgscale;
    var newwidth = target.style.width.replace('px','')/imgscale; 
    var newheight = target.style.height.replace('px','')/imgscale; 
    var newright = newleft + newwidth; 
    var newbottom = newtop + newheight; 
    
    if ( isNaN(newleft) || isNaN(newtop) || isNaN(newright) || isNaN(newbottom) ) { 
    	console.log('Not a number - refusing to save: ' + newleft +' '+ newtop +' '+ newright + ' '+ newbottom);
    	return -1; 
    };
    
    var newbb = newleft +' '+ newtop +' '+ newright + ' '+ newbottom;
    baseelm.setAttribute('bbox', newbb);
  };
  


// Below are the Interact drag / resize functions

interact('.resize-drag')
  .draggable({
    onmove: window.dragMoveListener,
    restrict: {
      restriction: 'parent',
      elementRect: { top: 0, left: 0, bottom: 1, right: 1 }
    },
  })
  .resizable({
    // resize from all edges and corners
    edges: { left: true, right: true, bottom: true, top: true },

    // keep the edges inside the parent
    restrictEdges: {
      outer: 'parent',
      endOnly: true,
    },

    // minimum size
    restrictSize: {
      min: { width: 10, height: 5 },
    },

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

    target.setAttribute('data-x', x);
    target.setAttribute('data-y', y);
        
    updateelm(target);  
  });
  
  function dragMoveListener (event) {
    var target = event.target,
        // keep the dragged position in the data-x/data-y attributes
        x = (parseFloat(target.getAttribute('data-x')) || 0) + event.dx,
        y = (parseFloat(target.getAttribute('data-y')) || 0) + event.dy;

    // translate the element
    target.style.webkitTransform =
    target.style.transform =
      'translate(' + x + 'px, ' + y + 'px)';

    // update the posiion attributes
    target.setAttribute('data-x', x);
    target.setAttribute('data-y', y);

    updateelm(target);  
  }