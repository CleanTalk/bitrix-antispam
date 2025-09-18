<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Web\Json;

class CleantalkAjaxComponent extends CBitrixComponent
{
    public function executeComponent()
    {
        if (!Loader::includeModule('cleantalk.antispam')) {
            $this->showError('Module not installed');
            return;
        }

        // Check if this is an AJAX request (relaxed check for compatibility)
        $isAjax = (
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
            (isset($_POST['action']) || isset($_GET['action']))
        );
        
        if (!$isAjax) {
            // Debug: Log what headers we received
            $debug_info = [
                'headers' => function_exists('getallheaders') ? getallheaders() : 'getallheaders not available',
                'server_vars' => [
                    'HTTP_X_REQUESTED_WITH' => $_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'not set',
                    'HTTP_ACCEPT' => $_SERVER['HTTP_ACCEPT'] ?? 'not set',
                    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'not set',
                    'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
                ]
            ];
            $this->showError('Not an AJAX request. Debug: ' . json_encode($debug_info));
            return;
        }

        $action = $this->arParams['ACTION'] ?? $_POST['action'] ?? $_GET['action'] ?? '';
        
        switch ($action) {
            case 'cleantalk_force_ajax_check':
                $this->handleCleantalkAjaxCheck();
                break;
            default:
                $this->showError('Unknown action');
        }
    }

    private function handleCleantalkAjaxCheck()
    {
        global $APPLICATION;
        
        // Get form data
        $formData = $_POST;
        
        // Remove the action parameter
        unset($formData['action']);
        
        // Prepare user data for checking
        $arUser = array();
        $arUser["type"] = "feedback_general_contact_form";
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
                    $this->showSuccess([
                        'apbct' => [
                            'blocked' => false,
                            'comment' => ''
                        ],
                        'success' => true
                    ]);
                } else {
                    // Spam detected - block
                    $this->showSuccess([
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
                $this->showSuccess([
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
            $this->showSuccess([
                'apbct' => [
                    'blocked' => false,
                    'comment' => ''
                ],
                'success' => true
            ]);
        }
    }

    private function showSuccess($data)
    {
        header('Content-Type: application/json');
        echo Json::encode($data);
        die();
    }

    private function showError($message)
    {
        header('Content-Type: application/json');
        echo Json::encode(['error' => $message]);
        die();
    }
}
