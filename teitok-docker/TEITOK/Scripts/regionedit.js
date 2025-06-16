	var imgscale = imgdiv.offsetWidth/imgfacs.naturalWidth;
	var i; var toputs = []; var regsel = '';
	let crop = [];

	function showregions (regs,r,g,b) {
		
		regsel = regs;
		shiftx = 0; shifty = 10;
		if ( document.getElementById('autoplace') )  { document.getElementById('autoplace').style.display = 'none'; };

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
			// Create a crop section to autoplace into
			// Decide where in the image the lines start and end
			if ( !crop.length ) {
				var cropbox = document.createElement('region');
				var imgheight = imgdiv.offsetHeight;
				var imgwidth = imgdiv.offsetWidth;
				crop = [ imgwidth*0.05, imgheight*0.05, imgwidth*0.9, imgheight*0.9 ]; // If not defined, take a 10% margin
				cropbox.setAttribute('id', 'cropbox'); 
				cropbox.setAttribute('z-index', '1000'); 
				cropbox.style['opacity'] = '0.5'; 
				cropbox.style['position'] = 'absolute'; 
				cropbox.style.backgroundColor = '#eeeeee'; 
				cropbox.style.color = '#ffff66'; 
				cropbox.style.width = crop[2] + 'px'; 
				cropbox.style.left = crop[0]  + 'px'; 
				cropbox.style.top = crop[1] + 'px';
				cropbox.style.height = crop[3] + 'px';
				console.log(cropbox);
				document.getElementById('imgdiv').appendChild(cropbox);
				makedraggable(cropbox);
			};

			document.getElementById('autoplace').style.display = 'block';
		};
	};
	
	function savexml () {
		var saveform = document.getElementById('xmlsave');
		saveform.rawxmlta.value = new XMLSerializer().serializeToString(xmlDoc);
		window.onbeforeunload = null;
		saveform.submit();
	};

	function addlineblock(pagid) {
		var newelm = document.createElement('lineblock');
		newelm.setAttribute('id', 'newlb');
		newelm.setAttribute('bbox', (imgdiv.offsetWidth * 0.05)/imgscale+' '+(imgdiv.offsetHeight * 0.05)/imgscale+' '+(imgdiv.offsetWidth * 0.95)/imgscale+' '+(imgdiv.offsetHeight * 0.95)/imgscale);
		var baseelm = xmlDoc.getElementById(pagid);
		baseelm.appendChild(newelm);
		hlelm(newelm, 200,150,0);
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
		newelm.innerHTML = getinnertext(elm);
		newelm.setAttribute('title',elm.getAttribute('id')+': '+newelm.innerText);
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
	console.log(crop);
  	for ( var i=0; i<toputs.length; i++ ) {
  		toput = toputs[i];
  		var putelm = document.getElementById(toput);
		if ( regsel.substr(0,2) == "p," ) {
			putelm.style.left = crop[0]  + 'px'; 
			putelm.style.top = crop[1] + i*(crop[3]/toputs.length)*0.9  + 'px';
			putelm.style.width = crop[2] + 'px'; 
			putelm.style.height = (crop[3]/toputs.length)*0.95 + 'px';
		} else if ( regsel.substr(0,3) == "lb," ) {
			// TODO: These should be placed within their respective <p>
			putelm.style.left = crop[0]  + 'px'; 
			putelm.style.top = crop[1] + i*(crop[3]/toputs.length)  + 'px';
			putelm.style.width = crop[2] + 'px'; 
			putelm.style.height = (crop[3]/toputs.length)*0.95 + 'px';
		} else if ( regsel.substr(0,4) == "tok," ) {
		} else {
			console.log(regsel);
		};
  	};
  };
  
  // Update an element after it has been dragged or resized
  function updateelm ( target ) {
    var tid = target.getAttribute('tid');
    
    if ( target.getAttribute('id') == 'cropbox' ) {
		var x = target.getAttribute('data-x')*1; if ( isNaN(x) ) { x = 0; };
		var y = target.getAttribute('data-y')*1; if ( isNaN(y) ) { y = 0; };
		var newleft = (target.style.left.replace('px','')*1);// /imgscale;
		var newtop = (target.style.top.replace('px','')*1);// /imgscale;
		var newwidth = target.style.width.replace('px',''); // /imgscale; 
		var newheight = target.style.height.replace('px',''); // /imgscale; 
		crop = [newleft, newtop, newwidth, newheight];
		
    	return -1;
    };
    
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
	window.onbeforeunload = function () {
		return 'Your XML has been changed, unsaved changes will be lost.';
	};

  };
  

	function makelines ( ) {
	    var bboxelm = xmlDoc.getElementById('newlb');
	    var bbox = bboxelm.getAttribute('bbox').split(' ');
	    var pagenode = bboxelm.parentNode;
	    var lbid = 'lineb_'+xmlDoc.getElementsByTagName('lineblock').length;
	    bboxelm.setAttribute('id', lbid);
	    var linecnt = document.getElementById('linecnt').value*1;
	    var lineheight = (bbox[3]-bbox[1])/linecnt;
		var mtxt = document.getElementById('mtxt');
	    for ( var i=0; i<linecnt; i++ ) {
			var newelm = document.createElement('line');
			newelm.setAttribute('id', lbid+'_'+(i+1));
			var newtop = bbox[1]*1 + i*lineheight;
			newelm.setAttribute('bbox', bbox[0]+' '+newtop+' '+bbox[2]+' '+(newtop+lineheight*0.98));
			bboxelm.appendChild(newelm);
			// Also add to the MTXT since that is where we grab our regions for display from
			var newelm = document.createElement('line');
			newelm.setAttribute('id', lbid+'_'+(i+1));
			var newtop = bbox[1]*1 + i*lineheight;
			newelm.setAttribute('bbox', bbox[0]+' '+newtop+' '+bbox[2]+' '+(newtop+lineheight*0.98));
			mtxt.appendChild(newelm);
	    };
	    // pagenode.removeChild(bboxelm);
		showregions ('lb,l,line',0,0,255);
	};



