<?php

/**
 * This file is part of jurager/exchange1c package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Jurager\Exchange1C\Events;

class AfterProductsSync extends AbstractEventInterface
{
    const NAME = 'after.products.sync';

    /**
     * @var array
     */
    public $ids;

    public $merchant_id;

    /**
     * AfterProductsSync constructor.
     *
     * @param array $ids
     * @param string $merchant_id
     */
    public function __construct(array $ids = [], ?string $merchant_id = null)
    {
        $this->ids = $ids;
        $this->merchant_id = $merchant_id;
    }
}
