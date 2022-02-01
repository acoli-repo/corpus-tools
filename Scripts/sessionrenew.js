var sessiontest = true;
var testloop = window.setInterval(function() {
  var el=document.createElement('img');
  el.src='index.php?action=sessionrenew&rand='+Math.random();
  el.style.opacity=.01;
  el.style.width=1;
  el.style.height=1;
  el.onload=function() {	
    document.body.removeChild(el);
  }
  el.onerror=function(e) {	
  	console.log('session check failed');
  	console.log(e);
  	if ( sessiontest ) {
  		alert('You seem to have been logged out from TEITOK - copy any relevant material you might be loosing, and renew your session in another window');
    	clearInterval(testloop);
    };
    sessiontest = false;
    document.body.removeChild(el);
  }
  document.body.appendChild(el);
}, 30000);