function makedraggable(rectangle) {
  const parent = rectangle.parentElement;
  let isResizing = false;
  let isDragging = false;
  let startX, startY, startWidth, startHeight, startLeft, startTop;
  let resizeDirection = null;
  const edgeThreshold = 10; // pixels from edge considered "resizable"

  rectangle.addEventListener('mousedown', (e) => {
    const rect = rectangle.getBoundingClientRect();
    const parentRect = parent.getBoundingClientRect();
    
    // Check which edge is being grabbed
    const rightEdge = rect.right - e.clientX < edgeThreshold;
    const bottomEdge = rect.bottom - e.clientY < edgeThreshold;
    const corner = rightEdge && bottomEdge;
    
    if (rightEdge || bottomEdge || corner) {
      e.preventDefault();
      isResizing = true;
      
      // Set resize direction
      if (corner) resizeDirection = 'corner';
      else if (rightEdge) resizeDirection = 'right';
      else if (bottomEdge) resizeDirection = 'bottom';
      
      startX = e.clientX;
      startY = e.clientY;
      startWidth = rect.width;
      startHeight = rect.height;
      startLeft = rect.left - parentRect.left;
      startTop = rect.top - parentRect.top;
      
      // Update cursor
      updateCursor(resizeDirection);
      
      document.addEventListener('mousemove', resize);
      document.addEventListener('mouseup', stopResize);
    } else {
      // Dragging logic
      e.preventDefault();
      isDragging = true;
      offsetX = e.clientX - rect.left;
      offsetY = e.clientY - rect.top;
      rectangle.style.cursor = 'move';
      
      document.addEventListener('mousemove', drag);
      document.addEventListener('mouseup', stopDrag);
    }
  });

  // Update cursor when hovering near edges
  rectangle.addEventListener('mousemove', (e) => {
    if (isResizing || isDragging) return;
    
    const rect = rectangle.getBoundingClientRect();
    const rightEdge = rect.right - e.clientX < edgeThreshold;
    const bottomEdge = rect.bottom - e.clientY < edgeThreshold;
    
    if (rightEdge && bottomEdge) {
      rectangle.style.cursor = 'se-resize';
    } else if (rightEdge) {
      rectangle.style.cursor = 'e-resize';
    } else if (bottomEdge) {
      rectangle.style.cursor = 's-resize';
    } else {
      rectangle.style.cursor = 'default';
    }
  });

  function resize(e) {
    if (!isResizing) return;
    
    const parentRect = parent.getBoundingClientRect();
    const maxWidth = parentRect.width - startLeft;
    const maxHeight = parentRect.height - startTop;
    
    if (resizeDirection === 'right') {
      let width = startWidth + (e.clientX - startX);
      width = Math.min(Math.max(width, 50), maxWidth); // Min width 50px
      rectangle.style.width = `${width}px`;
    } 
    else if (resizeDirection === 'bottom') {
      let height = startHeight + (e.clientY - startY);
      height = Math.min(Math.max(height, 50), maxHeight); // Min height 50px
      rectangle.style.height = `${height}px`;
    }
    else if (resizeDirection === 'corner') {
      let width = startWidth + (e.clientX - startX);
      let height = startHeight + (e.clientY - startY);
      width = Math.min(Math.max(width, 50), maxWidth);
      height = Math.min(Math.max(height, 50), maxHeight);
      rectangle.style.width = `${width}px`;
      rectangle.style.height = `${height}px`;
    }
  }

  function drag(e) {
    if (!isDragging) return;
    
    const parentRect = parent.getBoundingClientRect();
    const rect = rectangle.getBoundingClientRect();
    
    let left = e.clientX - offsetX - parentRect.left;
    let top = e.clientY - offsetY - parentRect.top;
    
    // Constrain within parent
    left = Math.max(0, Math.min(left, parentRect.width - rect.width));
    top = Math.max(0, Math.min(top, parentRect.height - rect.height));
    
    rectangle.style.left = `${left}px`;
    rectangle.style.top = `${top}px`;
  }

  function stopResize() {
    isResizing = false;
    rectangle.style.cursor = 'default';
    document.removeEventListener('mousemove', resize);
    document.removeEventListener('mouseup', stopResize);
    updateelm(rectangle);
  }

  function stopDrag() {
    isDragging = false;
    rectangle.style.cursor = 'default';
    document.removeEventListener('mousemove', drag);
    document.removeEventListener('mouseup', stopDrag);
    updateelm(rectangle);
  }

  function updateCursor(direction) {
    if (direction === 'corner') {
      rectangle.style.cursor = 'se-resize';
    } else if (direction === 'right') {
      rectangle.style.cursor = 'e-resize';
    } else if (direction === 'bottom') {
      rectangle.style.cursor = 's-resize';
    }
  }
};

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