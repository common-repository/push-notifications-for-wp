<?php

declare(strict_types=1);

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2014-2020 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
 */

namespace DeliteStudio\Jose\Component\KeyManagement\Analyzer;

use DeliteStudio\Base64Url\Base64Url;
use DeliteStudio\Jose\Component\Core\JWK;

final class HS512KeyAnalyzer implements KeyAnalyzer
{
    public function analyze(JWK $jwk, MessageBag $bag): void
    {
        if ('oct' !== $jwk->get('kty')) {
            return;
        }
        if (!$jwk->has('alg') || 'HS512' !== $jwk->get('alg')) {
            return;
        }
        $k = Base64Url::decode($jwk->get('k'));
        $kLength = 8 * mb_strlen($k, '8bit');
        if ($kLength < 512) {
            $bag->add(Message::high('HS512 algorithm requires at least 512 bits key length.'));
        }
    }
}
