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

namespace Techyouknow\SocialLogin\Controller\Social;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Techyouknow\SocialLogin\Model\Social as SocialModel;

class SaveRegistration implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly Session $customerSession,
        private readonly ManagerInterface $messageManager,
        private readonly AccountManagementInterface $accountManagement,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerInterfaceFactory $customerFactory,
        private readonly CustomerFactory $customerModelFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly SocialModel $socialModel,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(): ResultInterface
    {
        $redirectBack = $this->resultRedirectFactory->create()
            ->setPath('techyouknow_redirect/social/completeregistration');

        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(__('Invalid form key. Please reload the page and try again.'));
            return $redirectBack;
        }

        $profile = $this->customerSession->getData('social_login_pending_profile');
        if (!$profile || empty($profile['email'])) {
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        $taxvat    = preg_replace('/\D/', '', (string) $this->request->getParam('taxvat', ''));
        $firstname = trim((string) $this->request->getParam('firstname', $profile['firstname']));
        $lastname  = trim((string) $this->request->getParam('lastname', $profile['lastname']));
        $password  = (string) $this->request->getParam('password', '');
        $passwordConfirm = (string) $this->request->getParam('password_confirmation', '');

        if ($errors = $this->validate($taxvat, $firstname, $lastname, $password, $passwordConfirm)) {
            foreach ($errors as $error) {
                $this->messageManager->addErrorMessage($error);
            }
            return $redirectBack;
        }

        try {
            // Garante que o email ainda não existe (race condition)
            try {
                $this->customerRepository->get($profile['email']);
                $this->customerSession->unsetData('social_login_pending_profile');
                $this->messageManager->addErrorMessage(
                    __('An account with this email already exists. Please log in.')
                );
                return $this->resultRedirectFactory->create()->setPath('customer/account/login');
            } catch (\Magento\Framework\Exception\NoSuchEntityException) {
                // OK, email livre — prosseguir com a criação
            }

            $store    = $this->storeManager->getStore();
            $customer = $this->customerFactory->create();
            $customer
                ->setFirstname($firstname)
                ->setLastname($lastname)
                ->setEmail($profile['email'])
                ->setStoreId($store->getId())
                ->setWebsiteId($store->getWebsiteId())
                ->setCreatedIn($store->getName());

            // Cria o cliente com senha (AccountManagement faz hash + envia email de boas-vindas)
            $savedCustomer = $this->accountManagement->createAccount($customer, $password);

            // Salva o taxvat separadamente (AccountManagement não expõe esse campo diretamente)
            $savedCustomer->setTaxvat($taxvat);
            $this->customerRepository->save($savedCustomer);

            // Cria o vínculo com o provedor social
            $this->socialModel->createSocialLoginCustomer($profile, $profile['adapter_id'], $savedCustomer->getId());

            // Limpa os dados pendentes da sessão
            $this->customerSession->unsetData('social_login_pending_profile');

            // Faz o login do cliente
            $customerModel = $this->customerModelFactory->create()->load($savedCustomer->getId());
            $this->socialModel->refresh($customerModel);

        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $redirectBack;
        } catch (\Exception $e) {
            $this->logger->critical('Social login complete registration error: ' . $e->getMessage(), ['exception' => $e]);
            $this->messageManager->addErrorMessage(__('An error occurred while creating your account. Please try again.'));
            return $redirectBack;
        }

        return $this->resultRedirectFactory->create()->setPath('customer/account');
    }

    private function validate(
        string $taxvat,
        string $firstname,
        string $lastname,
        string $password,
        string $passwordConfirm
    ): array {
        $errors = [];

        if (empty($firstname)) {
            $errors[] = __('First name is required.');
        }

        if (empty($lastname)) {
            $errors[] = __('Last name is required.');
        }

        if (strlen($taxvat) !== 11) {
            $errors[] = __('CPF must contain exactly 11 digits.');
        } elseif (!$this->isValidCpf($taxvat)) {
            $errors[] = __('Please enter a valid CPF.');
        }

        if (strlen($password) < 8) {
            $errors[] = __('Password must be at least 8 characters long.');
        }

        if ($password !== $passwordConfirm) {
            $errors[] = __('Password and password confirmation do not match.');
        }

        return $errors;
    }

    private function isValidCpf(string $cpf): bool
    {
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false; // Sequências como 00000000000
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * ($t + 1 - $i);
            }
            $remainder = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $remainder) {
                return false;
            }
        }

        return true;
    }
}
