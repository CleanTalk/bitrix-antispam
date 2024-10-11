<?php
/**
 * Settings of CleanTalk module
 *
 * @author  Cleantalk
 * @since   29/08/2013
 *
 * @link    http://cleantalk.org
 */

$sModuleId  = 'cleantalk.antispam';
CModule::IncludeModule( $sModuleId );
global $MESS;
IncludeModuleLangFile( __FILE__ );

use Bitrix\Main\Config\Option;
use Cleantalk\Common\API as CleantalkAPI;
use Cleantalk\Common\Helper as CleantalkHelper;

$cleantalk_is_wrong_regexp = false;
$cleantalk_is_wrong_url_regexp = false;
$is_account_exists = false;

$sites_from_bd = CSite::GetList("", "", Array("ACTIVE" => "Y"));
$sites = array();
$sub_tabs = array();
while( $site = $sites_from_bd->Fetch() ) {
    $site["ID"] = htmlspecialcharsbx($site["ID"]);
    $site["NAME"] = htmlspecialcharsbx($site["NAME"]);
    $sites[] = $site;
    $sub_tabs[] = array("DIV" => "opt_site_".$site["ID"], "TAB" => "(".$site["ID"].") ".$site["NAME"], 'TITLE' => '');
}

$subTabControl = new CAdminViewTabControl("subTabControl", $sub_tabs);

$current_options = ct_get_options($sModuleId);

