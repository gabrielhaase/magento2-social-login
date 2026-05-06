/*
 * MIT License
 *
 * Copyright (c) 2023 Techyouknow
 */
define(['jquery'], function ($) {
    'use strict';

    return function (config, element) {
        var $taxvat = $(element).find('#taxvat');

        $taxvat.on('input', function () {
            var raw = this.value.replace(/\D/g, '').slice(0, 11);
            var formatted = raw
                .replace(/^(\d{3})(\d)/, '$1.$2')
                .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
                .replace(/\.(\d{3})(\d)/, '.$1-$2');
            this.value = formatted;
        });

        $(element).on('submit', function () {
            // Envia apenas os dígitos para o servidor
            $taxvat.val($taxvat.val().replace(/\D/g, ''));
        });
    };
});
