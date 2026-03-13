<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentParameters = [
    'PARAMETERS' => [
        'IBLOCK_ID' => [
            'PARENT' => 'BASE',
            'NAME' => 'ID инфоблока',
            'TYPE' => 'STRING',
            'DEFAULT' => '2',
        ],
        'PAGE_ELEMENT_COUNT' => [
            'PARENT' => 'BASE',
            'NAME' => 'Количество товаров',
            'TYPE' => 'STRING',
            'DEFAULT' => '20',
        ],
        'CACHE_TIME' => ['DEFAULT' => 3600],
    ],
];
