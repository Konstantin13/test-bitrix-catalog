<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Catalog\GroupTable;
use Bitrix\Catalog\PriceTable;
use Bitrix\Catalog\ProductTable;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\IblockTable;
use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\SystemException;
use Bitrix\Main\Type\DateTime;

class DemoProductCatalogComponent extends CBitrixComponent
{
    private bool $isCurrencyModuleLoaded = false;

    public function onPrepareComponentParams($arParams)
    {
        $arParams['IBLOCK_ID'] = (int)($arParams['IBLOCK_ID'] ?? 0);
        $arParams['PAGE_ELEMENT_COUNT'] = (int)($arParams['PAGE_ELEMENT_COUNT'] ?? 20);
        $arParams['PAGE_ELEMENT_COUNT'] = $arParams['PAGE_ELEMENT_COUNT'] > 0 ? $arParams['PAGE_ELEMENT_COUNT'] : 20;
        $arParams['CACHE_TIME'] = (int)($arParams['CACHE_TIME'] ?? 3600);
        $arParams['CACHE_TIME'] = $arParams['CACHE_TIME'] > 0 ? $arParams['CACHE_TIME'] : 3600;

        return $arParams;
    }

    public function executeComponent()
    {
        if (!Loader::includeModule('iblock') || !Loader::includeModule('catalog')) {
            ShowError('Не удалось подключить модули iblock и catalog');
            return;
        }

        $this->isCurrencyModuleLoaded = Loader::includeModule('currency');

        if ($this->arParams['IBLOCK_ID'] <= 0) {
            ShowError('Некорректный IBLOCK_ID');
            return;
        }

        $sortDirection = $this->resolveSortDirection();
        $cacheKey = [
            $this->arParams['IBLOCK_ID'],
            $this->arParams['PAGE_ELEMENT_COUNT'],
            $sortDirection,
        ];

        if ($this->startResultCache(false, $cacheKey)) {
            try {
                $this->arResult = $this->loadItems($sortDirection);
                $this->registerIblockTag((int)$this->arParams['IBLOCK_ID']);
            } catch (\Throwable $e) {
                $this->abortManagedCache();
                $this->abortResultCache();
                ShowError($e->getMessage());
                return;
            }

            $this->includeComponentTemplate();
        }
    }

    private function resolveSortDirection(): string
    {
        $sort = (string)Context::getCurrent()->getRequest()->getQuery('sort');
        $sort = strtolower($sort);

        return $sort === 'desc' ? 'desc' : 'asc';
    }

