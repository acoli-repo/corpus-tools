var sessiontest = true;
var testloop = window.setInterval(function() {
	var xhr = new XMLHttpRequest();
  	var url = 'index.php?action=sessionrenew&type=text&rand='+Math.random();

	xhr.open("GET", url);

	// xhr.setRequestHeader("Accept", "application/json");

	xhr.onreadystatechange = function () {
	   if (xhr.readyState === 4) {
	      var stat = xhr.responseText.trim();
	      if ( stat == "logged out" ) {
	      	console.log('logged out');
			if ( sessiontest ) {
				alert('You seem to have been logged out from TEITOK - copy any relevant material you might be loosing, and renew your session in another window');
				clearInterval(testloop);
			};
			sessiontest = false;
	      } else if ( stat == "logged in" ) {
			// console.log('ok');
	      } else if ( stat == "sso logged in" ) {
			// console.log('ok');
	      } else {
			console.log('?? ' + stat);
	      };
	   }};

	xhr.send();
}, 30000);