<?php

namespace Cleantalk\ApbctBitrix;

use Bitrix\Main\Loader;
use Bitrix\Main\Web\Json;
use CleantalkAntispam;

class CleantalkExternalFormsAjaxHandler
{
    public static function handleAjax($request)
    {
        global $APPLICATION;

        if (!Loader::includeModule('cleantalk.antispam')) {
            return;
        }
        $formData = $request->getPostList()->toArray();

        if (
            empty($formData) ||
            !isset($formData['action']) ||
            $formData['action']
            !== 'cleantalk_force_ajax_check'
        ) {
            return;
        }
        // Remove the action parameter
        unset($formData['action']);

        // Sanitize and filter form data
        $formData = CleantalkAntispam::apbct__filter_form_data($formData);

        // Prepare user data for checking
        $arUser = array();
        $arUser["type"] = "feedback_ajax";
        $arUser["sender_email"] = $formData['email'] ?? '';
        $arUser["sender_nickname"] = $formData['name'] ?? $formData['nickname'] ?? '';
        $arUser["subject"] = $formData['subject'] ?? '';
        $arUser["message"] = array_filter($formData, function($key) {
            return !in_array($key, ['email', 'name', 'nickname', 'subject', 'action', 'ct_bot_detector_event_token']);
        }, ARRAY_FILTER_USE_KEY);

        // Check for spam
        $aResult = CleantalkAntispam::CheckAllBefore($arUser, false);

        if (isset($aResult) && is_array($aResult)) {
            if ($aResult['errno'] == 0) {
                if ($aResult['allow'] == 1) {
                    // Not spam - allow
                    self::showSuccess([
                                           'apbct' => [
                                               'blocked' => false,
                                               'comment' => ''
                                           ],
                                           'success' => true
                                       ]);
                } else {
                    // Spam detected - block
                    self::showSuccess([
                                           'apbct' => [
                                               'blocked' => true,
                                               'comment' => $aResult['ct_result_comment']
                                           ],
                                           'error' => [
                                               'msg' => $aResult['ct_result_comment']
                                           ]
                                       ]);
                }
            } else {
                // Error occurred
                self::showSuccess([
                                       'apbct' => [
                                           'blocked' => false,
                                           'comment' => ''
                                       ],
                                       'error' => [
                                           'msg' => $aResult['errstr'] ?? 'Unknown error'
                                       ]
                                   ]);
            }
        } else {
            // No result - allow by default
            self::showSuccess([
                                   'apbct' => [
                                       'blocked' => false,
                                       'comment' => ''
                                   ],
                                   'success' => true
                               ]);
        }
    }

    private static function showSuccess($data)
    {
        header('Content-Type: application/json');
        echo Json::encode($data);
        die();
    }

    private static function showError($message)
    {
        header('Content-Type: application/json');
        echo Json::encode(['error' => $message]);
        die();
    }
}
