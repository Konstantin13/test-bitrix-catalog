<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Catalog\PriceTable;
use Bitrix\Catalog\ProductTable;
use Bitrix\Main\Loader;
use Bitrix\Main\SystemException;

class DemoProductCatalogComponent extends CBitrixComponent
{
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
        $sort = (string)($_REQUEST['sort'] ?? 'asc');
        $sort = strtolower($sort);

        return $sort === 'desc' ? 'desc' : 'asc';
    }

    private function loadItems(string $sortDirection): array
    {
        $iblockId = (int)$this->arParams['IBLOCK_ID'];
        $basePriceGroup = CCatalogGroup::GetBaseGroup();

        if (!$basePriceGroup || empty($basePriceGroup['ID'])) {
            throw new SystemException('Не найден базовый тип цены');
        }

        $basePriceGroupId = (int)$basePriceGroup['ID'];
        $sortField = 'CATALOG_PRICE_' . $basePriceGroupId;
        $elementIds = [];
        $itemsById = [];

        $elements = CIBlockElement::GetList(
            [
                $sortField => strtoupper($sortDirection),
                'ID' => 'ASC',
            ],
            [
                'IBLOCK_ID' => $iblockId,
                'ACTIVE' => 'Y',
                'ACTIVE_DATE' => 'Y',
            ],
            false,
            ['nTopCount' => (int)$this->arParams['PAGE_ELEMENT_COUNT']],
            ['ID', 'IBLOCK_ID', 'NAME', 'DETAIL_PAGE_URL']
        );

        while ($item = $elements->GetNext()) {
            $elementId = (int)$item['ID'];
            $elementIds[] = $elementId;
            $itemsById[$elementId] = [
                'ID' => $elementId,
                'NAME' => (string)$item['NAME'],
                'DETAIL_PAGE_URL' => (string)$item['DETAIL_PAGE_URL'],
                'PRICE' => null,
                'PRICE_FORMATTED' => '—',
                'QUANTITY' => 0.0,
            ];
        }

        if ($elementIds) {
            $prices = $this->loadPrices($elementIds, $basePriceGroupId);
            $quantities = $this->loadQuantities($elementIds);

            foreach ($elementIds as $elementId) {
                if (isset($prices[$elementId])) {
                    $priceValue = (float)$prices[$elementId]['PRICE'];
                    $currency = (string)$prices[$elementId]['CURRENCY'];
                    $itemsById[$elementId]['PRICE'] = $priceValue;
                    $itemsById[$elementId]['PRICE_FORMATTED'] = $this->formatPrice($priceValue, $currency);
                }

                if (isset($quantities[$elementId])) {
                    $itemsById[$elementId]['QUANTITY'] = (float)$quantities[$elementId];
                }
            }
        }

        return [
            'ITEMS' => array_values($itemsById),
            'CURRENT_SORT' => $sortDirection,
        ];
    }

    private function loadPrices(array $elementIds, int $basePriceGroupId): array
    {
        $prices = [];

        $result = PriceTable::getList([
            'select' => ['PRODUCT_ID', 'PRICE', 'CURRENCY'],
            'filter' => [
                '=PRODUCT_ID' => $elementIds,
                '=CATALOG_GROUP_ID' => $basePriceGroupId,
            ],
        ]);

        while ($row = $result->fetch()) {
            $prices[(int)$row['PRODUCT_ID']] = [
                'PRICE' => (float)$row['PRICE'],
                'CURRENCY' => (string)$row['CURRENCY'],
            ];
        }

        return $prices;
    }

    private function loadQuantities(array $elementIds): array
    {
        $quantities = [];
        $result = ProductTable::getList([
            'select' => ['ID', 'QUANTITY'],
            'filter' => ['=ID' => $elementIds],
        ]);

        while ($row = $result->fetch()) {
            $quantities[(int)$row['ID']] = (float)$row['QUANTITY'];
        }

        return $quantities;
    }

    private function formatPrice(float $price, string $currency): string
    {
        if (Loader::includeModule('currency') && class_exists('CCurrencyLang')) {
            return CCurrencyLang::CurrencyFormat($price, $currency, true);
        }

        return number_format($price, 2, '.', ' ') . ' ' . $currency;
    }

    private function registerIblockTag(int $iblockId): void
    {
        if (!defined('BX_COMP_MANAGED_CACHE')) {
            return;
        }

        global $CACHE_MANAGER;
        $CACHE_MANAGER->StartTagCache($this->GetCachePath());
        $CACHE_MANAGER->RegisterTag('iblock_id_' . $iblockId);
        $CACHE_MANAGER->EndTagCache();
    }

    private function abortManagedCache(): void
    {
        if (!defined('BX_COMP_MANAGED_CACHE')) {
            return;
        }

        global $CACHE_MANAGER;
        $CACHE_MANAGER->AbortTagCache();
    }
}
