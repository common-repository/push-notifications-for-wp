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

use DeliteStudio\Jose\Component\Core\JWK;

final class KeyIdentifierAnalyzer implements KeyAnalyzer
{
    public function analyze(JWK $jwk, MessageBag $bag): void
    {
        if (!$jwk->has('kid')) {
            $bag->add(Message::medium('The parameter "kid" should be added.'));
        }
    }
}
