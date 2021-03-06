<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Component\DocumentManager;

use PHPCR\NodeInterface;

/**
 * Ensures that node names are unique.
 */
class NameResolver
{
    /**
     * @param NodeInterface $parentNode
     * @param string $name
     * @param null|NodeInterface $forNode
     *
     * @return string
     */
    public function resolveName(NodeInterface $parentNode, $name, $forNode = null)
    {
        if ($forNode && $parentNode->hasNode($name) && $parentNode->getNode($name) == $forNode) {
            return $name;
        }

        $index = 0;
        $baseName = $name;
        do {
            if ($index > 0) {
                $name = $baseName . '-' . $index;
            }

            $hasChild = $parentNode->hasNode($name);

            ++$index;
        } while ($hasChild);

        return $name;
    }
}
