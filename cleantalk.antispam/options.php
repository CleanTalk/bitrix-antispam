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

use Cleantalk\Common\API as CleantalkAPI;
use Cleantalk\Common\Helper as CleantalkHelper;

if( $REQUEST_METHOD == 'POST' && $_POST['Update'] == 'Y' ) {
    
    $old_key = COption::GetOptionString( $sModuleId, 'key', '' );
    
    
    //Getting key automatically
    if(isset($_POST['getautokey'])){
        
        $result = CleantalkAPI::method__get_api_key('antispam', COption::GetOptionString("main", "email_from"), $_SERVER["HTTP_HOST"], 'bitrix');
        
        if (empty($result['error'])){
        
            if(isset($result['user_token'])){
                COption::SetOptionString( $sModuleId, 'user_token', $result['user_token']);
            }
            
            if(isset($result['auth_key'])){
                COption::SetOptionString( $sModuleId, 'key', $result['auth_key']);
                $new_key = $result['auth_key'];
            }
        }
        
    }else{
        $new_key = $_POST['key'];
    }
    
    // Send empty feedback for version comparison in Dashboard
    $result = CleantalkAPI::method__send_empty_feedback($new_key, CLEANTALK_USER_AGENT);
    
    /**
     * Set settings when submit
     */
    //Validating key
    if (CleantalkHelper::api_key__is_correct($new_key)) {
        $result = CleantalkAPI::method__notice_paid_till($new_key, preg_replace('/http[s]?:\/\//', '', $_SERVER['HTTP_HOST'], 1));

        COption::SetOptionInt($sModuleId, 'key_is_ok', (empty($result['error']) && isset($result['valid']) && $result['valid'] == 1) ? 1 : 0);
        COption::SetOptionString($sModuleId, 'user_token', (empty($result['error']) && isset($result['user_token'])) ? $result['user_token'] : '');           
        COption::SetOptionInt($sModuleId, 'moderate_ip', (empty($result['error']) && isset($result['moderate_ip']) && $result['moderate_ip'] == 1) ? 1 : 0);
        COption::SetOptionInt($sModuleId, 'ip_license', (empty($result['error']) && isset($result['moderate_ip'], $result['ip_license']) && $result['moderate_ip'] == 1) ? $result['ip_license'] : 0);  

        if (empty($result['error'])) {
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
        COption::SetOptionInt($sModuleId, 'key_is_ok', 0);
        COption::SetOptionString($sModuleId, 'user_token','');           
        COption::SetOptionInt($sModuleId, 'moderate_ip', 0);
        COption::SetOptionInt($sModuleId, 'ip_license', 0);        
    }


    COption::SetOptionInt( $sModuleId, 'status',                          $_POST['status'] == '1'                          ? 1 : 0 );
    COption::SetOptionInt( $sModuleId, 'form_new_user',                   $_POST['form_new_user'] == '1'                   ? 1 : 0 );
    COption::SetOptionInt( $sModuleId, 'form_comment_blog',               $_POST['form_comment_blog'] == '1'               ? 1 : 0 );
    COption::SetOptionInt( $sModuleId, 'form_comment_forum',              $_POST['form_comment_forum'] == '1'              ? 1 : 0 );
    COption::SetOptionInt( $sModuleId, 'form_forum_private_messages',     $_POST['form_forum_private_messages'] == '1'     ? 1 : 0 );
    COption::SetOptionInt( $sModuleId, 'form_comment_treelike',           $_POST['form_comment_treelike'] == '1'           ? 1 : 0 );
    COption::SetOptionInt( $sModuleId, 'form_send_example',               $_POST['form_send_example'] == '1'               ? 1 : 0 );
    COption::SetOptionInt( $sModuleId, 'form_order',                      $_POST['form_order'] == '1'                      ? 1 : 0 );
    COption::SetOptionInt( $sModuleId, 'web_form',                        $_POST['web_form'] == '1'                        ? 1 : 0 );
    COption::SetOptionInt( $sModuleId, 'is_paid',                         $_POST['is_paid'] == '1'                         ? 1 : 0 );
    COption::SetOptionInt( $sModuleId, 'last_checked',                    $_POST['last_checked'] == '1'                    ? 1 : 0 );
    COption::SetOptionInt( $sModuleId, 'form_global_check',               $_POST['form_global_check'] == '1'               ? 1 : 0 );
    COption::SetOptionInt( $sModuleId, 'form_global_check_without_email', $_POST['form_global_check_without_email'] == '1' ? 1 : 0 );
    COption::SetOptionInt( $sModuleId, 'form_sfw',                        $_POST['form_sfw'] == '1'                        ? 1 : 0 );

    COption::SetOptionString( $sModuleId, 'form_exclusions_url',             isset($_POST['form_exclusions_url'])     ? $_POST['form_exclusions_url']     : '' );
    COption::SetOptionString( $sModuleId, 'form_exclusions_fields',          isset($_POST['form_exclusions_fields'])  ? $_POST['form_exclusions_fields']  : '' );
    COption::SetOptionString( $sModuleId, 'form_exclusions_webform',         isset($_POST['form_exclusions_webform']) ? $_POST['form_exclusions_webform'] : '' );

    COption::SetOptionString( $sModuleId, 'key', $new_key );
    
    // URL host
    COption::SetOptionString( $sModuleId, 'host_url', ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? "https" : "http" ) . "://" . $_SERVER['HTTP_HOST'] );
    
    // SFW scheduled actions
    if($_POST['form_sfw'] == 1) {
     
	    CAgent::RemoveModuleAgents( 'cleantalk.antispam' );
        CAgent::AddAgent(
            'CleantalkAntispam::sfw_send_logs();',
            'cleantalk.antispam',
            'N',
            3600,
            date( 'd.m.Y H:i:s' ),
            'Y',
            date( 'd.m.Y H:i:s' , time() + 3600)
        );
        CAgent::AddAgent(
            'CleantalkAntispam::sfw_update__agent();',
            'cleantalk.antispam',
            'N',
            86400,
            date( 'd.m.Y H:i:s' ),
            'Y',
            date( 'd.m.Y H:i:s' , time() + 86400)
        );
	    
	    CleantalkAntispam::sfw_update( $new_key );
	    CleantalkAntispam::sfw_send_logs( $new_key );
        
    // Remove it if SFW is disabled
    }else
	    CAgent::RemoveModuleAgents("cleantalk.antispam");
    
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
 * Settings form
 */
?><form method="POST" enctype="multipart/form-data" action="<?echo $APPLICATION->GetCurPage()?>?mid=<?=htmlspecialchars( $sModuleId )?>&lang=<?echo LANG?>">
    <?=bitrix_sessid_post()?>
    <?$oTabControl->BeginNextTab();?>
    <script>        
        function ctDdisableInputLine(ct_input_line){
                        
            ct_label = document.getElementById(ct_input_line+'_label');
            ct_input = document.getElementById(ct_input_line);
            
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
        <td colspan="2"><?=GetMessage( 'CLEANTALK_TITLE' )?></td>
    </tr>
    <tr>
        <td width="50%" valign="top"><label for="status"><?echo GetMessage( 'CLEANTALK_LABEL_STATUS' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="status" id="status"<? if ( COption::GetOptionInt( $sModuleId, 'status', 0 ) == 1):?> checked="checked"<? endif; ?> value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top"><label for="form_new_user"><?echo GetMessage( 'CLEANTALK_LABEL_NEW_USER' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_new_user" id="form_new_user"<? if ( COption::GetOptionInt( $sModuleId, 'form_new_user', 0 ) == 1):?> checked="checked"<? endif; ?> value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top"><label for="form_comment_blog"><?echo GetMessage( 'CLEANTALK_LABEL_COMMENT_BLOG' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_comment_blog" id="form_comment_blog"<? if ( COption::GetOptionInt( $sModuleId, 'form_comment_blog', 0 ) == 1):?> checked="checked"<? endif; ?> value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top"><label for="form_comment_forum"><?echo GetMessage( 'CLEANTALK_LABEL_COMMENT_FORUM' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_comment_forum" id="form_comment_forum"<? if ( COption::GetOptionInt( $sModuleId, 'form_comment_forum', 0 ) == 1):?> checked="checked"<? endif; ?> value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top"><label for="form_forum_private_messages"><?echo GetMessage( 'CLEANTALK_LABEL_FORUM_PRIVATE_MESSAGES' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_forum_private_messages" id="form_forum_private_messages"<? if ( COption::GetOptionInt( $sModuleId, 'form_forum_private_messages', 0 ) == 1):?> checked="checked"<? endif; ?> value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top"><label for="form_comment_treelike"><?echo GetMessage( 'CLEANTALK_LABEL_COMMENT_TREELIKE' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_comment_treelike" id="form_comment_treelike"<? if ( COption::GetOptionInt( $sModuleId, 'form_comment_treelike', 0 ) == 1):?> checked="checked"<? endif; ?> value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top"><label for="form_send_example"><?echo GetMessage( 'CLEANTALK_LABEL_SEND_EXAMPLE' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_send_example" id="form_send_example"<? if ( COption::GetOptionInt( $sModuleId, 'form_send_example', 0 ) == 1):?> checked="checked"<? endif; ?> value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top"><label for="form_order"><?echo GetMessage( 'CLEANTALK_LABEL_ORDER' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_order" id="form_order" <? if ( COption::GetOptionInt( $sModuleId, 'form_order', 0 ) == 1):?> checked="checked"<? endif; ?> value="1" />
        </td>
    </tr>
     <tr>
        <td width="50%" valign="top"><label for="web_form"><?echo GetMessage( 'CLEANTALK_LABEL_WEB_FORMS' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="web_form" id="web_form" <? if ( COption::GetOptionInt( $sModuleId, 'web_form', 0 ) == 1):?> checked="checked"<? endif; ?> value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top"><label for="form_global_check"><?echo GetMessage( 'CLEANTALK_LABEL_GLOBAL_CHECK' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_global_check" id="form_global_check"  onclick="ctDdisableInputLine('form_global_check_without_email');" <? if ( COption::GetOptionInt( $sModuleId, 'form_global_check', 0 ) == 1):?> checked="checked"<? endif; ?> value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top">
            <label
                    id="form_global_check_without_email_label"
                    for="form_global_check_without_email"
                    <?if( COption::GetOptionInt( $sModuleId, 'form_global_check', 0 ) == 0){  "style='color: gray;'"; } ?>
            >
            <?echo GetMessage( 'CLEANTALK_LABEL_GLOBAL_CHECK_WITHOUT_EMAIL' );?>:
        </td>
        <td  valign="top">
            <input type="checkbox" name="form_global_check_without_email" id="form_global_check_without_email"
            <? 
                if( COption::GetOptionInt( $sModuleId, 'form_global_check', 0 ) == 1){
                    if( COption::GetOptionInt( $sModuleId, 'form_global_check_without_email', 0 ) == 1)
                        echo 'checked="checked"';
                }else{
                    echo "disabled";
                }
            ?> value="1" /> <?php echo GetMessage( 'CLEANTALK_WARNING_GLOBAL_CHECK_WITHOUT_EMAIL' ); ?>
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top"><label for="form_global_check"><?echo GetMessage( 'CLEANTALK_LABEL_SFW' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_sfw" id="form_sfw" <? if ( COption::GetOptionInt( $sModuleId, 'form_sfw', 0 ) == 1):?> checked="checked"<? endif; ?> value="1" />
        </td>
    </tr>
    <tr class="heading">
        <td colspan="2"><?=GetMessage( 'CLEANTALK_EXCLUSIONS' )?></td>
    </tr>
    <tr>
        <td width="50%" valign="top"><label for="form_exclusions_check"><?echo GetMessage( 'CLEANTALK_EXCLUSIONS_URL' );?>:</td>
        <td  valign="top">
            <input type="text" name="form_exclusions_url" id="form_exclusions_url" value="<?php echo COption::GetOptionString( $sModuleId, 'form_exclusions_url', '' ); ?>" />
            <div><?php echo GetMessage( 'CLEANTALK_EXCLUSIONS_URL_DESCRIPTION' ); ?></div>
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top"><label for="form_exclusions_fields"><?echo GetMessage( 'CLEANTALK_EXCLUSIONS_FIELDS' );?>:</td>
        <td  valign="top">
            <input type="text" name="form_exclusions_fields" id="form_exclusions_fields" value="<?php echo COption::GetOptionString( $sModuleId, 'form_exclusions_fields', '' ); ?>" />
            <div><?php echo GetMessage( 'CLEANTALK_EXCLUSIONS_FIELDS_DESCRIPTION' ); ?></div>
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top"><label for="form_exclusions_webform"><?php echo GetMessage( 'CLEANTALK_EXCLUSIONS_WEBFORM' );?>:</td>
        <td  valign="top">
            <input type="text" name="form_exclusions_webform" id="form_exclusions_webform" value="<?php echo COption::GetOptionString( $sModuleId, 'form_exclusions_webform', '' ); ?>" />
            <div><?php echo GetMessage( 'CLEANTALK_EXCLUSIONS_WEBFORM_DESCRIPTION' ); ?></div>
        </td>
    </tr>
    <tr class="heading">
        <td colspan="2"><?=GetMessage( 'CLEANTALK_KEY' )?></td>
    </tr>
    <tr>
        <?php
            $moderate_ip=COption::GetOptionInt( $sModuleId, 'moderate_ip', 0 );

            if($moderate_ip == 1){
                print '<td width="100%" valign="top" colspan="2">';
                print "The anti-spam service is paid by your hosting provider. License #".COption::GetOptionInt( $sModuleId, 'ip_license', 0 ).".";
                print '</td>';
            }else{
                $key_is_ok = COption::GetOptionInt( $sModuleId, 'key_is_ok', 0);
        ?>
        <td width="50%" valign="top"><label for="key"><?echo GetMessage( 'CLEANTALK_LABEL_KEY' );?>:</td>
        <td  valign="top">
            <input type="text" name="key" id="key" value="<?php echo COption::GetOptionString( $sModuleId, 'key', '' ) ?>" /> <span><?php 
                if ($key_is_ok == 0) {
                    echo "<span style='color: red'>".GetMessage( 'CLEANTALK_KEY_NOT_VALID' )."</span>";
                }
                ?></span>
            <input type="hidden" name="is_paid" value="<?php echo COption::GetOptionInt( $sModuleId, 'is_paid', 0 ) ?>" />
            <input type="hidden" name="last_checked" value="0" />
            <input type="hidden" name="moderate_ip" value="<?php echo COption::GetOptionInt( $sModuleId, 'moderate_ip', 0 ) ?>" />
            <input type="hidden" name="ip_license" value="<?php echo COption::GetOptionInt( $sModuleId, 'ip_license', 0 ) ?>" />
        </td>
        <?php
            }
        ?>
    </tr>
    <?php if($key_is_ok == 0){ ?>
    <tr>
        <td width="50%" valign="top">
            <a target="_blank" href="https://cleantalk.org/register?platform=bitrix&email=<?php echo COption::GetOptionString("main", "email_from"); ?>&website=<?php echo $_SERVER["SERVER_NAME"]; ?>">
                <input type="button" name="getmanualkey" value="<?php echo GetMessage( 'CLEANTALK_GET_MANUAL_KEY' ) ?>" />
            </a>
        </td>
        <td  valign="top">
            <input type="submit" name="getautokey" value="<?php echo GetMessage( 'CLEANTALK_GET_AUTO_KEY' ) ?>" />
        </td>
    </tr>
    <tr>
        <td colspan='2' style='text-align: center;'><?php echo GetMessage( 'CLEANTALK_EMAIL_REGISTRATION_WARNING' )."(". COption::GetOptionString("main", "email_from"); ?>).<br> <a target="_blank" href="https://cleantalk.org/publicoffer"><?php echo GetMessage( 'CLEANTALK_LICENSE_AGREEMENT' ); ?></a></td>
    </tr>
    <?php }else{ ?>
    <tr>
        <td width="50%"></td>
        <td valign="top">
            <a target="_blank" href="https://cleantalk.org/my?user_token=<?php echo COption::GetOptionString( $sModuleId, "user_token"); ?>">
                <input type="button" name="getmanualkey" value="<?php echo GetMessage( 'CLEANTALK_GET_TO_CP' ) ?>" />
            </a>
        </td>
    </tr>
    <?php 
        } 
        $oTabControl->Buttons();
    ?>
    <input type="submit" name="Update" value="<?php echo GetMessage( 'CLEANTALK_BUTTON_SAVE' ) ?>" />
    <input type="reset" name="reset" value="<?php echo GetMessage( 'CLEANTALK_BUTTON_RESET' ) ?>" />
    <input type="hidden" name="Update" value="Y" />
    <?php $oTabControl->End();?>
</form>
