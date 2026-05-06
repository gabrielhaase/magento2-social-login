<?php
declare(strict_types=1);
/*
 * MIT License
 *
 * Copyright (c) 2023 Techyouknow
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Techyouknow\SocialLogin\Model\Repository;

use Magento\Framework\Exception\NoSuchEntityException;
use Techyouknow\SocialLogin\Api\Data\SocialNetworkCustomer;
use Techyouknow\SocialLogin\Api\SocialNetworkCustomerRepositoryInterface;
use Techyouknow\SocialLogin\Model\ResourceModel\SocialLoginCustomer\CollectionFactory;
use Techyouknow\SocialLogin\Model\SocialLoginCustomerFactory;

class SocialLoginCustomerRepository implements SocialNetworkCustomerRepositoryInterface
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly SocialLoginCustomerFactory $socialLoginCustomerFactory,
    ) {}

    public function getById(int $id): SocialNetworkCustomer
    {
        $socialLoginCustomer = $this->socialLoginCustomerFactory->create();
        $socialLoginCustomer->getResource()->load($socialLoginCustomer, $id);

        if (!$socialLoginCustomer->getId()) {
            throw new NoSuchEntityException(__('Unable to find Social Login Customer with ID %1', $id));
        }

        return $socialLoginCustomer;
    }

    public function socialNetworkCustomerExists(array $userProfile, string $type): int
    {
        return $this->collectionFactory->create()
            ->addFieldToFilter('social_id', $userProfile['identifier'])
            ->addFieldToFilter('social_type', $type)
            ->count();
    }

    public function save(SocialNetworkCustomer $socialNetworkCustomer): SocialNetworkCustomer
    {
        $socialNetworkCustomer->getResource()->save($socialNetworkCustomer);
        return $socialNetworkCustomer;
    }

    public function delete(SocialNetworkCustomer $socialNetworkCustomer): SocialNetworkCustomer
    {
        $socialNetworkCustomer->getResource()->delete($socialNetworkCustomer);
        return $socialNetworkCustomer;
    }
}