if ( ! empty($REQUEST_METHOD) && $REQUEST_METHOD == 'POST' && $_POST['Update'] == 'Y' ) {
    //try to get default options
    $default_options = ct_get_default_options($sModuleId);

    if ( ! $default_options ){
        //if failed - get current instead
        $default_options = ct_get_options($sModuleId);
    }

    //reset options to defaults if reset in post
    if ( isset($_POST['reset']) ) {
        //try to reset options to defaults
        ct_reset_options($sModuleId);
        // if failed anyway it will be rewrote by current options
        $current_options = ct_get_options($sModuleId);
    } else {
        //save current key
        $old_key = Option::get($sModuleId,'key');

        //Getting key automatically
        if(isset($_POST['getautokey'])){

            $result = CleantalkAPI::method__get_api_key('antispam', COption::GetOptionString("main", "email_from"), $_SERVER["HTTP_HOST"], 'bitrix');

            if ( isset($result['account_exists']) && $result['account_exists'] == 1 ) {
                $is_account_exists = true;
            }

            if (empty($result['error'])){

                if(isset($result['user_token'])){
                    Option::set( $sModuleId, 'user_token', $result['user_token']);
                }

                if(isset($result['auth_key'])){
                    Option::set( $sModuleId, 'key', $result['auth_key']);
                    $new_key = $result['auth_key'];
                }
            }

        }else{
            $new_key = $_POST['key'];
        }

        // Send empty feedback for version comparison in Dashboard
        $new_key = isset( $new_key ) ? $new_key : $old_key;
        $result = CleantalkAPI::method__send_empty_feedback($new_key, CLEANTALK_USER_AGENT);

        /**
         * Set settings when submit
         */
        //Validating key
        if (CleantalkHelper::key_is_correct($new_key)) {
            $result = CleantalkAPI::method__notice_paid_till($new_key, preg_replace('/http[s]?:\/\//', '', $_SERVER['HTTP_HOST'], 1));

            Option::set($sModuleId, 'key_is_ok', (empty($result['error']) && isset($result['valid']) && $result['valid'] == 1) ? 1 : 0);
            Option::set($sModuleId, 'user_token', (empty($result['error']) && isset($result['user_token'])) ? $result['user_token'] : '');
            Option::set($sModuleId, 'moderate_ip', (empty($result['error']) && isset($result['moderate_ip']) && $result['moderate_ip'] == 1) ? 1 : 0);
            Option::set($sModuleId, 'ip_license', (empty($result['error']) && isset($result['moderate_ip'], $result['ip_license']) && $result['moderate_ip'] == 1) ? $result['ip_license'] : 0);

            if ( empty($result['error']) ) {
                if (isset($result['show_notice'], $result['trial']) && $result['show_notice'] == 1 && $result['trial'] == 1) {
                    CAdminNotify::Add(array(
                        'MESSAGE' => GetMessage( 'CLEANTALK_TRIAL_NOTIFY' ),
                        'TAG' => 'trial_notify',
                        'MODULE_ID' => 'main',
                        'ENABLE_CLOSE' => 'Y'));
                } else {
                    CAdminNotify::DeleteByTag('trial_notify');
                }
                if (isset($result['show_notice'], $result['renew']) && $result['show_notice'] == 1 && $result['renew'] == 1) {
                    CAdminNotify::Add(array(
                        'MESSAGE' => GetMessage( 'CLEANTALK_RENEW_NOTIFY' ),
                        'TAG' => 'renew_notify',
                        'MODULE_ID' => 'main',
                        'ENABLE_CLOSE' => 'Y'));
                } else {
                    CAdminNotify::DeleteByTag('renew_notify');
                }
            }
        } else {
            Option::set($sModuleId, 'key_is_ok', 0);
            Option::set($sModuleId, 'user_token','');
            Option::set($sModuleId, 'moderate_ip', 0);
            Option::set($sModuleId, 'ip_license', 0);
        }
        //set non-key options
        Option::set( $sModuleId, 'status',                          $_POST['status'] == '1'                          ? 1 : 0 );
        Option::set( $sModuleId, 'form_new_user',                   $_POST['form_new_user'] == '1'                   ? 1 : 0 );
        Option::set( $sModuleId, 'form_comment_blog',               $_POST['form_comment_blog'] == '1'               ? 1 : 0 );
        Option::set( $sModuleId, 'form_comment_forum',              $_POST['form_comment_forum'] == '1'              ? 1 : 0 );
        Option::set( $sModuleId, 'form_forum_private_messages',     $_POST['form_forum_private_messages'] == '1'     ? 1 : 0 );
        Option::set( $sModuleId, 'form_comment_treelike',           $_POST['form_comment_treelike'] == '1'           ? 1 : 0 );
        Option::set( $sModuleId, 'form_send_example',               $_POST['form_send_example'] == '1'               ? 1 : 0 );
        Option::set( $sModuleId, 'form_order',                      $_POST['form_order'] == '1'                      ? 1 : 0 );
        Option::set( $sModuleId, 'web_form',                        $_POST['web_form'] == '1'                        ? 1 : 0 );
        Option::set( $sModuleId, 'is_paid',                         $_POST['is_paid'] == '1'                         ? 1 : 0 );
        Option::set( $sModuleId, 'last_checked',                    $_POST['last_checked'] == '1'                    ? 1 : 0 );
        Option::set( $sModuleId, 'form_global_check',               $_POST['form_global_check'] == '1'               ? 1 : 0 );
        Option::set( $sModuleId, 'form_global_check_without_email', $_POST['form_global_check_without_email'] == '1' ? 1 : 0 );
        Option::set( $sModuleId, 'form_sfw',                        $_POST['form_sfw'] == '1'                        ? 1 : 0 );
        Option::set( $sModuleId, 'bot_detector',                    $_POST['bot_detector'] == '1'                        ? 1 : 0 );
        Option::set( $sModuleId, 'form_sfw_uniq_get_option',        $_POST['form_sfw_uniq_get_option'] == '1'        ? 1 : 0 );
        Option::set( $sModuleId, 'complete_deactivation',           $_POST['complete_deactivation'] == '1'           ? 1 : 0 );

        if (isset($_POST['form_exclusions_sites']) && is_array($_POST['form_exclusions_sites'])) {
            $exclusion_sites = array();
            foreach ($_POST['form_exclusions_sites'] as $value) {
                $exclusion_sites[] = $value;
            }
            Option::set( $sModuleId, 'site_exclusions', implode(',', $exclusion_sites));
        } else {
            Option::set( $sModuleId, 'site_exclusions', $default_options['site_exclusions']);
        }
        Option::set( $sModuleId, 'form_exclusions_url',             isset($_POST['form_exclusions_url'])     ? $_POST['form_exclusions_url']     : $default_options['form_exclusions_url'] );

        if (
            isset($_POST['form_exclusions_url'], $_POST['form_exclusions_url__regexp']) &&
            ! empty($_POST['form_exclusions_url']) &&
            ct_is_valid_regexp($_POST['form_exclusions_url'])
        ) {
            Option::set( $sModuleId, 'form_exclusions_url__regexp',     isset($_POST['form_exclusions_url__regexp'])  ? $_POST['form_exclusions_url__regexp']  : $default_options['form_exclusions_url__regexp'] );
        } else {
            if ( ! empty($_POST['form_exclusions_url']) && isset($_POST['form_exclusions_url__regexp']) ) {
                $cleantalk_is_wrong_url_regexp = true;
            }
            Option::set( $sModuleId, 'form_exclusions_url__regexp',     0 );
        }

        Option::set( $sModuleId, 'form_exclusions_fields',          isset($_POST['form_exclusions_fields'])  ? $_POST['form_exclusions_fields']  : $default_options['form_exclusions_fields'] );

        if (
            isset($_POST['form_exclusions_fields'], $_POST['form_exclusions_fields__regexp']) &&
            ! empty($_POST['form_exclusions_fields']) &&
            ct_is_valid_regexp($_POST['form_exclusions_fields'])
        ) {
            Option::set( $sModuleId, 'form_exclusions_fields__regexp',     isset($_POST['form_exclusions_fields__regexp'])  ? $_POST['form_exclusions_fields__regexp']  : $default_options['form_exclusions_fields__regexp'] );
        } else {
            if ( ! empty($_POST['form_exclusions_fields']) && isset($_POST['form_exclusions_fields__regexp']) ) {
                $cleantalk_is_wrong_regexp = true;
            }
            Option::set( $sModuleId, 'form_exclusions_fields__regexp',     0 );
        }
        Option::set( $sModuleId, 'form_exclusions_webform',         isset($_POST['form_exclusions_webform']) ? $_POST['form_exclusions_webform'] : $default_options['form_exclusions_webform'] );

        Option::set( $sModuleId, 'key', $new_key );

        // URL host
        Option::set( $sModuleId, 'host_url', ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? "https" : "http" ) . "://" . $_SERVER['HTTP_HOST'] );

        // SFW scheduled actions
        if($_POST['form_sfw'] == 1) {

            CAgent::RemoveModuleAgents( 'cleantalk.antispam' );
            CleantalkAntispam::apbct_sfw_update( $new_key );
            CleantalkAntispam::apbct_sfw_send_logs( $new_key );

            // Remove it if SFW is disabled
        } else {
            CAgent::RemoveModuleAgents("cleantalk.antispam");
        }
    }

    foreach( $sites as $site ) {
        $key = "key_" . $site["SITE_ID"];
        if ( isset($_POST[$key]) ) {
            if ( empty($_POST[$key]) ) {
                COption::RemoveOption($sModuleId, "_key", $site["SITE_ID"]);
            } else {
                // @ToDo add key_is_ok checking here and output error message
                COption::SetOptionString($sModuleId, "_key", $_POST[$key], false, $site["SITE_ID"]);
            }
        }
    }
}

