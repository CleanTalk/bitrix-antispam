var ct_date = new Date();

function ctSetCookie(c_name, value) {
    document.cookie = c_name + '=' + encodeURIComponent(value) + '; path=/';
}

ctSetCookie('ct_ps_timestamp', Math.floor(new Date().getTime()/1000));
ctSetCookie('ct_fkp_timestamp', '0');
ctSetCookie('ct_timezone', '0');

ct_attach_event_handler(window, 'DOMContentLoaded', ct_ready);

setTimeout(function(){
    ctSetCookie('ct_timezone', ct_date.getTimezoneOffset()/60*(-1));
    ctSetCookie('ct_checkjs', ct_checkjs_val);  
},1000);

/* Writing first key press timestamp */
var ctFunctionFirstKey = function output(event){
    var KeyTimestamp = Math.floor(new Date().getTime()/1000);
    ctSetCookie('ct_fkp_timestamp', KeyTimestamp);
    ctKeyStopStopListening();
}

/* Stop key listening function */
function ctKeyStopStopListening(){
    if(typeof window.addEventListener == 'function'){
        window.removeEventListener('mousedown', ctFunctionFirstKey);
        window.removeEventListener('keydown', ctFunctionFirstKey);
    }else{
        window.detachEvent('mousedown', ctFunctionFirstKey);
        window.detachEvent('keydown', ctFunctionFirstKey);
    }
}

if(typeof window.addEventListener == 'function'){
    window.addEventListener('mousedown', ctFunctionFirstKey);
    window.addEventListener('keydown', ctFunctionFirstKey);
}else{
    window.attachEvent('mousedown', ctFunctionFirstKey);
    window.attachEvent('keydown', ctFunctionFirstKey);
}
/* Ready function */
function ct_ready(){
    ctSetCookie('ct_visible_fields', 0);
    ctSetCookie('ct_visible_fields_count', 0);
    setTimeout(function(){
    for(var i = 0; i < document.forms.length; i++){
        var form = document.forms[i];
        if (form.action.toString().indexOf('/auth/?forgot_password') !== -1)  {
            continue;
        }
        form.onsubmit_prev = form.onsubmit;
        form.onsubmit = function(event){

            /* Get only fields */
            var elements = [];
            for(var key in this.elements){
                if(!isNaN(+key))
                elements[key] = this.elements[key];
            }

            /* Filter fields */
            elements = elements.filter(function(elem){

                var pass = true;

                /* Filter fields */
                if( getComputedStyle(elem).display    === 'none' ||   // hidden
                    getComputedStyle(elem).visibility === 'hidden' || // hidden
                    getComputedStyle(elem).opacity    === '0' ||      // hidden
                    elem.getAttribute('type')         === 'hidden' || // type == hidden
                    elem.getAttribute('type')         === 'submit' || // type == submit
                    elem.value                        === ''       || // empty value
                    elem.getAttribute('name')         === null
                ){
                return false;
                }

                /* Filter elements with same names for type == radio */
                if(elem.getAttribute('type') === 'radio'){
                    elements.forEach(function(el, j, els){
                    if(elem.getAttribute('name') === el.getAttribute('name')){
                        pass = false;
                        return;
                    }
                });
            }

            return true;
        });

        /* Visible fields count */
        var visible_fields_count = elements.length;

        /* Visible fields */
        var visible_fields = '';
        elements.forEach(function(elem, i, elements){
            visible_fields += ' ' + elem.getAttribute('name');
        });
        visible_fields = visible_fields.trim();

        ctSetCookie('ct_visible_fields', visible_fields);
        ctSetCookie('ct_visible_fields_count', visible_fields_count);

        /* Call previous submit action */
        if(event.target.onsubmit_prev instanceof Function){
            setTimeout(function(){
            event.target.onsubmit_prev.call(event.target, event);
            }, 500);
        }
        };
    }
    }, 1000);
}

function ct_attach_event_handler(elem, event, callback){
    if(typeof window.addEventListener === 'function') elem.addEventListener(event, callback);
    else                                              elem.attachEvent(event, callback);
}

function ct_remove_event_handler(elem, event, callback){
    if(typeof window.removeEventListener === 'function') elem.removeEventListener(event, callback);
    else                                                 elem.detachEvent(event, callback);
}

if(typeof jQuery !== 'undefined') {

/* Capturing responses and output block message for unknown AJAX forms */
jQuery(document).ajaxComplete(function (event, xhr, settings) {
    if (xhr.responseText && xhr.responseText.indexOf('\"apbct') !== -1) {
    try {
        var response = JSON.parse(xhr.responseText);
        if (typeof response.apbct !== 'undefined') {
        response = response.apbct;
        if (response.blocked) {
            alert(response.comment);
            if(+response.stop_script == 1)
            window.stop();
        }
        }                  
    } catch (e) {
        return;
    }

    }
});

}