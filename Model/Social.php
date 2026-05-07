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

use Magento\Customer\Model\EmailNotificationInterface;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Reward\Helper\Data as RewardData;
use Magento\Reward\Model\RewardFactory;

class Social extends \Magento\Framework\Model\AbstractModel
{
    protected EmailNotificationInterface $emailNotificationInterface;
    protected RewardFactory $rewardFactory;
    protected RewardData $rewardData;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        private readonly \Techyouknow\SocialLogin\Helper\Social $socialHelper,
        private readonly \Magento\Customer\Api\Data\CustomerInterfaceFactory $customerFactory,
        private readonly \Magento\Customer\Model\CustomerFactory $customerModelFactory,
        private readonly \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        private readonly \Magento\Store\Model\StoreManagerInterface $storeManager,
        private readonly \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory,
        private readonly \Magento\Framework\Stdlib\Cookie\PhpCookieManager $cookieMetadataManager,
        private readonly \Magento\Customer\Model\Session $session,
        private readonly \Magento\Customer\Model\AccountManagement $accountManagement,
        private readonly \Magento\Framework\Math\Random $random,
        private readonly \Techyouknow\SocialLogin\Api\Data\SocialNetworkCustomerFactory $socialNetworkCustomer,
        private readonly \Techyouknow\SocialLogin\Model\Repository\SocialLoginCustomerRepository $socialLoginCustomerRepository,
        EmailNotificationInterface $emailNotificationInterface,
        RewardFactory $rewardFactory,
        RewardData $rewardData,
        \Psr\Log\LoggerInterface $logger,
        private readonly CustomerRegistry $customerRegistry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
        $this->emailNotificationInterface = $emailNotificationInterface;
        $this->rewardFactory = $rewardFactory;
        $this->rewardData = $rewardData;
        $this->_logger = $logger;
    }

    public function getSocialUserProfile(string $adapterId): array
    {
        $adapterName = $this->socialHelper->getSocialNetwork($adapterId);
        $adaptersConfig = $this->socialHelper->getHybridauthConfig($adapterId);

        $hybridauth = new \Hybridauth\Hybridauth($adaptersConfig);
        $adapter = $hybridauth->authenticate($adapterName);
        $userProfile = $adapter->getUserProfile();
        $adapter->disconnect();

        return $this->prepareUserProfile($userProfile, $adapterId);
    }

    public function prepareUserProfile(object $userProfile, string $type): array
    {
        $name = explode(' ', (string) ($userProfile->displayName ?: __('New User')));

        return [
            'email'      => $userProfile->email ?: $userProfile->identifier . '@' . strtolower($type) . '.com',
            'firstname'  => $userProfile->firstName ?: (array_shift($name) ?: $userProfile->identifier),
            'lastname'   => $userProfile->lastName ?: (array_shift($name) ?: $userProfile->identifier),
            'identifier' => $userProfile->identifier,
            'type'       => $type,
            'password'   => $userProfile->password ?? null,
        ];
    }

    public function createCustomerAccount(array $userProfile, string $type): \Magento\Customer\Model\Customer
    {
        $store = $this->storeManager->getStore();

        $customer = $this->customerFactory->create();
        $customer
            ->setFirstname($userProfile['firstname'])
            ->setLastname($userProfile['lastname'])
            ->setEmail($userProfile['email'])
            ->setStoreId($store->getId())
            ->setWebsiteId($store->getWebsiteId())
            ->setCreatedIn($store->getName());

        $customer = $this->customerRepository->save($customer);
        $this->createSocialLoginCustomer($userProfile, $type, $customer->getId());

        $newPasswordToken = $this->random->getUniqueHash();
        $this->accountManagement->changeResetPasswordLinkToken($customer, $newPasswordToken);
        $this->emailNotificationInterface->newAccount(
            $customer,
            EmailNotificationInterface::NEW_ACCOUNT_EMAIL_REGISTERED_NO_PASSWORD
        );
        $this->assignRewardPoints($customer);

        return $this->customerModelFactory->create()->load($customer->getId());
    }

    public function createSocialLoginCustomer(array $userProfile, string $type, int|string $customerId): void
    {
        $now = (new \DateTime())->format('Y-m-d H:i:s');
        $socialNetworkCustomer = $this->socialNetworkCustomer->create();
        $socialNetworkCustomer
            ->setSocialId($userProfile['identifier'])
            ->setCustomerId($customerId)
            ->setSocialType($type)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        $this->socialLoginCustomerRepository->save($socialNetworkCustomer);
    }

    public function refresh(\Magento\Customer\Model\Customer $customer): void
    {
        if ($customer && $customer->getId()) {
            $this->session->setCustomerAsLoggedIn($customer);
            $this->session->regenerateId();

            if ($this->cookieMetadataManager->getCookie('mage-cache-sessid')) {
                $metadata = $this->cookieMetadataFactory->createCookieMetadata();
                $metadata->setPath('/');
                $this->cookieMetadataManager->deleteCookie('mage-cache-sessid', $metadata);
            }
        }
    }

    protected function assignRewardPoints(\Magento\Customer\Api\Data\CustomerInterface $customer): void
    {
        if ($this->rewardData->isEnabledOnFront()) {
            try {
                $subscribeByDefault = $this->rewardData->getNotificationConfig(
                    'subscribe_by_default',
                    $this->storeManager->getStore()->getWebsiteId()
                );
                $customerModel = $this->customerRegistry->retrieveByEmail($customer->getEmail());
                $customerModel->setRewardUpdateNotification($subscribeByDefault);
                $customerModel->setRewardWarningNotification($subscribeByDefault);
                $customerModel->getResource()->saveAttribute($customerModel, 'reward_update_notification');
                $customerModel->getResource()->saveAttribute($customerModel, 'reward_warning_notification');

                $reward = $this->rewardFactory->create();
                $reward
                    ->setCustomer($customer)
                    ->setActionEntity($customer)
                    ->setStore($this->storeManager->getStore()->getId())
                    ->setAction(\Magento\Reward\Model\Reward::REWARD_ACTION_REGISTER)
                    ->updateRewardPoints();
            } catch (\Exception $e) {
                $this->_logger->critical($e);
            }
        }
    }
}
