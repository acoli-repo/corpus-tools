var pdffile;
var pdf = null;
var pagenr = 0;
var pageside = 1;
var pagestyle = 1;
var pagestart = 2;
var pageend;
var pagetot = 0;
pageRendering = false;

function initialize() {
	if ( 1==1 ) { pdffile = 'pdf/' + pdffile; };
	console.log(pdffile);
	document.frm.teiname.value = pdffile.replace('.pdf', '').replace('pdf/', '');
    PDFJS.getDocument(pdffile).then(function (pdfDoc_) {
    pdf = pdfDoc_;
		pagenr = 1;
		
    // Initial/first page rendering
    render();
    
    // Show the tools
    document.getElementById('pdfinfo').style.display = 'block';
  });
};

function topage(goto) {
	if ( goto == 'next' ) {
		if ( pageside == 2 || pagestyle == 1 ) {
			pagenr = Math.min(pdf.numPages,pagenr+1);
			pageside = 1;
		} else {
			pageside = 2;
		};
	} else if ( goto == 'prev' ) {
		if ( pageside == 1 || pagestyle == 1 ) {
			pagenr = Math.max(1,pagenr-1);
			pageside = pagestyle;
		} else {
			pageside = 1;
		};
	} else if ( goto == 'last' ) {
		pagenr = pdf.numPages;
	} else {
		pagenr = goto; pageside = 1;
	};
	render();
};

function setstart () {
	document.frm.pagestart.value = ((pagenr-1)*2) + pageside;
	render();
};

function setend () {
	document.frm.pageend.value = ((pagenr-1)*2) + pageside;
	render();
};

function setstyle (num) {
	pagestyle = num;
	render();
};

function render () {
		// Using promise to fetch the page
		pageRendering = true;
		pdf.getPage(pagenr).then(function(page) {

			var desiredWidth = 600;
			var viewport = page.getViewport(1/pagestyle);
			var scale = desiredWidth / viewport.width;
			var scaledViewport = page.getViewport(scale);
			var pdfViewBox = page.pageInfo.view;
			
			var xOffset = scaledViewport.width - scaledViewport.width/pageside;
			
			var myviewport = new PDFJS.PageViewport(pdfViewBox, scale, page.rotate, -xOffset, 0);
		
			// Prepare canvas using PDF page dimensions
			var canvas = document.getElementById('the-canvas');
			var context = canvas.getContext('2d');
			canvas.height = scaledViewport.height;
			canvas.width = scaledViewport.width/pagestyle;

			// Render PDF page into canvas context
			var renderContext = {
				canvasContext: context,
				viewport: myviewport
			};
			page.render(renderContext);
		});
		
		var sidetxt = '';
		var pagetxt = '';
		if ( pagestyle == 2 ) { 
			if ( pageside == 2 ) { sidetxt = 'b'; } else {  sidetxt = 'a'; }; 
		};
		pagestart = document.frm.pagestart.value;
		pageend = document.frm.pageend.value;
		if ( 
			( pagestyle == 1 && pagenr >= pagestart && ( pageend == '' || pageend >= pagenr ) ) 
			||
			( pagestyle == 2 && ((pagenr-1)*2) + pageside >= pagestart && ( pageend == '' || pageend >= ((pagenr-1)*2) + pageside ) )
			) {
			pagetxt = pagenr - pagestart;
			if ( pagestyle == 2 ) { 
				if ( pageside == 2 ) { 
					pagetxt = pagenr-Math.floor(pagestart/2) + 'r';
				} else { 
					pagetxt = (pagenr-Math.floor(pagestart/2)-1) + 'v';
				}; 
			};
			imgname = document.frm.teiname.value + '_' + pagetxt + '.jpg';
		} else {
			imgname = '(not exported)';
		};
		document.getElementById('the-caption').innerHTML = pdffile.replace('pdf/', '') 
			+ ' - page ' + pagenr + sidetxt + ' of ' + pdf.numPages
			+ ' - ' + imgname;
};

