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
    /**
     * Set settings when submit
     */
    $old_key=COption::GetOptionString( $sModuleId, 'key', '' );
    $new_key=$_POST['key'];
    //if($old_key!=$new_key)
    //{
    	$url = 'http://moderate.cleantalk.org/api2.0';
    	$dt=Array(
			'auth_key'=>$new_key,
			'method_name'=> 'check_message',
			'message'=>'CleanTalk setup test',
			'example'=>null,
			'agent'=>'bitrix-330',
			'sender_ip'=>$_SERVER['REMOTE_ADDR'],
			'sender_email'=>'good@cleantalk.org',
			'sender_nickname'=>'CleanTalk',
			'js_on'=>1);
		
		$result=CleantalkAntispam::CleantalkSendRequest($url,$dt,true);
    //}
    COption::SetOptionString( $sModuleId, 'status', $_POST['status'] == '1' ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'form_new_user', $_POST['form_new_user'] == '1' ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'form_comment_blog', $_POST['form_comment_blog'] == '1' ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'form_comment_forum', $_POST['form_comment_forum'] == '1' ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'form_comment_treelike', $_POST['form_comment_treelike'] == '1' ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'form_send_example', $_POST['form_send_example'] == '1' ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'form_order', $_POST['form_order'] == '1' ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'is_paid', $_POST['is_paid'] == '1' ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'last_checked', $_POST['last_checked'] == '1' ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'form_global_check', $_POST['form_global_check'] == '1' ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'form_sfw', $_POST['form_sfw'] == '1' ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'sfw_last_updated', $_POST['sfw_last_updated'] == '1' ? '1' : '0' );
    COption::SetOptionString( $sModuleId, 'key', $_POST['key'] );
    
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
        <td width="50%" valign="top"><label for="form_global_check"><?echo GetMessage( 'CLEANTALK_LABEL_GLOBAL_CHECK' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_global_check" id="form_global_check" <? if ( COption::GetOptionString( $sModuleId, 'form_global_check', '0' ) == '1'):?> checked="checked"<? endif; ?> value="1" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top"><label for="form_global_check"><?echo GetMessage( 'CLEANTALK_LABEL_SFW' );?>:</td>
        <td  valign="top">
            <input type="checkbox" name="form_sfw" id="form_sfw" <? if ( COption::GetOptionString( $sModuleId, 'form_sfw', '0' ) == '1'):?> checked="checked"<? endif; ?> value="1" />
            <input type="hidden" name="sfw_last_updated" value="<?php echo COption::GetOptionString( $sModuleId, 'sfw_last_updated', '0' ) ?>" />
        </td>
    </tr>
    <tr>
        <td width="50%" valign="top"><label for="key"><?echo GetMessage( 'CLEANTALK_LABEL_KEY' );?>:</td>
        <td  valign="top">
            <input type="text" name="key" id="key" value="<?php echo COption::GetOptionString( $sModuleId, 'key', '' ) ?>" />
            <input type="hidden" name="is_paid" value="<?php echo COption::GetOptionString( $sModuleId, 'is_paid', '0' ) ?>" />
            <input type="hidden" name="last_checked" value="<?php echo COption::GetOptionString( $sModuleId, 'last_checked', '0' ) ?>" />
        </td>
    </tr>
    <?$oTabControl->Buttons();?>
    <input type="submit" name="Update" value="<?php echo GetMessage( 'CLEANTALK_BUTTON_SAVE' ) ?>" />
    <input type="reset" name="reset" value="<?php echo GetMessage( 'CLEANTALK_BUTTON_RESET' ) ?>" />
    <input type="hidden" name="Update" value="Y" />
    <?$oTabControl->End();?>
</form>
