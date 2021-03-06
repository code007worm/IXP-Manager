<?php

/*
 * Copyright (C) 2009 - 2019 Internet Neutral Exchange Association Company Limited By Guarantee.
 * All Rights Reserved.
 *
 * This file is part of IXP Manager.
 *
 * IXP Manager is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, version v2.0 of the License.
 *
 * IXP Manager is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GpNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License v2.0
 * along with IXP Manager.  If not, see:
 *
 * http://www.gnu.org/licenses/gpl-2.0.html
 */

namespace Repositories;

use Doctrine\ORM\EntityRepository;

/**
 * Vendor
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class Vendor extends EntityRepository
{
    /**
     * Get all lcoation (or a particular one) for listing on the frontend CRUD
     *
     * @see \IXP\Http\Controller\Doctrine2Frontend
     *
     *
     * @param \stdClass $feParams
     * @param int|null $id
     * @return array Array of lcoation (as associated arrays) (or single element if `$id` passed)
     */
    public function getAllForFeList( \stdClass $feParams, int $id = null )
    {
        $dql = "SELECT  v.id AS id, 
                        v.name AS name, 
                        v.shortname AS shortname, 
                        v.nagios_name AS nagios_name, 
                        v.bundle_name AS bundle_name
                FROM Entities\\Vendor v
                WHERE 1 = 1";

        if( $id ) {
            $dql .= " AND v.id = " . (int)$id;
        }

        if( isset( $feParams->listOrderBy ) ) {
            $dql .= " ORDER BY " . $feParams->listOrderBy . ' ';
            $dql .= isset( $feParams->listOrderByDir ) ? $feParams->listOrderByDir : 'ASC';
        }

        $query = $this->getEntityManager()->createQuery( $dql );

        return $query->getArrayResult();
    }

    /**
     * Return an array of all vendors names where the array key is the vendor id.
     * @return array An array of all vendors names with the vendor id as the key.
     */
    public function getAsArray(): array {
        $vendors = [];
        foreach( self::findAll() as $vendor ) {
            $vendors[ $vendor->getId() ] = $vendor->getName();
        }

        return $vendors;
    }
}
