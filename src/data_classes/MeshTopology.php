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
