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

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface;
use Techyouknow\SocialLogin\Api\SocialNetworkCustomerRepositoryInterface;
use Techyouknow\SocialLogin\Helper\Social as SocialHelper;
use Techyouknow\SocialLogin\Model\Social;

class Login implements HttpGetActionInterface, HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RawFactory $resultRawFactory,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly PageFactory $resultPageFactory,
        private readonly Social $socialModel,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerFactory $customerModelFactory,
        private readonly SocialNetworkCustomerRepositoryInterface $socialNetworkCustomerRepository,
        private readonly SocialHelper $socialHelper,
        private readonly Session $customerSession,
        private readonly LoggerInterface $logger,
        private readonly UrlInterface $url,
    ) {}

    public function execute(): ResultInterface
    {
        $adapterId = (string) $this->request->getParam('provider');

        if ($this->customerSession->isLoggedIn() || !$this->checkAdapterIdActive($adapterId)) {
            return $this->resultRedirectFactory->create()
                ->setPath($this->customerSession->isLoggedIn() ? 'customer/account' : '/');
        }

        try {
            $userProfile = $this->socialModel->getSocialUserProfile($adapterId);

            try {
                // Cliente existente: faz login normalmente
                $customer      = $this->customerRepository->get($userProfile['email']);
                $customerModel = $this->customerModelFactory->create()->load($customer->getId());

                if (!$this->socialNetworkCustomerRepository->socialNetworkCustomerExists($userProfile, $adapterId)) {
                    $this->socialModel->createSocialLoginCustomer($userProfile, $adapterId, $customer->getId());
                }

                $this->socialModel->refresh($customerModel);

                if ($adapterId === 'apple') {
                    $page = $this->resultPageFactory->create();
                    $page->addHandle('custom_script');
                    return $page;
                }

                return $this->appendJs();

            } catch (\Magento\Framework\Exception\NoSuchEntityException) {
                // Novo cliente: guarda perfil na sessão e redireciona para completar cadastro
                $this->customerSession->setData(
                    'social_login_pending_profile',
                    array_merge($userProfile, ['adapter_id' => $adapterId])
                );

                $completeUrl = $this->url->getUrl('techyouknow_redirect/social/completeregistration');

                return $this->appendJs(
                    'window.opener.location.href=' . json_encode($completeUrl) . ';window.close();'
                );
            }

        } catch (\Exception $e) {
            $this->logger->critical('Social login error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->appendJs(
                'window.opener.location.href=' . json_encode($this->url->getUrl('customer/account/login')) . ';window.close();'
            );
        }
    }

    private function appendJs(?string $content = null): Raw
    {
        return $this->resultRawFactory->create()->setContents(
            '<script>' . ($content ?? 'window.opener.location.reload(true);window.close();') . '</script>'
        );
    }

    private function checkAdapterIdActive(string $adapterId): bool
    {
        return array_key_exists($adapterId, $this->socialHelper->getActiveSocialNetworksList());
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
