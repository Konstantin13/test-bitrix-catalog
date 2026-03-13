<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

global $APPLICATION;
$sortAscUrl = $APPLICATION->GetCurPageParam('sort=asc', ['sort']);
$sortDescUrl = $APPLICATION->GetCurPageParam('sort=desc', ['sort']);
?>

<div class="test-product-catalog">
    <div style="margin-bottom: 16px;">
        <strong>Сортировка по цене:</strong>
        <a href="<?=htmlspecialcharsbx($sortAscUrl)?>" <?=$arResult['CURRENT_SORT'] === 'asc' ? 'style="font-weight:700;"' : ''?>>Сначала дешёвые</a>
        |
        <a href="<?=htmlspecialcharsbx($sortDescUrl)?>" <?=$arResult['CURRENT_SORT'] === 'desc' ? 'style="font-weight:700;"' : ''?>>Сначала дорогие</a>
    </div>

    <?php if (empty($arResult['ITEMS'])): ?>
        <p>Товары не найдены.</p>
    <?php else: ?>
        <table class="table table-bordered table-striped">
            <thead>
            <tr>
                <th>Товар</th>
                <th>Цена</th>
                <th>Остаток</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($arResult['ITEMS'] as $item): ?>
                <tr>
                    <td>
                        <?php if (!empty($item['DETAIL_PAGE_URL'])): ?>
                            <a href="<?=htmlspecialcharsbx($item['DETAIL_PAGE_URL'])?>"><?=htmlspecialcharsbx($item['NAME'])?></a>
                        <?php else: ?>
                            <?=htmlspecialcharsbx($item['NAME'])?>
                        <?php endif; ?>
                    </td>
                    <td><?=(string)$item['PRICE_FORMATTED']?></td>
                    <td><?=htmlspecialcharsbx(rtrim(rtrim(number_format((float)$item['QUANTITY'], 2, '.', ''), '0'), '.'))?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
