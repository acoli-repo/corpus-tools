getPlainText = function(node){

	var html = node.innerHTML;
	var text = html;
	console.log(text);

	// White space are irrelevant in HTML
	text = text.replace(/[\r\s ]+/gmi, ' ');
	
	// Turn paragraphs into double linebreaks
	text = text.replace(/<br\/?> */gmi, '\n');
	text = text.replace(/<p> */gmi, '\n\n');
	text = text.replace(/<p [^>]*> */gmi, '\n\n');
	
	// Remove all the HTML tags
	while ( text.match(/<[^<>]+>/gmi) ) {
		text = text.replace(/<[^<>]+>/gmi, '');
	};

	text = text.replace(/^ */gmi, '');
	
	return text;

}