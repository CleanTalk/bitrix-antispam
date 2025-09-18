
window.addEventListener('load', function() {
    // if ( ! +ctPublic.settings__forms__check_external ) {
    //     return;
    // }

    setTimeout(function() {
        ctProtectExternal();
    }, 2000);
});

/**
 * Handle external forms
 */
function ctProtectExternal() {
    for (let i = 0; i < document.forms.length; i++) {
        const currentForm = document.forms[i];

        // skip excluded forms
        // if (formIsExclusion(currentForm)) {
        //     continue;
        // }

        // Ajax checking for the integrated forms - will be changed the whole form object to make protection
        if (isIntegratedForm(currentForm)) {
            apbctProcessExternalForm(currentForm, i, document);
        }
    }
}

/**
 * Check if the form is integrated
 * @param {HTMLFormElement} form
 * @returns {boolean}
 */
function isIntegratedForm(form) {
    // if form contains input with class b24-form-control
    if (form.querySelector('input[class*="b24-form-control"]')) {
        console.log('Form is integrated');
        return true;
    }
    console.log('Form is not integrated');
    return false;
}

/**
 * Process external form
 * @param {HTMLFormElement} form
 * @param {number} iterator
 * @param {HTMLDocument} document
 */
function apbctProcessExternalForm(currentForm, iterator, documentObject) {
    console.log('Processing external form');

    const cleantalkPlaceholder = document.createElement('i');
    cleantalkPlaceholder.className = 'cleantalk_placeholder';
    cleantalkPlaceholder.style = 'display: none';

    currentForm.parentElement.insertBefore(cleantalkPlaceholder, currentForm);

    // Deleting form to prevent submit event
    const prev = currentForm.previousSibling;
    const formHtml = currentForm.outerHTML;
    const formOriginal = currentForm;
    const formContent = currentForm.querySelectorAll('input, textarea, select');

    // Remove the original form
    currentForm.parentElement.removeChild(currentForm);

    // Insert a clone
    const placeholder = document.createElement('div');
    placeholder.innerHTML = formHtml;
    const clonedForm = placeholder.firstElementChild;
    prev.after(clonedForm);

    if (formContent && formContent.length > 0) {
        formContent.forEach(function(content) {
            if (content && content.name && content.type !== 'submit' && content.type !== 'button') {
                if (content.type === 'checkbox') {
                    const checkboxInput = clonedForm.querySelector(`input[name="${content.name}"]`);
                    if (checkboxInput) {
                        checkboxInput.checked = content.checked;
                    }
                } else {
                    const input = clonedForm.querySelector(
                        `input[name="${content.name}"], ` +
                        `textarea[name="${content.name}"], ` +
                        `select[name="${content.name}"]`,
                    );
                    if (input) {
                        input.value = content.value;
                    }
                }
            }
        });
    }

    const forceAction = document.createElement('input');
    forceAction.name = 'action';
    forceAction.value = 'cleantalk_force_ajax_check';
    forceAction.type = 'hidden';

    const reUseCurrentForm = documentObject.forms[iterator];

    reUseCurrentForm.appendChild(forceAction);
    reUseCurrentForm.apbctPrev = prev;
    reUseCurrentForm.apbctFormOriginal = formOriginal;

    documentObject.forms[iterator].onsubmit = function(event) {
        event.preventDefault();
        sendAjaxCheckingFormData(event.currentTarget);
    };
}

/**
 * Replace input values from one form to another
 * @param {HTMLElement} sourceForm
 * @param {HTMLElement} targetForm
 */
function apbctReplaceInputsValuesFromOtherForm(sourceForm, targetForm) {
    if (!sourceForm || !targetForm) return;
    
    const sourceInputs = sourceForm.querySelectorAll('input, textarea, select');
    sourceInputs.forEach(function(sourceInput) {
        if (sourceInput.name && sourceInput.type !== 'submit' && sourceInput.type !== 'button') {
            const targetInput = targetForm.querySelector(`[name="${sourceInput.name}"]`);
            if (targetInput) {
                if (sourceInput.type === 'checkbox' || sourceInput.type === 'radio') {
                    targetInput.checked = sourceInput.checked;
                } else {
                    targetInput.value = sourceInput.value;
                }
            }
        }
    });
}

/**
 * ApbctHandler class for handling block messages
 */
