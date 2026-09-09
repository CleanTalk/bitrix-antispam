<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");


if (!CModule::IncludeModule('cleantalk.antispam')) {
    echo json_encode(['error' => 'Module not loaded']);
    exit;
}

$fields = $_POST;
$response = \Cleantalk\Integrations\IntegrationFactory::handle($fields);
echo json_encode($response);
