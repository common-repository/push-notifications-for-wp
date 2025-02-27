<?php
/*
 * This file is part of the PHPASN1 library.
 *
 * Copyright © Friedrich Große <friedrich.grosse@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DeliteStudio\FG\ASN1\Universal;

use DeliteStudio\FG\ASN1\AbstractString;
use DeliteStudio\FG\ASN1\Identifier;

class GraphicString extends AbstractString
{
    /**
     * Creates a new ASN.1 Graphic String.
     * TODO The encodable characters of this type are not yet checked.
     *
     * @param string $string
     */
    public function __construct($string)
    {
        $this->value = $string;
        $this->allowAll();
    }

    public function getType()
    {
        return Identifier::GRAPHIC_STRING;
    }
}