function ct_is_valid_regexp($exclusion_string)
{
    if ( ! empty($exclusion_string) ) {
        $exclusions = explode(',', $exclusion_string);
        foreach ( $exclusions as $exclusion ) {
            if ( ! ct_is_regexp($exclusion) ) {
                return false;
            }
        }
    }
    return true;
}

/**
 * Checks if given string is valid regular expression
 *
 * @param string $regexp
 *
 * @return bool
 */
function ct_is_regexp($regexp)
{
    return @preg_match('/' . $regexp . '/', '') !== false;
}

/**
 * Reads option set for the cleantalk module.
 *
 * @param $sModuleId
 *
 * @return array|false
 */
function ct_get_default_options($sModuleId){
    try {
        return Option::getDefaults($sModuleId);
    } catch (\Bitrix\Main\ArgumentOutOfRangeException $ex) {
        CAdminNotify::Add(array(
            'MESSAGE' => GetMessage('CLEANTALK_WRONG_DEFAULT_SETTINGS'),
            'TAG' => 'def_options_failed',
            'MODULE_ID' => 'main',
            'ENABLE_CLOSE' => 'Y'));
    }
    return false;
}

/**
 * Reset cleantalk options to defaults. If some option reset fails throw admin notice
 * @param $sModuleId
 */
