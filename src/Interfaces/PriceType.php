<?php

/**
 * This file is part of jurager/exchange1c package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Jurager\Exchange1C\Interfaces;

interface PriceTypeInterface extends IdentifierInterface
{
    /**
     * Создание списка типов цен
     * в параметр передаётся массив всех типов цен (import.xml > Классификатор > ТипыЦен)
     *
     * @param \Zenwalker\CommerceML\Model\PriceType[] $priceTypes
     * @param $merchant_id
     *
     * @return void
     */
    public static function createPriceTypes1c($priceTypes, $merchant_id);
}