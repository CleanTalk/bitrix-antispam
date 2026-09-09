/**
 * Returns FormData with cleantalk_check and ct_bot_detector_event_token
 * @param {HTMLFormElement} form
 * @returns {FormData}
 */
function getFormDataWithToken(form) {
    const formData = new FormData(form);
    formData.append('cleantalk_check', '1');
    let ctToken = '';
    const ctTokenInput = form.querySelector('[name="ct_bot_detector_event_token"]');
    if (ctTokenInput && ctTokenInput.value) {
        ctToken = ctTokenInput.value;
    } else {
        try {
            const lsToken = localStorage.getItem('bot_detector_event_token');
            if (lsToken) {
                const parsed = JSON.parse(lsToken);
                if (parsed && parsed.value) {
                    ctToken = parsed.value;
                }
            }
        } catch (e) {}
    }
    formData.set('ct_bot_detector_event_token', ctToken);
    return formData;
}

/**
 * Shows a full-page blocking message
 * @param {string} msg
 */
function cleantalkShowForbiddenMessage(msg) {
    let blockDiv = document.getElementById('cleantalk-forbidden-block');
    if (!blockDiv) {
        blockDiv = document.createElement('div');
        blockDiv.id = 'cleantalk-forbidden-block';
        blockDiv.style.position = 'fixed';
        blockDiv.style.top = '0';
        blockDiv.style.left = '0';
        blockDiv.style.width = '100vw';
        blockDiv.style.height = '100vh';
        blockDiv.style.background = 'rgba(0,0,0,0.7)';
        blockDiv.style.zIndex = '99999';
        blockDiv.style.display = 'flex';
        blockDiv.style.alignItems = 'center';
        blockDiv.style.justifyContent = 'center';
        blockDiv.style.color = '#fff';
        blockDiv.style.fontSize = '2em';
        blockDiv.innerHTML = '<div style="background:#222;padding:2em;border-radius:1em;max-width:90vw;text-align:center;">' + msg + '<br><br><button id="cleantalk-forbidden-close" style="margin-top:1em;padding:0.5em 2em;">OK</button></div>';
        document.body.appendChild(blockDiv);
        document.getElementById('cleantalk-forbidden-close').onclick = function() {
            blockDiv.remove();
            location.reload();
        };
    }
}

/**
 * Overrides fetch to intercept target form submissions
 */
(function() {
    // Save original fetch
    const defaultFetch = window.fetch;
    let fetchOverridden = false;

    // Form selectors for different integrations to identify target forms
    const FORM_SELECTORS = {
        'Bitrix24Integration': '.b24-form-content form',
    };

    // List of URL patterns for fetch interception
    const FETCH_URL_PATTERNS = [
        '/bitrix/'
    ];

    /**
     * Checks if fetch call is for target forms
     * @param {Array} args
     * @returns {boolean}
     */
    function isTargetFormFetch(args) {
        if (!args || !args[0]) return false;
        const url = typeof args[0] === 'string' ? args[0] : (args[0].url || '');
        return FETCH_URL_PATTERNS.some(pattern => url.includes(pattern));
    }

    /**
     * Gets the first found form and its integration class
     * @returns {{form: HTMLFormElement, integration: string}|null}
     */
    function getTargetFormAndIntegration() {
        for (const integration in FORM_SELECTORS) {
            const selector = FORM_SELECTORS[integration];
            const form = document.querySelector(selector);
            if (form) return { form, integration };
        }
        return null;
    }

    /**
     * Overrides fetch for target forms
     * @returns {void}
     */
    function overrideFetchForTargetForms() {
        if (fetchOverridden) return;
        fetchOverridden = true;
        window.fetch = async function(...args) {
            if (isTargetFormFetch(args)) {
                const found = getTargetFormAndIntegration();
                if (found && found.form && found.integration) {
                    const formData = getFormDataWithToken(found.form);
                    formData.append('integration', found.integration);
                    let blocked = false;
                    let blockMsg = '';
                    try {
                        const checkResult = await defaultFetch('/bitrix/tools/cleantalk.antispam/ajax_handler.php', {
                            method: 'POST',
                            body: formData,
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const json = await checkResult.json();
                        if (json && json.apbct && +json.apbct.blocked) {
                            blocked = true;
                            blockMsg = json.apbct.comment || 'Your submission has been blocked as Cleantalk antispam.';
                        }
                    } catch (err) {
                        blocked = true;
                        blockMsg = 'Error during spam check. Please try again later.';
                    }
                    if (blocked) {
                        cleantalkShowForbiddenMessage(blockMsg);
                        return new Promise(() => {});
                    }
                }
            }
            return defaultFetch.apply(window, args);
        };
    }

    // Override fetch only after the page is fully loaded
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        overrideFetchForTargetForms();
    } else {
        document.addEventListener('DOMContentLoaded', overrideFetchForTargetForms);
    }

    // MutationObserver for dynamically added forms
    const observer = new MutationObserver((mutationsList) => {
        for (const mutation of mutationsList) {
            if (mutation.type === 'childList') {
                for (const node of mutation.addedNodes) {
                    if (node.nodeType === 1) {
                        for (const integration in FORM_SELECTORS) {
                            const selector = FORM_SELECTORS[integration];
                            if (
                                (node.matches && node.matches(selector)) ||
                                (node.querySelector && node.querySelector(selector))
                            ) {
                                overrideFetchForTargetForms();
                                break;
                            }
                        }
                    }
                }
            }
        }
    });
    if (document.body) {
        observer.observe(document.body, { childList: true, subtree: true });
    } else {
        document.addEventListener('DOMContentLoaded', function() {
            observer.observe(document.body, { childList: true, subtree: true });
        });
    }
})();
