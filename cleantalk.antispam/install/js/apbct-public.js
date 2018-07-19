var ct_date = new Date(), 
	ctTimeMs = new Date().getTime(),
	ctMouseEventTimerFlag = true, //Reading interval flag
	ctMouseData = [],
	ctMouseDataCounter = 0;

function ctSetCookie(c_name, value) {
	document.cookie = c_name + "=" + encodeURIComponent(value) + "; path=/";
}

ctSetCookie("ct_ps_timestamp", Math.floor(new Date().getTime()/1000));
ctSetCookie("ct_fkp_timestamp", "0");
ctSetCookie("ct_pointer_data", "0");
ctSetCookie("ct_timezone", "0");
ctSetCookie("ct_checkjs", ct_checkjs_val);

setTimeout(function(){
	ctSetCookie("ct_timezone", ct_date.getTimezoneOffset()/60*(-1));
},1000);

//Writing first key press timestamp
var ctFunctionFirstKey = function output(event){
	var KeyTimestamp = Math.floor(new Date().getTime()/1000);
	ctSetCookie("ct_fkp_timestamp", KeyTimestamp);
	ctKeyStopStopListening();
}

//Reading interval
var ctMouseReadInterval = setInterval(function(){
	ctMouseEventTimerFlag = true;
}, 150);
	
//Writting interval
var ctMouseWriteDataInterval = setInterval(function(){
	ctSetCookie("ct_pointer_data", JSON.stringify(ctMouseData));
}, 1200);

//Logging mouse position each 150 ms
var ctFunctionMouseMove = function output(event){
	if(ctMouseEventTimerFlag == true){
		
		ctMouseData.push([
			Math.round(event.pageY),
			Math.round(event.pageX),
			Math.round(new Date().getTime() - ctTimeMs)
		]);
		
		ctMouseDataCounter++;
		ctMouseEventTimerFlag = false;
		if(ctMouseDataCounter >= 100){
			ctMouseStopData();
		}
	}
}

//Stop mouse observing function
function ctMouseStopData(){
	if(typeof window.addEventListener == "function"){
		window.removeEventListener("mousemove", ctFunctionMouseMove);
	}else{
		window.detachEvent("onmousemove", ctFunctionMouseMove);
	}
	clearInterval(ctMouseReadInterval);
	clearInterval(ctMouseWriteDataInterval);				
}

//Stop key listening function
function ctKeyStopStopListening(){
	if(typeof window.addEventListener == "function"){
		window.removeEventListener("mousedown", ctFunctionFirstKey);
		window.removeEventListener("keydown", ctFunctionFirstKey);
	}else{
		window.detachEvent("mousedown", ctFunctionFirstKey);
		window.detachEvent("keydown", ctFunctionFirstKey);
	}
}

if(typeof window.addEventListener == "function"){
	window.addEventListener("mousemove", ctFunctionMouseMove);
	window.addEventListener("mousedown", ctFunctionFirstKey);
	window.addEventListener("keydown", ctFunctionFirstKey);
}else{
	window.attachEvent("onmousemove", ctFunctionMouseMove);
	window.attachEvent("mousedown", ctFunctionFirstKey);
	window.attachEvent("keydown", ctFunctionFirstKey);
}