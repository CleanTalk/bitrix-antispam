
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

function apbctParseBlockMessageForAajax(result) {
    let message = '';
    console.table('result',result)
    if (result.apbct && result.apbct.comment) {
        message = result.apbct.comment;
    } else if (result.error && result.error.msg) {
        message = result.error.msg;
    } else if (result.data && result.data.message) {
        message = result.data.message;
    }
    console.table('message',message)
    if (message) {
        alert(message);
        if (result.apbct && result.apbct.stop_script == 1) {
            window.stop();
        }
    }
}
/**
 * Sending Ajax for checking form data
 * @param {HTMLElement} form
 */
function sendAjaxCheckingFormData(form) {
    const botDetectorEventToken = getBotDetectorToken();
    const data = prepareFormData(form, botDetectorEventToken);

    if (typeof BX !== 'undefined' && BX.ajax) {
        sendBitrixAjax(form, data);
    } else {
        sendNativeAjax(form, data);
    }
}

/**
 * Get bot detector token from localStorage
 */
function getBotDetectorToken() {
    let token = localStorage.getItem('bot_detector_event_token');
    if (typeof token === 'string') {
        token = JSON.parse(token);
        if (typeof token === 'object') {
            return token.value;
        }
    }
    return token;
}

/**
 * Prepare form data for submission
 */
function prepareFormData(form, botDetectorToken) {
    const data = {
        'ct_bot_detector_event_token': botDetectorToken,
    };

    const elements = Array.from(form.elements);
    elements.forEach((elem, index) => {
        const key = elem.name || `input_${index}`;
        data[key] = elem.value;
    });

    return data;
}

/**
 * Send AJAX using Bitrix framework
 */
function sendBitrixAjax(form, data) {
    BX.ajax({
        url: '/bitrix/components/cleantalk/ajax/ajax.php',
        method: 'POST',
        data: data,
        dataType: 'json',
        async: false,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        onsuccess: (result) => handleSuccess(form, result),
        onfailure: (result) => handleFailure(form, result)
    });
}

/**
 * Send AJAX using native XMLHttpRequest
 */
function sendNativeAjax(form, data) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '/bitrix/components/cleantalk/ajax/ajax.php', false);
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
            handleSuccess(form, result);
        } catch (e) {
            console.error('CleanTalk JSON parse error:', e);
            restoreAndSubmitForm(form);
        }
    } else {
        console.error('CleanTalk AJAX request failed:', xhr.status);
        restoreAndSubmitForm(form);
    }
}

/**
 * Handle successful AJAX response
 */
function handleSuccess(form, result) {
    console.log('CleanTalk AJAX success:', result);

    const isAllowed = (result.apbct === undefined && result.data === undefined) ||
        (result.apbct !== undefined && !+result.apbct.blocked);

    const isBlocked = (result.apbct !== undefined && +result.apbct.blocked) ||
        (result.data !== undefined && result.data.message !== undefined);

    if (isAllowed) {
        restoreAndSubmitForm(form, true);
    } else if (isBlocked) {
        apbctParseBlockMessageForAajax(result);
    }
}

/**
 * Handle AJAX failure
 */
function handleFailure(form, result) {
    console.error('CleanTalk AJAX error:', result);
    restoreAndSubmitForm(form);
}

/**
 * Restore original form and trigger submission
 */
function restoreAndSubmitForm(form, isSuccess = false) {
    const formNew = form;
    const prev = form.apbctPrev;
    const formOriginal = form.apbctFormOriginal;

    // Remove current form and restore original
    form.parentElement.removeChild(form);
    apbctReplaceInputsValuesFromOtherForm(formNew, formOriginal);
    prev.after(formOriginal);

    // Clean up service fields
    removeServiceFields(formOriginal);

    // Find and trigger submit button
    const submitButton = findSubmitButton(formOriginal);
    if (submitButton) {
        console.log(`CleanTalk AJAX ${isSuccess ? 'success' : 'error'}:`, submitButton);
        submitButton.click();

        // Schedule external forms protection for successful cases
        if (isSuccess) {
            setTimeout(() => {
                ctProtectExternal();
            }, 1500);
        }
    }
}

/**
 * Remove service fields from form
 */
function removeServiceFields(form) {
    const selectors = [
        'input[value="cleantalk_force_ajax_check"]',
        'input[name="ct_bot_detector_event_token"]'
    ];

    selectors.forEach(selector => {
        form.querySelectorAll(selector).forEach(el => el.remove());
    });
}

/**
 * Find submit button in form
 */
function findSubmitButton(form) {
    const button = form.querySelector('button[type="submit"]');
    if (button) return button;

    const input = form.querySelector('input[type="submit"]');
    return input || null;
}
