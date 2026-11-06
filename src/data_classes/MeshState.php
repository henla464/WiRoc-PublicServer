<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="NewMeshState", type="object", required={"BTAddress", "snapshotTime"})
 */
class MeshState
{
    public static $tableName = 'MeshStates';

    public $id;
    /** @SWG\Property() @var string */
    public $BTAddress;
    /** @SWG\Property() @var integer */
    public $snapshotTime;
    /** @SWG\Property() @var boolean */
    public $isRoot;
    /** @SWG\Property() @var integer */
    public $addr;
    /** @SWG\Property() @var integer */
    public $state;
    /** @SWG\Property() @var integer */
    public $parentAddr;
    /** @SWG\Property() @var integer */
    public $hopCount;
    /** @SWG\Property() @var integer */
    public $pathCost;
    /** @SWG\Property() @var integer */
    public $neighbourCount;
    /** @SWG\Property() @var integer */
    public $transmitQueue;
    /** @SWG\Property() @var integer */
    public $allocatedAddresses;
    /** @SWG\Property() @var integer */
    public $epoch;
    /** @SWG\Property() @var number */
    public $overheadPercent;
    /** @SWG\Property() @var integer */
    public $ackTimeoutMs;
    /** @SWG\Property() @var boolean */
    public $standby;
    public $updateTime;
    public $createdTime;
}
