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

namespace Techyouknow\SocialLogin\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Session\Config as MagentoConfig;

class SessionConfig
{
    private array $disableSessionUrls = [
        'apple.com',
        'techyouknow_redirect/social/login',
    ];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Http $request,
    ) {}

    public function aroundSetOption(MagentoConfig $subject, callable $proceed, string $option, mixed $value): mixed
    {
        if ($this->isSocialNetworkEnable() && $this->isSecureAndSameSiteCookiesEnabled()) {
            foreach ($this->disableSessionUrls as $url) {
                if (str_contains((string) $this->request->getPathInfo(), $url)) {
                    if ($option === 'session.cookie_secure') {
                        $value = 1;
                    } elseif ($option === 'session.cookie_samesite') {
                        $value = 'None';
                    }
                    break;
                }
            }
        }

        return $proceed($option, $value);
    }

    private function isSecureAndSameSiteCookiesEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue('techyouknow_social_network/adapters/apple/change_session');
    }

    private function isSocialNetworkEnable(): bool
    {
        return (bool) $this->scopeConfig->getValue('techyouknow_social_network/general/enable');
    }
}
