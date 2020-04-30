<?php

/**
 * This file is part of jurager/exchange1c package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Jurager\Exchange1C\Events;

class AfterComplete extends AbstractEventInterface
{
    const NAME = 'after.complete';

    public $merchant_id;

    /**
     * AfterComplete constructor.
     *
     * @param string $merchant_id
     */
    public function __construct(string $merchant_id = null)
    {
        $this->merchant_id = $merchant_id;
    }

}