    private function loadItems(string $sortDirection): array
    {
        $iblockId = (int)$this->arParams['IBLOCK_ID'];
        $basePriceGroupId = GroupTable::getBasePriceTypeId();
        if ($basePriceGroupId === null || $basePriceGroupId <= 0) {
            throw new SystemException('Не найден базовый тип цены');
        }

        $now = new DateTime();
        $filter = [
            '=IBLOCK_ID' => $iblockId,
            '=ACTIVE' => 'Y',
            [
                'LOGIC' => 'OR',
                '=ACTIVE_FROM' => null,
                '<=ACTIVE_FROM' => $now,
            ],
            [
                'LOGIC' => 'OR',
                '=ACTIVE_TO' => null,
                '>=ACTIVE_TO' => $now,
            ],
        ];

        $query = ElementTable::query();
        $query->setSelect([
            'ID',
            'NAME',
            'CODE',
            'XML_ID',
            'IBLOCK_ID',
            'IBLOCK_SECTION_ID',
            'IBLOCK_TYPE_ID' => 'IBLOCK.IBLOCK_TYPE_ID',
            'IBLOCK_CODE' => 'IBLOCK.CODE',
            'IBLOCK_XML_ID' => 'IBLOCK.XML_ID',
            'DETAIL_URL_TEMPLATE' => 'IBLOCK.DETAIL_PAGE_URL',
            'PRICE_VALUE' => 'PRICE.PRICE',
            'PRICE_CURRENCY' => 'PRICE.CURRENCY',
            'QUANTITY_VALUE' => 'PRODUCT.QUANTITY',
        ]);
        $query->registerRuntimeField(
            new Reference(
                'IBLOCK',
                IblockTable::class,
                Join::on('this.IBLOCK_ID', 'ref.ID')
            )
        );
        $query->registerRuntimeField(
            new Reference(
                'PRICE',
                PriceTable::class,
                Join::on('this.ID', 'ref.PRODUCT_ID')
                    ->where('ref.CATALOG_GROUP_ID', '=', $basePriceGroupId)
                    ->whereNull('ref.QUANTITY_FROM')
                    ->whereNull('ref.QUANTITY_TO')
            )
        );
        $query->registerRuntimeField(
            new Reference(
                'PRODUCT',
                ProductTable::class,
                Join::on('this.ID', 'ref.ID')
            )
        );
        $query->setFilter($filter);
        $query->setOrder([
            'PRICE_VALUE' => strtoupper($sortDirection),
            'ID' => 'ASC',
        ]);
        $query->setLimit((int)$this->arParams['PAGE_ELEMENT_COUNT']);

        $rows = [];
        $result = $query->exec();
        while ($row = $result->fetch()) {
            $rows[] = $row;
        }

        $items = [];
        foreach ($rows as $row) {
            $priceValue = $row['PRICE_VALUE'] !== null ? (float)$row['PRICE_VALUE'] : null;
            $currency = (string)($row['PRICE_CURRENCY'] ?? '');
            $items[] = [
                'ID' => (int)$row['ID'],
                'NAME' => (string)$row['NAME'],
                'DETAIL_PAGE_URL' => $this->buildDetailUrl($row),
                'PRICE' => $priceValue,
                'PRICE_FORMATTED' => $priceValue !== null ? CurrencyFormat($priceValue, $currency) : '—',
                'QUANTITY' => (float)($row['QUANTITY_VALUE'] ?? 0),
            ];
        }

        return [
            'ITEMS' => $items,
            'CURRENT_SORT' => $sortDirection,
        ];
    }

    private function buildDetailUrl(array $row): string
    {
        $template = (string)($row['DETAIL_URL_TEMPLATE'] ?? '');
        if ($template === '') {
            return '';
        }

        $elementId = (int)($row['ID'] ?? 0);
        $fields = [
            'ID' => $elementId,
            'ELEMENT_ID' => $elementId,
            'CODE' => (string)($row['CODE'] ?? ''),
            'ELEMENT_CODE' => (string)($row['CODE'] ?? ''),
            'EXTERNAL_ID' => (string)($row['XML_ID'] ?? ''),
            'IBLOCK_TYPE_ID' => (string)($row['IBLOCK_TYPE_ID'] ?? ''),
            'IBLOCK_ID' => (int)($row['IBLOCK_ID'] ?? 0),
            'IBLOCK_CODE' => (string)($row['IBLOCK_CODE'] ?? ''),
            'IBLOCK_EXTERNAL_ID' => (string)($row['IBLOCK_XML_ID'] ?? ''),
            'IBLOCK_SECTION_ID' => (int)($row['IBLOCK_SECTION_ID'] ?? 0),
            'LANG_DIR' => defined('SITE_DIR') ? (string)SITE_DIR : '/',
            'LID' => defined('SITE_ID') ? (string)SITE_ID : '',
        ];

        $url = CIBlock::ReplaceDetailUrl($template, $fields, true, 'E');

        return is_string($url) ? $url : '';
    }

    private function registerIblockTag(int $iblockId): void
    {
        if (!defined('BX_COMP_MANAGED_CACHE')) {
            return;
        }

        $taggedCache = Application::getInstance()->getTaggedCache();
        $taggedCache->startTagCache($this->GetCachePath());
        $taggedCache->registerTag('iblock_id_' . $iblockId);
        $taggedCache->endTagCache();
    }

    private function abortManagedCache(): void
    {
        if (!defined('BX_COMP_MANAGED_CACHE')) {
            return;
        }

        Application::getInstance()->getTaggedCache()->abortTagCache();
    }
}
