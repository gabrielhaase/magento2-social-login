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

namespace Techyouknow\SocialLogin\Model;

use Techyouknow\SocialLogin\Api\Data\SocialNetworkCustomer as SocialNetworkCustomerInterface;

class SocialLoginCustomer extends \Magento\Framework\Model\AbstractModel implements SocialNetworkCustomerInterface
{
    protected function _construct(): void
    {
        $this->_init(\Techyouknow\SocialLogin\Model\ResourceModel\SocialLoginCustomer::class);
    }

    public function getEntityId(): mixed
    {
        return $this->getData(SocialNetworkCustomerInterface::ENTITY_ID);
    }

    public function setEntityId($entityId): static
    {
        $this->setData(SocialNetworkCustomerInterface::ENTITY_ID, $entityId);
        return $this;
    }

    public function getSocialId(): mixed
    {
        return $this->getData(SocialNetworkCustomerInterface::SOCIAL_ID);
    }

    public function setSocialId($socialId): static
    {
        $this->setData(SocialNetworkCustomerInterface::SOCIAL_ID, $socialId);
        return $this;
    }

    public function getCustomerId(): mixed
    {
        return $this->getData(SocialNetworkCustomerInterface::CUSTOMER_ID);
    }

    public function setCustomerId($customerId): static
    {
        $this->setData(SocialNetworkCustomerInterface::CUSTOMER_ID, $customerId);
        return $this;
    }

    public function getSocialType(): mixed
    {
        return $this->getData(SocialNetworkCustomerInterface::SOCIAL_TYPE);
    }

    public function setSocialType($socialType): static
    {
        $this->setData(SocialNetworkCustomerInterface::SOCIAL_TYPE, $socialType);
        return $this;
    }

    public function getCreatedAt(): mixed
    {
        return $this->getData(SocialNetworkCustomerInterface::CREATED_AT);
    }

    public function setCreatedAt($createdAt): static
    {
        $this->setData(SocialNetworkCustomerInterface::CREATED_AT, $createdAt);
        return $this;
    }

    public function getUpdatedAt(): mixed
    {
        return $this->getData(SocialNetworkCustomerInterface::UPDATED_AT);
    }

    public function setUpdatedAt($updatedAt): static
    {
        $this->setData(SocialNetworkCustomerInterface::UPDATED_AT, $updatedAt);
        return $this;
    }
}
