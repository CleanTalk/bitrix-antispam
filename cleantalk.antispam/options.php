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

if( $REQUEST_METHOD == 'POST' && $_POST['Update'] == 'Y' ) {
    
    $old_key = COption::GetOptionString( $sModuleId, 'key', '' );
    
    
    //Getting key automatically
    if(isset($_POST['getautokey'])){
        
        $result = CleantalkHelper::api_method__get_api_key(COption::GetOptionString("main", "email_from"), $_SERVER["SERVER_NAME"], 'bitrix');
        
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
    $result = CleantalkHelper::api_method_send_empty_feedback($new_key, 'bitrix-3108');
    
    /**
     * Set settings when submit
     */
    //Validating key
    $result = CleantalkHelper::api_method__notice_validate_key($new_key);
    if(empty($result['error'])){
        COption::SetOptionString( $sModuleId, 'key_is_ok', strval($result['valid']));
    }
    
    COption::SetOptionString( $sModuleId, 'status',                          $_POST['status'] == '1'                          ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'form_new_user',                   $_POST['form_new_user'] == '1'                   ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'form_comment_blog',               $_POST['form_comment_blog'] == '1'               ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'form_comment_forum',              $_POST['form_comment_forum'] == '1'              ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'form_forum_private_messages',     $_POST['form_forum_private_messages'] == '1'     ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'form_comment_treelike',           $_POST['form_comment_treelike'] == '1'           ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'form_send_example',               $_POST['form_send_example'] == '1'               ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'form_order',                      $_POST['form_order'] == '1'                      ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'web_form',                        $_POST['web_form'] == '1'                        ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'is_paid',                         $_POST['is_paid'] == '1'                         ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'last_checked',                    $_POST['last_checked'] == '1'                    ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'form_global_check',               $_POST['form_global_check'] == '1'               ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'form_global_check_without_email', $_POST['form_global_check_without_email'] == '1' ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'form_sfw',                        $_POST['form_sfw'] == '1'                        ? '1' : '0' );  
    COption::SetOptionString( $sModuleId, 'ip_license',                      $_POST['ip_license'] == '1'                      ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'moderate_ip',                     $_POST['moderate_ip'] == '1'                     ? '1' : '0' );
    
    COption::SetOptionString( $sModuleId, 'key', $new_key );     
    if($_POST['form_sfw'] == 1)
    {
        CAgent::RemoveModuleAgents("cleantalk.antispam");
        CAgent::AddAgent("CleantalkAntispam::sfw_send_logs();", "cleantalk.antispam", "N", 3600);
        CAgent::AddAgent("CleantalkAntispam::sfw_update();",    "cleantalk.antispam", "N", 86400);
        $sfw = new CleantalkSFW();
        $sfw->sfw_update($new_key);
        $sfw->send_logs($new_key);
    }else{
        CAgent::RemoveModuleAgents("cleantalk.antispam");
    }
    
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
            <input type="checkbox" name="status" id="status"<? if ( COption::GetOptionString( $sModuleId, 'status', '0' ) == '1'):?> checked="checked"<? endif; ?> value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top"><label for="form_new_user"><?echo GetMessage( 'CLEANTALK_LABEL_NEW_USER' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_new_user" id="form_new_user"<? if ( COption::GetOptionString( $sModuleId, 'form_new_user', '0' ) == '1'):?> checked="checked"<? endif; ?> value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top"><label for="form_comment_blog"><?echo GetMessage( 'CLEANTALK_LABEL_COMMENT_BLOG' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_comment_blog" id="form_comment_blog"<? if ( COption::GetOptionString( $sModuleId, 'form_comment_blog', '0' ) == '1'):?> checked="checked"<? endif; ?> value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top"><label for="form_comment_forum"><?echo GetMessage( 'CLEANTALK_LABEL_COMMENT_FORUM' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_comment_forum" id="form_comment_forum"<? if ( COption::GetOptionString( $sModuleId, 'form_comment_forum', '0' ) == '1'):?> checked="checked"<? endif; ?> value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top"><label for="form_forum_private_messages"><?echo GetMessage( 'CLEANTALK_LABEL_FORUM_PRIVATE_MESSAGES' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_forum_private_messages" id="form_forum_private_messages"<? if ( COption::GetOptionString( $sModuleId, 'form_forum_private_messages', '0' ) == '1'):?> checked="checked"<? endif; ?> value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top"><label for="form_comment_treelike"><?echo GetMessage( 'CLEANTALK_LABEL_COMMENT_TREELIKE' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_comment_treelike" id="form_comment_treelike"<? if ( COption::GetOptionString( $sModuleId, 'form_comment_treelike', '0' ) == '1'):?> checked="checked"<? endif; ?> value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top"><label for="form_send_example"><?echo GetMessage( 'CLEANTALK_LABEL_SEND_EXAMPLE' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_send_example" id="form_send_example"<? if ( COption::GetOptionString( $sModuleId, 'form_send_example', '0' ) == '1'):?> checked="checked"<? endif; ?> value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top"><label for="form_order"><?echo GetMessage( 'CLEANTALK_LABEL_ORDER' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_order" id="form_order" <? if ( COption::GetOptionString( $sModuleId, 'form_order', '0' ) == '1'):?> checked="checked"<? endif; ?> value="1" />
        </td>
    </tr>
     <tr>
        <td width="50%" valign="top"><label for="web_form"><?echo GetMessage( 'CLEANTALK_LABEL_WEB_FORMS' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="web_form" id="web_form" <? if ( COption::GetOptionString( $sModuleId, 'web_form', '0' ) == '1'):?> checked="checked"<? endif; ?> value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top"><label for="form_global_check"><?echo GetMessage( 'CLEANTALK_LABEL_GLOBAL_CHECK' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_global_check" id="form_global_check"  onclick="ctDdisableInputLine('form_global_check_without_email');" <? if ( COption::GetOptionString( $sModuleId, 'form_global_check', '0' ) == '1'):?> checked="checked"<? endif; ?> value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top"><label id="form_global_check_without_email_label" for="form_global_check_without_email" <?if( COption::GetOptionString( $sModuleId, 'form_global_check', '0' ) == '0'){ echo "style='color: gray;'"; } ?>><?echo GetMessage( 'CLEANTALK_LABEL_GLOBAL_CHECK_WITHOUT_EMAIL' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_global_check_without_email" id="form_global_check_without_email"
            <? 
                if( COption::GetOptionString( $sModuleId, 'form_global_check', '0' ) == '1'){
                    if( COption::GetOptionString( $sModuleId, 'form_global_check_without_email', '0' ) == '1')
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
            <input type="checkbox" name="form_sfw" id="form_sfw" <? if ( COption::GetOptionString( $sModuleId, 'form_sfw', '0' ) == '1'):?> checked="checked"<? endif; ?> value="1" />
        </td>
    </tr>   
    <tr>
        <?php
            $moderate_ip=COption::GetOptionString( $sModuleId, 'moderate_ip', '0' );
            if($moderate_ip == 1){
                print '<td width="100%" valign="top" colspan="2">';
                print "The anti-spam service is paid by your hosting provider. License #".COption::GetOptionString( $sModuleId, 'ip_license', '0' ).".";
                print '</td>';
            }else{
                $key_is_ok = COption::GetOptionString( $sModuleId, 'key_is_ok', '0');
        ?>
        <td width="50%" valign="top"><label for="key"><?echo GetMessage( 'CLEANTALK_LABEL_KEY' );?>:</td>
        <td  valign="top">
            <input type="text" name="key" id="key" value="<?php echo COption::GetOptionString( $sModuleId, 'key', '' ) ?>" /> <span><?php 
                echo COption::GetOptionString( $sModuleId, 'key', '') == '' ? GetMessage( 'CLEANTALK_ENTER_KEY' ) : ($key_is_ok == '1' ? "<span style='color: green'>".GetMessage( 'CLEANTALK_KEY_VALID' )."</span>" : "<span style='color: red'>".GetMessage( 'CLEANTALK_KEY_NOT_VALID' )."</span>" );
                ?></span>
            <input type="hidden" name="is_paid" value="<?php echo COption::GetOptionString( $sModuleId, 'is_paid', '0' ) ?>" />
            <input type="hidden" name="last_checked" value="0" />
            <input type="hidden" name="moderate_ip" value="<?php echo COption::GetOptionString( $sModuleId, 'moderate_ip', '0' ) ?>" />
            <input type="hidden" name="ip_license" value="<?php echo COption::GetOptionString( $sModuleId, 'ip_license', '0' ) ?>" />
        </td>
        <?php
            }
        ?>
    </tr>
    <?php if($key_is_ok == '0'){ ?>
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