function ct_reset_options($sModuleId){
    $default_options = ct_get_default_options($sModuleId);
    if ( $default_options !== false ) {
        foreach ( $default_options as $setting => $value ) {
            try {
                Option::set($sModuleId, $setting, $value);
            } catch (\Bitrix\Main\ArgumentOutOfRangeException $ex) {
                CAdminNotify::Add(array(
                    'MESSAGE' => GetMessage( 'CLEANTALK_RESET_OPTIONS_FAILED' ),
                    'TAG' => 'current_options_failed',
                    'MODULE_ID' => 'main',
                    'ENABLE_CLOSE' => 'Y'));
            }
        }
    }
}


/**
 * Return current cleantalk options or false if exception.
 * @param $sModuleId
 * @return array|false
 */
function ct_get_options($sModuleId){
    try {
        $result =  Option::getForModule($sModuleId);
    } catch (\Bitrix\Main\ArgumentNullException $ex){
        CAdminNotify::Add(array(
            'MESSAGE' => GetMessage( 'CLEANTALK_WRONG_CURRENT_SETTINGS' ),
            'TAG' => 'cur_options_failed',
            'MODULE_ID' => 'main',
            'ENABLE_CLOSE' => 'Y'));
        return false;
    }
    return $result;
}

/**
 * Describe tabs
 */
$aTabs = array(
    array(
        'DIV'   => 'edit1',
        'TAB'   => GetMessage('MAIN_TAB_SET'),
        'ICON'  => 'fileman_settings',
        'TITLE' => GetMessage('MAIN_TAB_TITLE_SET' )
    ),
);

/**
 * Init tabs
 */
$oTabControl = new CAdmintabControl( 'tabControl', $aTabs );
$oTabControl->Begin();

/**
 * Settings form HTML
 */
