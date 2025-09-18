<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentParameters = array(
    "GROUPS" => array(),
    "PARAMETERS" => array(
        "ACTION" => array(
            "PARENT" => "BASE",
            "NAME" => "AJAX Action",
            "TYPE" => "STRING",
            "DEFAULT" => "cleantalk_force_ajax_check",
        ),
    ),
);
