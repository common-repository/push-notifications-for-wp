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

namespace DeliteStudio\Jose\Component\Signature\Algorithm;

use DeliteStudio\Jose\Component\Core\Algorithm;
use DeliteStudio\Jose\Component\Core\JWK;

interface MacAlgorithm extends Algorithm
{
    /**
     * Sign the input.
     *
     * @param JWK    $key   The private key used to hash the data
     * @param string $input The input
     */
    public function hash(JWK $key, string $input): string;

    /**
     * Verify the signature of data.
     *
     * @param JWK    $key       The private key used to hash the data
     * @param string $input     The input
     * @param string $signature The signature to verify
     */
    public function verify(JWK $key, string $input, string $signature): bool;
}
