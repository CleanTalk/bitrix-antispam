<?php
$MESS['CLEANTALK_LABEL_STATUS'] = 'Module is enabled';
$MESS['CLEANTALK_LABEL_KEY']    = 'Access key';
$MESS['CLEANTALK_LABEL_NEW_USER']       = 'Registration form protection';
$MESS['CLEANTALK_LABEL_COMMENT_BLOG']   = 'Blog comment form protection';
$MESS['CLEANTALK_LABEL_COMMENT_FORUM']  = 'Forum comment form protection';
$MESS['CLEANTALK_LABEL_FORUM_PRIVATE_MESSAGES']  = 'Forum private messages protection';
$MESS['CLEANTALK_LABEL_COMMENT_TREELIKE']  = 'Treelike comments forms protection';
$MESS['CLEANTALK_LABEL_SEND_EXAMPLE']  = 'Send texts for off-top analysis';
$MESS['CLEANTALK_LABEL_ORDER'] = 'Order form protection';
$MESS['CLEANTALK_LABEL_WEB_FORMS'] = 'Web forms protection';
$MESS['CLEANTALK_BUTTON_SAVE']  = 'Save';
$MESS['CLEANTALK_GET_AUTO_KEY']  = 'Get access key automatically';
$MESS['CLEANTALK_GET_MANUAL_KEY']  = 'Get access key manually';
$MESS['CLEANTALK_GET_TO_CP']  = 'Get to the CleanTalk dashboard';
$MESS['CLEANTALK_BUTTON_RESET'] = 'Reset';
$MESS['CLEANTALK_TITLE']        = 'Module setting';
$MESS['CLEANTALK_LABEL_GLOBAL_CHECK']        = 'Any forms protection';
$MESS['CLEANTALK_LABEL_GLOBAL_CHECK_WITHOUT_EMAIL'] = 'Check all POST data';
$MESS['CLEANTALK_WARNING_GLOBAL_CHECK_WITHOUT_EMAIL'] = '- Warning, conflict possibility!';
$MESS['CLEANTALK_LABEL_BOT_DETECTOR'] = 'Use Anti-Spam by CleanTalk JavaScript library';
$MESS['CLEANTALK_DESCRIPTION_BOT_DETECTOR'] = 'This option includes external Anti-Spam by CleanTalk JavaScript library to getting visitors info data';
$MESS['CLEANTALK_LABEL_SFW']        = 'Spam FireWall';
$MESS['CLEANTALK_LABEL_UNIQ_GET_OPTION']        = 'Uniq GET option';
$MESS['CLEANTALK_LABEL_UNIQ_GET_OPTION_DESC']        = 'If a visitor gets the SpamFireWall page, the plugin will put a unique GET variable in the URL to avoid issues with caching plugins.';
$MESS['CLEANTALK_LABEL_NOTIFY']        = "Like Anti-spam by CleanTalk? Help others learn about CleanTalk! <a  target='_blank' href='http://marketplace.1c-bitrix.ru/solutions/cleantalk.antispam/#rating'>Leave a review at the Bitrix.Marketplace</a>";
$MESS['CLEANTALK_ENTER_KEY']        = 'Enter the access key';
$MESS['CLEANTALK_KEY_VALID']        = 'Key is valid';
$MESS['CLEANTALK_KEY_NOT_VALID']    = 'Key is not valid';
$MESS['CLEANTALK_EMAIL_REGISTRATION_WARNING']	= "Administrator's e-mail will be used for registration";
$MESS['CLEANTALK_API_KEY_GETTING_WARNING']	= sprintf(
    'Please, get the Access Key from %s CleanTalk Control Panel %s and insert it in the Access Key field',
    '<a href="https://cleantalk.org/my/?cp_mode=antispam" target="_blank">',
    '</a>'
);
$MESS['CLEANTALK_LICENSE_AGREEMENT'] = 'License agreement';
$MESS['CLEANTALK_KEY']                           = 'Access key';
$MESS['CLEANTALK_EXCLUSIONS']                    = 'Exclusions';
$MESS['CLEANTALK_EXCLUSIONS_URL']                = 'URL exclusions';
$MESS['CLEANTALK_EXCLUSIONS_URL_DESCRIPTION']    = 'Exclude urls from spam check. List them separated by commas.';
$MESS['CLEANTALK_EXCLUSIONS_URLS_REGEXP_DESCRIPTION'] = 'Use Regular Expression in URLs Exclusions';
$MESS['CLEANTALK_EXCLUSIONS_FIELDS']             = 'Fields exclusions';
$MESS['CLEANTALK_EXCLUSIONS_FIELDS_DESCRIPTION'] = 'Exclude fields from spam check. List them separated by commas. Works on forms except for registration and comment forms.';
$MESS['CLEANTALK_EXCLUSIONS_FIELDS_REGEXP_DESCRIPTION'] = 'Use Regular Expression in Field Exclusions';
$MESS['CLEANTALK_EXCLUSIONS_WEBFORM']            = 'Web-form ID exclusion';
$MESS['CLEANTALK_EXCLUSIONS_WEBFORM_DESCRIPTION']= 'Exclude forms (Web-forms module) by provided IDs. List them separated by commas.';
$MESS['CLEANTALK_EXCLUSIONS_SITES'] = 'Sites exclusions';
$MESS['CLEANTALK_EXCLUSIONS_SITES_DESCRIPTION'] = 'Exclude sites from spam checking. Hold CTRL to select multiple sites.';
$MESS['CLEANTALK_TRIAL_NOTIFY']= "<b>Anti-spam by CleanTalk</b> trial period ends, please, upgrade to <a href='https://cleantalk.org/my/bill/recharge?utm_source=bitrix-backend&utm_medium=cpc&utm_campaign=bitrix-backend-trial&user_token=".COption::GetOptionString('cleantalk.antispam', 'user_token', '')."&cp_mode=antispam' target='_blank'><b>premium version.</b></a>.";
$MESS['CLEANTALK_RENEW_NOTIFY']= "Please, <a href='https://cleantalk.org/my/bill/recharge?utm_source=bitrix-backend&utm_medium=cpc&utm_campaign=bitrix-backend-renew&user_token=".COption::GetOptionString('cleantalk.antispam', 'user_token', '')."&cp_mode=antispam' target='_blank'><b>renew</b></a> your anti-spam license for <b>Anti-spam by CleanTalk</b>!";
$MESS['CLEANTALK_MISC'] = 'Miscellaneous';
$MESS['CLEANTALK_LABEL_COMPLETE_DEACTIVATION'] = 'Complete deactivation';
$MESS['CLEANTALK_WRONG_REGEXP_NOTIFY'] = 'Field Exclusions is not a valid regular expression.';
$MESS['CLEANTALK_WRONG_DEFAULT_SETTINGS'] = 'Can not load default options. Module name is incorrect.';
$MESS['CLEANTALK_WRONG_CURRENT_SETTINGS'] = 'Can not load current options.';
$MESS['CLEANTALK_RESET_OPTIONS_FAILED'] = 'Can not reset options to defaults.';
$MESS['CLEANTALK_MULTISITE_LABEL_KEY'] = 'If you want to use specific Access Key for this website paste it here. Otherwise, leave it empty.';