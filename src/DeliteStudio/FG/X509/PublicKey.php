<?php
/*
 * This file is part of the PHPASN1 library.
 *
 * Copyright © Friedrich Große <friedrich.grosse@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DeliteStudio\FG\X509;

use DeliteStudio\FG\ASN1\OID;
use DeliteStudio\FG\ASN1\Universal\NullObject;
use DeliteStudio\FG\ASN1\Universal\Sequence;
use DeliteStudio\FG\ASN1\Universal\BitString;
use DeliteStudio\FG\ASN1\Universal\ObjectIdentifier;

class PublicKey extends Sequence
{
    /**
     * @param string $hexKey
     * @param \DeliteStudio\FG\ASN1\ASNObject|string $algorithmIdentifierString
     */
    public function __construct($hexKey, $algorithmIdentifierString = OID::RSA_ENCRYPTION)
    {
        parent::__construct(
            new Sequence(
                new ObjectIdentifier($algorithmIdentifierString),
                new NullObject()
            ),
            new BitString($hexKey)
        );
    }
}
