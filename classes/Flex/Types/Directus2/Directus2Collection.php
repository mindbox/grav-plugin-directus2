<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Plugin\Directus2\Flex\Types\Directus2;

use Grav\Common\Flex\Types\Generic\GenericCollection;
use Doctrine\Common\Collections\Criteria;

/**
 * Class Directus2Collection
 * @package Grav\Common\Flex\Generic
 *
 * @extends FlexCollection<string,GenericObject>
 */
class Directus2Collection extends GenericCollection
{
    // custom filter to test for multiple values on the same key (if field is type string)
    public function filterByArray( $key, $array ): Directus2Collection
    {
        $expr = Criteria::expr();
        $criteria = Criteria::create();
        // https://www.doctrine-project.org/projects/doctrine-collections/en/1.6/expressions.html#orwhere

        foreach ( $array as $item ) {
            $criteria->orWhere( $expr->eq( $key, $item ) );
        }

        return $this->matching( $criteria );
    }

    // custom filter for multiple values of the same key (if field is type array)
    public function inArray($key, array $values): Directus2Collection
    {
        $mappedCollection = $this->map(function($obj) use ($values, $key) {
            foreach ($values as $value) {
                if (in_array($value, $obj->getProperty($key, []))) {
                    return $obj;
                }
            }
            return false; // if element doesnt match what we want, make it false
        });


        $array_with_elements_remove = array_filter($mappedCollection->getElements(), function($e){
            return $e; //remove all the unwanted elements that are false
        });

        return $this->createFrom(array_values($array_with_elements_remove));
    }
}
