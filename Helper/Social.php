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

namespace Techyouknow\SocialLogin\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Social extends \Magento\Framework\App\Helper\AbstractHelper
{
    const CONFIG_ROOT_PATH = 'techyouknow_social_network';
    const CONFIG_ADAPTERS  = 'adapters';
    const CONFIG_APP_ID    = 'app_id';
    const CONFIG_APP_SECRET = 'app_secret';
    const CONFIG_TEAM_ID   = 'team_id';
    const CONFIG_KEY_ID    = 'key_id';
    const CONFIG_KEY_CONTENT = 'key_content';

    public function __construct(
        Context $context,
        private readonly \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    public function isSocialNetworkEnable(): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::CONFIG_ROOT_PATH . '/general/enable',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getAdapterConfigValue(string $adapterId, string $key): ?string
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_ROOT_PATH . '/' . self::CONFIG_ADAPTERS . '/' . $adapterId . '/' . $key,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function isAdapterEnable(string $adapterId): bool
    {
        return (bool) $this->getAdapterConfigValue($adapterId, 'enable');
    }

    public function getSocialNetworksList(): array
    {
        return [
            'google'    => 'Google',
            'facebook'  => 'Facebook',
            'apple'     => 'Apple',
            'instagram' => 'Instagram',
            'twitter'   => 'Twitter',
            'amazon'    => 'Amazon',
            'yahoo'     => 'Yahoo',
            'linkedin'  => 'LinkedIn',
            'github'    => 'GitHub',
        ];
    }

    public function getActiveSocialNetworksList(): array
    {
        $enabled = [];
        foreach ($this->getSocialNetworksList() as $key => $socialNetwork) {
            if ($this->isAdapterEnable($key)) {
                $enabled[$key] = $socialNetwork;
            }
        }
        return $enabled;
    }

    public function getSocialNetwork(string $adapterId): string
    {
        return $this->getSocialNetworksList()[$adapterId];
    }

    public function getHybridauthConfig(string $adapterId): array
    {
        $providers = [];

        foreach ($this->getActiveSocialNetworksList() as $key => $socialNetwork) {
            $providerConfig = [
                'enabled' => true,
                'keys'    => [
                    'id' => $this->getAdapterConfigValue($key, self::CONFIG_APP_ID),
                ],
            ];

            if ($socialNetwork === 'Apple') {
                $providerConfig['keys']['team_id']     = $this->getAdapterConfigValue($key, self::CONFIG_TEAM_ID);
                $providerConfig['keys']['key_id']      = $this->getAdapterConfigValue($key, self::CONFIG_KEY_ID);
                $providerConfig['keys']['key_content'] = $this->getAdapterConfigValue($key, self::CONFIG_KEY_CONTENT);
                $providerConfig['scope'] = 'name email';
            } else {
                $providerConfig['keys']['secret'] = $this->getAdapterConfigValue($key, self::CONFIG_APP_SECRET);
            }

            $providers[$socialNetwork] = $providerConfig;
        }

        return [
            'callback'  => $this->getSocialRedirectUrl($adapterId),
            'providers' => $providers,
        ];
    }

    public function getSocialRedirectUrl(string $adapterId): string
    {
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        return $baseUrl . 'techyouknow_redirect/social/login/provider/' . $adapterId;
    }
}
