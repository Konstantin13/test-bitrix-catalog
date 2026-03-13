<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

$APPLICATION->SetTitle('Тестовый каталог товаров');
?>

<?php
$APPLICATION->IncludeComponent(
    'demo:product.catalog',
    '',
    [
        'IBLOCK_ID' => '2',
        'PAGE_ELEMENT_COUNT' => '20',
        'CACHE_TYPE' => 'A',
        'CACHE_TIME' => '3600',
    ]
);
?>

<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
