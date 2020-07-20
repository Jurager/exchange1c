<?php

/**
 * This file is part of jurager/exchange1c package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Jurager\Exchange1C\Interfaces;

interface WarehouseInterface extends IdentifierInterface
{
    /**
     * Создание списка складов
     * в параметр передаётся массив всех сладов (import.xml > Классификатор > склады)
     *
     * @param \Zenwalker\CommerceML\Model\Warehouse[] $warehouses
     * @param $merchant_id
     *
     * @return void
     */
    public static function createWarehouse1c($warehouses, $merchant_id);
}