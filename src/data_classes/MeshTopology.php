<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="NewMeshTopology", type="object", required={"BTAddress", "snapshotTime", "nodes"})
 */
class MeshTopology
{
    public static $tableName = 'MeshTopologies';

    public $id;
    /**
     * @SWG\Property()
     * @var string
     */
    public $BTAddress;
    /**
     * @SWG\Property()
     * @var integer
     */
    public $snapshotTime;
    /**
     * @SWG\Property()
     * @var boolean
     */
    public $isRoot;
    /**
     * @SWG\Property()
     * @var string
     */
    public $nodes;
    public $updateTime;
    public $createdTime;
}

/**
 * A mesh node resolved to its competition control position.
 *
 * @SWG\Definition(definition="MeshTopologyNode", type="object")
 */
class MeshTopologyNode
{
    public $id;
    /**
     * @SWG\Property()
     * @var string
     */
    public $BTAddress;
    /**
     * @SWG\Property()
     * @var string
     */
    public $deviceId;
    /**
     * @SWG\Property()
     * @var string
     */
    public $name;
    /**
     * @SWG\Property()
     * @var integer
     */
    public $controlId;
    /**
     * @SWG\Property()
     * @var integer
     */
    public $controlNumber;
    /**
     * @SWG\Property()
     * @var string
     */
    public $controlType;
    /**
     * @SWG\Property()
     * @var float
     */
    public $mapX;
    /**
     * @SWG\Property()
     * @var float
     */
    public $mapY;
    /**
     * @SWG\Property()
     * @var boolean
     */
    public $recentlyReported;
    /**
     * @SWG\Property()
     * @var boolean
     */
    public $connectedToInternet;
}
