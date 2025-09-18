<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$APPLICATION->IncludeComponent(
    "cleantalk:ajax",
    "",
    array(
        "ACTION" => $_POST['action'] ?? $_GET['action'] ?? 'cleantalk_force_ajax_check'
    )
);