function ApbctHandler() {
    this.parseBlockMessage = function(result) {
        let message = '';
        if (result.apbct && result.apbct.comment) {
            message = result.apbct.comment;
        } else if (result.error && result.error.msg) {
            message = result.error.msg;
        } else if (result.data && result.data.message) {
            message = result.data.message;
        }
        
        if (message) {
            alert(message);
            if (result.apbct && result.apbct.stop_script == 1) {
                window.stop();
            }
        }
    };
}

/**
 * Sending Ajax for checking form data
 * @param {HTMLElement} form
 * @param {HTMLElement} prev
 * @param {HTMLElement} formOriginal
 */
function sendAjaxCheckingFormData(form) {
    let botDetectorEventToken = localStorage.getItem('bot_detector_event_token');
    if (typeof botDetectorEventToken === 'string') {
        botDetectorEventToken = JSON.parse(botDetectorEventToken);
        if (typeof botDetectorEventToken === 'object') {
            botDetectorEventToken = botDetectorEventToken.value;
        }
    }
    const data = {
        'ct_bot_detector_event_token': botDetectorEventToken,
    };
    let elems = form.elements;
    elems = Array.prototype.slice.call(elems);

    elems.forEach(function(elem, y) {
        elem.name === '' ? data['input_' + y] = elem.value : data[elem.name] = elem.value;
    });

    // Use Bitrix AJAX framework
    if (typeof BX !== 'undefined' && BX.ajax) {
        BX.ajax({
            url: '/bitrix/components/cleantalk/ajax/ajax.php',
            method: 'POST',
            data: data,
            dataType: 'json',
            async: false,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            onsuccess: function(result) {
                console.log('CleanTalk AJAX success:', result);
                if ((result.apbct === undefined && result.data === undefined) ||
                    (result.apbct !== undefined && ! +result.apbct.blocked)
                ) {
                    // Clear service fields
                    // for (const el of form.querySelectorAll('input[value="cleantalk_force_ajax_check"]')) {
                    //     el.remove();
                    // }

                    const formNew = form;
                    form.parentElement.removeChild(form);
                    const prev = form.apbctPrev;
                    const formOriginal = form.apbctFormOriginal;
                    // let isExternalFormsRestartRequired = apbctExternalFormsRestartRequired(formOriginal);
                    apbctReplaceInputsValuesFromOtherForm(formNew, formOriginal);

                    prev.after(formOriginal);

                    for (const el of formOriginal.querySelectorAll('input[value="cleantalk_force_ajax_check"]')) {
                        el.remove();
                    }
                    for (const el of formOriginal.querySelectorAll('input[name="ct_bot_detector_event_token"]')) {
                        el.remove();
                    }

                    // Common click event
                    let submButton = formOriginal.querySelectorAll('button[type=submit]');
                    if ( submButton.length !== 0 ) {
                        console.log('CleanTalk AJAX success 1:', submButton);
                        // submButton[0].click();
                        // if (isExternalFormsRestartRequired) {
                        //     setTimeout(function() {
                        //         ctProtectExternal();
                        //     }, 1500);
                        // }
                        return;
                    }

                    submButton = formOriginal.querySelectorAll('input[type=submit]');
                    if ( submButton.length !== 0 ) {
                        console.log('CleanTalk AJAX success 2:', submButton);
                        // submButton[0].click();
                        // if (isExternalFormsRestartRequired) {
                        //     setTimeout(function() {
                        //         ctProtectExternal();
                        //     }, 1500);
                        // }
                        return;
                    }
                }
                // if ((result.apbct !== undefined && +result.apbct.blocked) ||
                //     (result.data !== undefined && result.data.message !== undefined)
                // ) {
                //     new ApbctHandler().parseBlockMessage(result);
                // }
            },
            onfailure: function(result) {
                console.error('CleanTalk AJAX error:', result);
                // Allow form submission on error
                const formNew = form;
                form.parentElement.removeChild(form);
                const prev = form.apbctPrev;
                const formOriginal = form.apbctFormOriginal;
                apbctReplaceInputsValuesFromOtherForm(formNew, formOriginal);
                prev.after(formOriginal);
                
                for (const el of formOriginal.querySelectorAll('input[name="cleantalk_force_ajax_check"]')) {
                    el.remove();
                }
                for (const el of formOriginal.querySelectorAll('input[name="ct_bot_detector_event_token"]')) {
                    el.remove();
                }
                
                let submButton = formOriginal.querySelectorAll('button[type=submit]');
                if ( submButton.length !== 0 ) {
                    console.log('CleanTalk AJAX error 1:', submButton);
                    // submButton[0].click();
                    return;
                }
                submButton = formOriginal.querySelectorAll('input[type=submit]');
                if ( submButton.length !== 0 ) {
                    console.log('CleanTalk AJAX error 2:', submButton);
                    // submButton[0].click();
                }
            }
        });
    } else {
        // Fallback to native XMLHttpRequest if BX is not available
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/bitrix/components/cleantalk/ajax/ajax.php', false); // synchronous
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        const formData = new URLSearchParams();
        for (const key in data) {
            formData.append(key, data[key]);
        }
        
        xhr.send(formData);
        
        if (xhr.status === 200) {
            try {
                const result = JSON.parse(xhr.responseText);
                if ((result.apbct === undefined && result.data === undefined) ||
                    (result.apbct !== undefined && ! +result.apbct.blocked)
                ) {
                    // Allow form submission
                    const formNew = form;
                    form.parentElement.removeChild(form);
                    const prev = form.apbctPrev;
                    const formOriginal = form.apbctFormOriginal;
                    apbctReplaceInputsValuesFromOtherForm(formNew, formOriginal);
                    prev.after(formOriginal);
                    
                    for (const el of formOriginal.querySelectorAll('input[value="cleantalk_force_ajax_check"]')) {
                        el.remove();
                    }
                    
                    let submButton = formOriginal.querySelectorAll('button[type=submit]');
                    if ( submButton.length !== 0 ) {
                        console.log('CleanTalk AJAX success 1:', submButton);
                        // submButton[0].click();
                        return;
                    }
                    submButton = formOriginal.querySelectorAll('input[type=submit]');
                    if ( submButton.length !== 0 ) {
                        console.log('CleanTalk AJAX success 2:', submButton);
                        // submButton[0].click();
                    }
                } else if ((result.apbct !== undefined && +result.apbct.blocked) ||
                    (result.data !== undefined && result.data.message !== undefined)
                ) {
                    new ApbctHandler().parseBlockMessage(result);
                }
            } catch (e) {
                console.error('CleanTalk JSON parse error:', e);
                // Allow form submission on error
                const formNew = form;
                form.parentElement.removeChild(form);
                const prev = form.apbctPrev;
                const formOriginal = form.apbctFormOriginal;
                apbctReplaceInputsValuesFromOtherForm(formNew, formOriginal);
                prev.after(formOriginal);
                
                for (const el of formOriginal.querySelectorAll('input[value="cleantalk_force_ajax_check"]')) {
                    el.remove();
                }
                
                let submButton = formOriginal.querySelectorAll('button[type=submit]');
                if ( submButton.length !== 0 ) {
                    console.log('CleanTalk AJAX error 1:', submButton);
                    // submButton[0].click();
                    return;
                }
                submButton = formOriginal.querySelectorAll('input[type=submit]');
                if ( submButton.length !== 0 ) {
                    console.log('CleanTalk AJAX error 2:', submButton);
                    // submButton[0].click();
                }
            }
        } else {
            console.error('CleanTalk AJAX request failed:', xhr.status);
            // Allow form submission on error
            const formNew = form;
            form.parentElement.removeChild(form);
            const prev = form.apbctPrev;
            const formOriginal = form.apbctFormOriginal;
            apbctReplaceInputsValuesFromOtherForm(formNew, formOriginal);
            prev.after(formOriginal);
            
            for (const el of formOriginal.querySelectorAll('input[value="cleantalk_force_ajax_check"]')) {
                el.remove();
            }
            
            let submButton = formOriginal.querySelectorAll('button[type=submit]');
            if ( submButton.length !== 0 ) {
                console.log('CleanTalk AJAX success 1:', submButton);
                // submButton[0].click();
                return;
            }
            submButton = formOriginal.querySelectorAll('input[type=submit]');
            if ( submButton.length !== 0 ) {
                console.log('CleanTalk AJAX success 2:', submButton);
                // submButton[0].click();
            }
        }
    }
}
