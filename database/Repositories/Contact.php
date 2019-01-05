<?php

namespace Repositories;

use Doctrine\ORM\EntityRepository;

use Auth, D2EM;

use Entities\{
    Contact             as ContactEntity,
    ContactGroup        as ContactGroupEntity
};

/**
 * Contact
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class Contact extends EntityRepository
{



    /**
     * Gets role names array for contacts.
     *
     * Function gets arrays of role names for contacts by given contacts
     * id list. Return sturcture:
     * $array = [
     *   contact_id0 => [ name0, name1, ..],
     *   contact_id1 => [ name0, name1, ..],
     *   ...
     * ];
     *
     * @param array $ids Contacts ID list to get roles names
     * @return array
     */
    public function getRolesByIds( $ids )
    {
        if( !count( $ids ) )
            return [];

        $dql = "SELECT c.id as contact_id,
                        cg.name as name
                FROM \\Entities\\Contact c
                LEFT JOIN c.Groups cg
                WHERE cg.type = '" . ContactGroupEntity::TYPE_ROLE . "'
                AND c.id IN ('" . implode( "','", array_values( $ids ) ) . "')";

        $data = [];


        foreach( $this->getEntityManager()->createQuery( $dql )->getArrayResult() as $row )
            $data[ $row['contact_id'] ][] = $row['name'];

        return $data;
    }

    /**
     * Gets group types and names array for contacts.
     *
     * Function gets arrays of group types and names for contacts by given
     * contacts id list. Return sturcture:
     * $array = [
     *   contact_id0 => [ [ name => name0, type => type0 ], [ name => name1, type => type1 ], ..],
     *   contact_id1 => [ [ name => name0, type => type0 ], [ name => name1, type => type1 ], ..],
     *   ...
     * ];
     *
     * @param array $ids Contacts ID list to get roles names
     * @return array
     */
    public function getGroupsByIds( $ids )
    {

        $dql = "SELECT c.id as contact_id,
                        cg.name as name,
                        cg.type as type
                FROM \\Entities\\Contact c
                LEFT JOIN c.Groups cg
                WHERE cg.type != '" . ContactGroupEntity::TYPE_ROLE . "'
                AND c.id IN ('" . implode( "','", array_values( $ids ) ) . "')";

        $data = [];
        foreach( $this->getEntityManager()->createQuery( $dql )->getArrayResult() as $row )
            $data[ $row['contact_id'] ][] = ['type' => $row['type'], 'name' => $row['name'] ];

        return $data;
    }


    /**
     * Find contacts by contact email
     *
     * @param  string $email The email to search for
     * @return \Entities\Contact[] Matching contacts
     */
    public function findByEmail( $email )
    {
        return $this->getEntityManager()->createQuery(
                "SELECT c

                 FROM \\Entities\\Contact c
  
                 WHERE c.email = :email"
            )
            ->setParameter( 'email', $email )
            ->getResult();
    }


    /**
     * Get all Contacts for listing on the frontend CRUD
     *
     * @see \IXP\Http\Controllers\Doctrine2Frontend
     *
     *
     * @param \stdClass $feParams
     * @param int|null $contactid
     * @param null $role
     * @param null $cgid
     *
     * @return array Array of Contacts (as associated arrays) (or single element if `$id` passed)
     *
     */
    public function getAllForFeList( \stdClass $feParams, $contactid, $role = null, $cgid = null )
    {
        $where = false;

        $dql = "SELECT  c.id as id, 
                        c.name as name, 
                        c.email as email, 
                        c.phone AS phone, 
                        c.mobile AS mobile,
                        c.facilityaccess AS facilityaccess, 
                        c.mayauthorize AS mayauthorize,
                        c.lastupdated AS lastupdated, 
                        c.lastupdatedby AS lastupdatedby, 
                        c.position AS position,
                        c.creator AS creator, 
                        c.created AS created, 
                        cust.name AS customer, 
                        cust.id AS custid
                     
                  FROM Entities\\Contact c
                  LEFT JOIN c.Customer cust";

        if( config('contact_group.types.ROLE') ) {

            if( $role ){
                $dql .= " LEFT JOIN c.Groups g";
                $dql .= "  WHERE g.id = " . $role;
                $where = true;
            } elseif( $cgid ) {
                $dql .= " LEFT JOIN c.Groups cg";
                $dql .= "  WHERE cg.id = " . $cgid;
                $where = true;
            }
        }

        if( $where && $contactid ){
            $dql .= " AND";
        } else if( !$where && $contactid){
            $dql .= "  WHERE";
            $where = true;
        } else {
            $dql .= "";
        }

        if( $contactid ) {
            $dql .= " c.id = " . (int)$contactid;
        }

        $dql .= ( $where ? "" : " WHERE 1 = 1" );

        if( !Auth::getUser()->isSuperUser() ) {
            $dql .= " AND cust.id = " . Auth::getUser()->getCustomer()->getId();
        }


        if( isset( $feParams->listOrderBy ) ) {
            $dql .= " ORDER BY " . $feParams->listOrderBy . ' ';
            $dql .= isset( $feParams->listOrderByDir ) ? $feParams->listOrderByDir : 'ASC';
        }


        if( !Auth::getUser()->isSuperUser() ) {
            return $this->getEntityManager()->createQuery( $dql )->getArrayResult();
        }

        $data = $this->getEntityManager()->createQuery( $dql )->getArrayResult();

        if( config('contact_group.types.ROLE') ) {
            $data = $this->setRolesAndGroups( $data, $contactid );
        }


        return $data;
    }


    /**
     * Sets roles and groups for contacts from the data array.
     *
     * From data array gets contact ids  and loads the role and group names by ids array.
     * Then it iterates throw $data and if roles or groups was found for preview it appends
     * that data to $data array. For list only roles is appended to $data array. Function returns
     * appended $data array.
     *
     * @param array $data Data loaded form DQL query for list or view action
     * @param int   $id   The `id` of the row to load for `viewAction`. `null` if `listAction`
     * @return array
     */
    private function setRolesAndGroups( $data, $id ) {
        $ids = [];
        foreach( $data as $row ){
            $ids[] = $row['id'];
        }

        $roles = D2EM::getRepository( ContactEntity::class )->getRolesByIds( $ids );

        if( $id ){
            $groups = D2EM::getRepository( ContactEntity::class )->getGroupsByIds( $ids );
        }


        foreach( $data as $idx => $contact ) {
            if( $id ) {

                if( isset( $roles[ $contact['id'] ] ) ) {

                    asort( $roles[ $contact['id'] ] );

                    $data[ $idx ][ 'role' ] = $roles[ $contact[ 'id' ] ];
                }

                if( isset( $groups[ $contact['id'] ] ) ) {

                    asort( $groups[ $contact['id'] ] );
                    $group = "";
                    foreach( $groups[ $contact['id'] ] as $gdata ) {
                        if( $group != "" )
                            $group .= ",";


                        $group .= $gdata[ "type" ] . " : " . $gdata['name'];
                    }

                    $data[$idx]['group'] = $group;
                }
            } else {

                if( isset( $roles[ $contact['id'] ] ) )
                {
                    asort( $roles[ $contact['id'] ] );
                    $data[$idx]['role'] = $roles[ $contact[ 'id' ] ];
                } else{
                    $data[$idx]['role'] = [];
                }


            }
        }

        return $data;
    }
}