?><form method="POST" enctype="multipart/form-data" action="<?php echo $APPLICATION->GetCurPage()?>?mid=<?=htmlspecialchars( $sModuleId )?>&lang=<?php echo LANG?>">
    <?=bitrix_sessid_post()?>
    <?php $oTabControl->BeginNextTab();?>
    <script>
        function ctDisableInputLine(ct_input_line){

            let ct_label = document.getElementById(ct_input_line+'_label');
            let ct_input = document.getElementById(ct_input_line);

            if(ct_input.hasAttribute('disabled')){
                ct_input.removeAttribute('disabled');
                ct_label.style.color = 'black';
            }else{
                ct_input.setAttribute('disabled', 'disabled');
                ct_input.checked = false;
                ct_label.style.color = 'gray';
            }
        }
    </script>
    <tr class="heading">
        <td colspan="2"><?=GetMessage( 'CLEANTALK_KEY' )?></td>
    </tr>
    <tr>
        <?php
        //Start options construct
        $current_options = ct_get_options($sModuleId);
        if ( $current_options['moderate_ip'] === '1' ){
            print '<td width="100%" valign="top" colspan="2">';
            print "The anti-spam service is paid by your hosting provider. License #".Option::get( $sModuleId, 'ip_license', 0 ).".";
            print '</td>';
        }else{
            $key_is_ok = $current_options['key_is_ok'];
            ?>
            <!--LABEL-->
            <td width="50%" valign="top"><label for="key"><?php echo GetMessage( 'CLEANTALK_LABEL_KEY' );?>:</td>
            <td  valign="top">
                <input type="text" name="key" id="key" value="<?php echo $current_options['key'] ?>" /> <span><?php
                    if ($key_is_ok === '0') {
                        echo "<span style='color: red'>".GetMessage( 'CLEANTALK_KEY_NOT_VALID' )."</span>";
                    }
                    ?></span>
                <!--HIDDEN FIELDSET-->
                <input type="hidden" name="is_paid" value="<?php echo $current_options['is_paid'] ?>" />
                <input type="hidden" name="last_checked" value="0" />
                <input type="hidden" name="moderate_ip" value="<?php echo $current_options['moderate_ip'] ?>" />
                <input type="hidden" name="ip_license" value="<?php echo $current_options['ip_license'] ?>" />
            </td>
            <?php
        }
        ?>
    </tr>
    <!--CHEK IF AUTOKEY-->
    <?php if ( $key_is_ok === '0' ){ ?>
        <tr>
            <td width="50%" valign="top">
                <a target="_blank" href="https://cleantalk.org/register?platform=bitrix&email=<?php echo Option::get("main", "email_from"); ?>&website=<?php echo $_SERVER["SERVER_NAME"]; ?>">
                    <input
                            type="button"
                            name="getmanualkey"
                            value="<?php echo GetMessage( 'CLEANTALK_GET_MANUAL_KEY' ) ?>" />
                </a>
            </td>
            <td  valign="top">
                <input
                        type="submit"
                        name="getautokey"
                        value="<?php echo GetMessage( 'CLEANTALK_GET_AUTO_KEY' ) ?>" />
            </td>
        </tr>
        <?php if ( $is_account_exists )  { ?>
        <tr>
            <td colspan='2' style='text-align: center; color: red;'><?php echo GetMessage( 'CLEANTALK_API_KEY_GETTING_WARNING' );?><br></td>
        </tr>
        <?php } ?>
        <tr>
            <td colspan='2' style='text-align: center;'><?php echo GetMessage( 'CLEANTALK_EMAIL_REGISTRATION_WARNING' )."(". Option::get("main", "email_from"); ?>).<br> <a target="_blank" href="https://cleantalk.org/publicoffer"><?php echo GetMessage( 'CLEANTALK_LICENSE_AGREEMENT' ); ?></a></td>
        </tr>
    <?php }else{ ?>
        <tr>
            <td width="50%"></td>
            <td valign="top">
                <a target="_blank" href="https://cleantalk.org/my?user_token=<?php echo $current_options['user_token']; ?>">
                    <input type="button" name="getmanualkey" value="<?php echo GetMessage( 'CLEANTALK_GET_TO_CP' ) ?>" />
                </a>
            </td>
        </tr>
    <?php } ?>


    <?php if ( count($sites) > 1 ) { ?>
    <!-- Multisite options -->
    <tr>
        <th colspan='2'><?php echo GetMessage('CLEANTALK_MULTISITE_TITLE') ?></th>
    </tr>
    <tr>
        <td colspan='2'>
            <?php
                $subTabControl->Begin();
                foreach ( $sites as $site )
                {
                    $subTabControl->BeginNextTab();
                    $api_key_subsite = Option::get($sModuleId, '_key', '', $site["SITE_ID"]);
                    ?>
                    <?= GetMessage( 'CLEANTALK_MULTISITE_LABEL_KEY' ) ?>
                    <input type="text" name="key_<?= $site["SITE_ID"] ?>" id="key_<?= $site["SITE_ID"] ?>" value="<?= $api_key_subsite ?>" />
            <?php }
                $subTabControl->End();
            ?>
        </td>
    </tr>
    <?php } ?>

    <tr class="heading">
        <td colspan="2">
            <?=GetMessage( 'CLEANTALK_TITLE' )?>
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top">
            <label for="status"><?php echo GetMessage( 'CLEANTALK_LABEL_STATUS' );?>:</td>
        <td valign="top">
            <input
                    type="checkbox"
                    name="status"
                    id="status"
                <?php if ( $current_options['status'] === '1' ):?> checked="checked" <?php endif; ?>value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top">
            <label for="form_new_user"><?php echo GetMessage( 'CLEANTALK_LABEL_NEW_USER' );?>:</td>
        <td  valign="top">
            <input
                    type="checkbox"
                    name="form_new_user"
                    id="form_new_user"
                <?php if ( $current_options['form_new_user'] === '1' ):?> checked="checked" <?php endif; ?>value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top">
            <label for="form_comment_blog"><?php echo GetMessage( 'CLEANTALK_LABEL_COMMENT_BLOG' );?>:</td>
        <td  valign="top">
            <input
                    type="checkbox"
                    name="form_comment_blog"
                    id="form_comment_blog"
                <?php if ( $current_options['form_comment_blog'] === '1' ):?> checked="checked" <?php endif; ?>value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top">
            <label for="form_comment_forum"><?php echo GetMessage( 'CLEANTALK_LABEL_COMMENT_FORUM' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_comment_forum" id="form_comment_forum"<?php if ( $current_options['form_comment_forum'] === '1'):?> checked="checked"<?php endif; ?>value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top">
            <label for="form_forum_private_messages"><?php echo GetMessage( 'CLEANTALK_LABEL_FORUM_PRIVATE_MESSAGES' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_forum_private_messages" id="form_forum_private_messages"<?php if ( $current_options['form_forum_private_messages'] === '1'):?> checked="checked"<?php endif; ?>value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top">
            <label for="form_comment_treelike"><?php echo GetMessage( 'CLEANTALK_LABEL_COMMENT_TREELIKE' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_comment_treelike" id="form_comment_treelike"<?php if ( $current_options['form_comment_treelike'] === '1'):?> checked="checked"<?php endif; ?>value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top">
            <label for="form_send_example"><?php echo GetMessage( 'CLEANTALK_LABEL_SEND_EXAMPLE' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_send_example" id="form_send_example"<?php if ( $current_options['form_send_example'] === '1'):?> checked="checked"<?php endif; ?>value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top">
            <label for="form_order"><?php echo GetMessage( 'CLEANTALK_LABEL_ORDER' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_order" id="form_order" <?php if ( $current_options['form_order'] === '1' ):?> checked="checked"<?php endif; ?>value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top">
            <label for="web_form"><?php echo GetMessage( 'CLEANTALK_LABEL_WEB_FORMS' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="web_form" id="web_form" <?php if ( $current_options['web_form'] === '1' ):?> checked="checked"<?php endif; ?>value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top">
            <label for="form_global_check"><?php echo GetMessage( 'CLEANTALK_LABEL_GLOBAL_CHECK' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_global_check" id="form_global_check" onclick="ctDisableInputLine('form_global_check_without_email');" <?php if ( $current_options['form_global_check'] === '1' ):?> checked="checked"<?php endif; ?>value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top">
            <label
                    id="form_global_check_without_email_label"
                    for="form_global_check_without_email"
                <?php
                if ( $current_options['form_global_check'] === '0' ){
                    echo ("style='color: gray;'");
                }
                ?>
            >
                <?php echo GetMessage( 'CLEANTALK_LABEL_GLOBAL_CHECK_WITHOUT_EMAIL' );?>:
        </td>
        <td  valign="top">
            <input
                    type="checkbox"
                    name="form_global_check_without_email"
                    id="form_global_check_without_email"
                <?php
                if( $current_options['form_global_check'] === '0' ) {
                    if ($current_options['form_global_check_without_email'] === '1') {
                        echo 'checked="checked"';
                    } else {
                        echo "disabled";
                    }
                }
                ?>
                    value="1" /> <?php echo GetMessage( 'CLEANTALK_WARNING_GLOBAL_CHECK_WITHOUT_EMAIL' ); ?>
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top">
            <label for="bot_detector"
                <?php
                if ( $current_options['bot_detector'] === '0' ){
                    echo ("style='color: gray;'");
                }
                ?>
            >
                <?php echo GetMessage( 'CLEANTALK_LABEL_BOT_DETECTOR' );?>:
        </td>
        <td  valign="top">
            <input
                    type="checkbox"
                    name="bot_detector"
                    id="bot_detector"
                <?php if ( $current_options['bot_detector'] === '1' ):?> checked="checked"<?php endif; ?>value="1" />
            <?php echo GetMessage( 'CLEANTALK_DESCRIPTION_BOT_DETECTOR' ); ?>
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top">
            <label for="form_sfw"><?php echo GetMessage( 'CLEANTALK_LABEL_SFW' );?>:</td>
        <td valign="top">
            <input
                    type="checkbox"
                    name="form_sfw"
                    id="form_sfw"
                <?php if ( $current_options['form_sfw'] === '1' ):?> checked="checked"<?php endif; ?>value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top">
            <label
                    id="form_sfw_uniq_get_option_label"
                    for="form_sfw_uniq_get_option"
                <?php
                if ($current_options['form_sfw'] === '0') {
                    echo ("style='color: gray;'");
                }
                ?>
            >
                <?php echo GetMessage( 'CLEANTALK_LABEL_UNIQ_GET_OPTION' );?>:
        </td>
        <td  valign="top">
            <input
                    type="checkbox"
                    name="form_sfw_uniq_get_option"
                    id="form_sfw_uniq_get_option"
                    <?php
                    if ( $current_options['form_sfw'] === '0' ){
                        echo "disabled";
                    }
                    ?>
                <?php if ( $current_options['form_sfw_uniq_get_option'] === '1' ):?> checked="checked"<?php endif; ?>value="1" />
                <?php echo GetMessage( 'CLEANTALK_LABEL_UNIQ_GET_OPTION_DESC' ); ?>
        </td>
    </tr>
    <tr class="heading">
        <td colspan="2">
            <?=GetMessage( 'CLEANTALK_EXCLUSIONS' )?>
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top">
            <label for="form_exclusions_webform"><?php echo GetMessage( 'CLEANTALK_EXCLUSIONS_SITES' );?>:</td>
        <td  valign="top">
            <select name="form_exclusions_sites[]" id="form_exclusions_sites" multiple>
                <?php $rsSites = CSite::GetList($by ="sort", $order="desc");
                $excluded_sites = explode(",", $current_options['site_exclusions']);
                while ($arSite = $rsSites->Fetch()) {
                    echo "<option value = \"".$arSite['ID']."\" " . (in_array($arSite['ID'], $excluded_sites) ? "selected" : "") . ">".$arSite['NAME']."</option>";
                } ?>
            </select>
            <div>
                <?php echo GetMessage( 'CLEANTALK_EXCLUSIONS_SITES_DESCRIPTION' ); ?>
            </div>
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top">
            <label for="form_exclusions_check"><?php echo GetMessage( 'CLEANTALK_EXCLUSIONS_URL' );?>:</td>
        <td  valign="top">
            <div class="ui-ctl ui-ctl-textarea">
                    <?php
                    echo ('<textarea class="ui-ctl-element" name="form_exclusions_url" id="form_exclusions_url" cols="45" rows="10">');
                    echo ($current_options['form_exclusions_url']);
                    echo ('</textarea>');
                    ?>
            </div>
            <input
                    type="checkbox"
                    name="form_exclusions_url__regexp"
                    id="form_exclusions_url__regexp"
                    value="1"
                <?php if ( $current_options['form_exclusions_url__regexp'] === '1' ): ?> checked="checked" <?php endif; ?> />
            <label for="form_exclusions_url__regexp">
                <?php echo GetMessage( 'CLEANTALK_EXCLUSIONS_URLS_REGEXP_DESCRIPTION' ); ?>
            </label>
            <?php if ( $cleantalk_is_wrong_url_regexp ) : ?>
                <div style="color:red"><?php echo GetMessage( 'CLEANTALK_WRONG_REGEXP_NOTIFY' ); ?></div>
            <?php endif; ?>
            <div style="padding: 10px 0 10px 0">
                <?php echo GetMessage( 'CLEANTALK_EXCLUSIONS_URL_DESCRIPTION' ); ?>
            </div>
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top">
            <label for="form_exclusions_fields"><?php echo GetMessage( 'CLEANTALK_EXCLUSIONS_FIELDS' );?>:</td>
        <td  valign="top">
            <div class="ui-ctl ui-ctl-textarea">
                <input type="text" name="form_exclusions_fields" id="form_exclusions_fields" value="<?php echo $current_options['form_exclusions_fields']; ?>" />
            </div>
            <input
                    type="checkbox"
                    name="form_exclusions_fields__regexp"
                    id="form_exclusions_fields__regexp"
                    value="1"
                <?php if ( $current_options['form_exclusions_fields__regexp'] === '1'): ?> checked="checked" <?php endif; ?> />
            <label for="form_exclusions_fields__regexp">
                <?php echo GetMessage( 'CLEANTALK_EXCLUSIONS_FIELDS_REGEXP_DESCRIPTION' ); ?>
            </label>
            <?php if ( $cleantalk_is_wrong_regexp ) : ?>
                <div style="color:red"><?php echo GetMessage( 'CLEANTALK_WRONG_REGEXP_NOTIFY' ); ?></div>
            <?php endif; ?>
            <div style="padding: 10px 0 10px 0">
                <?php echo GetMessage( 'CLEANTALK_EXCLUSIONS_FIELDS_DESCRIPTION' ); ?>
            </div>
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top">
            <label for="form_exclusions_webform"><?php echo GetMessage( 'CLEANTALK_EXCLUSIONS_WEBFORM' );?>:</td>
        <td  valign="top">
            <input
                    type="text"
                    name="form_exclusions_webform"
                    id="form_exclusions_webform"
                    value="<?php echo $current_options['form_exclusions_webform']; ?>" />
            <div style="padding: 10px 0 10px 0">
                <?php echo GetMessage( 'CLEANTALK_EXCLUSIONS_WEBFORM_DESCRIPTION' ); ?>
            </div>
        </td>
    </tr>
    <tr class="heading">
        <td colspan="2"><?=GetMessage( 'CLEANTALK_MISC' )?></td>
    </tr>
    <tr>
        <td width="50%" valign="top">
            <label for="complete_deactivation"><?php echo GetMessage( 'CLEANTALK_LABEL_COMPLETE_DEACTIVATION' ); ?>:</td>
        <td valign="top">
            <input
                    type="checkbox"
                    name="complete_deactivation"
                    id="complete_deactivation"
                <?php if ( $current_options['complete_deactivation'] === '1') :?> checked="checked"<?php endif; ?>
                    value="1" />
        </td>
    </tr>
    <!--HIDDEN FIELDSET-->
    <input type="hidden" name="is_paid" value="<?php echo $current_options['is_paid'] ?>" />
    <input type="hidden" name="last_checked" value="0" />
    <input type="hidden" name="moderate_ip" value="<?php echo $current_options['moderate_ip'] ?>" />
    <input type="hidden" name="ip_license" value="<?php echo $current_options['ip_license'] ?>" />
    <?php $oTabControl->Buttons(); ?>
    <input type="submit" name="Update" value="<?php echo GetMessage( 'CLEANTALK_BUTTON_SAVE' ) ?>" />
    <input type="submit" name="reset" value="<?php echo GetMessage( 'CLEANTALK_BUTTON_RESET' ) ?>" />
    <input type="hidden" name="Update" value="Y" />
    <?php $oTabControl->End();?>
    <script>
        let form_sfw_checkbox = document.getElementById('form_sfw');
        let form_sfw_uniq_get_option_checkbox = document.getElementById('form_sfw_uniq_get_option');
        let form_sfw_uniq_get_option_checkbox_label = document.getElementById('form_sfw_uniq_get_option_label');
        form_sfw_checkbox.addEventListener('change', (event) => {
            if (event.currentTarget.checked) {
                form_sfw_uniq_get_option_checkbox.disabled = false;
                form_sfw_uniq_get_option_checkbox.checked = true;
                form_sfw_uniq_get_option_checkbox_label.style.removeProperty('color');
            } else {
                form_sfw_uniq_get_option_checkbox.checked = false;
                form_sfw_uniq_get_option_checkbox.disabled = true;
                form_sfw_uniq_get_option_checkbox_label.style.color = 'gray';
            }
        });
    </script>
</form>
