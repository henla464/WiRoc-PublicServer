<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="NewReleaseStatus", type="object", required={"displayName", "keyName", "sortOrder"})
 */
class ReleaseStatus
{
	public static $tableName = 'ReleaseStatuses';
	
    public $id;
    /**
     * @var string
     * @SWG\Property()
     */
    public $displayName;
    /**
     * @var string
     * @SWG\Property()
     */
    public $keyName;
      /**
     * @var int
     * @SWG\Property()
     */
    public $sortOrder;
    

	public $updateTime;
    public $createdTime;
}

/**
 *  @SWG\Definition(
 *   definition="ReleaseStatus",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewReleaseStatus"),
 *       @SWG\Schema(
 *           required={"id"},
 *           @SWG\Property(property="id", format="int64", type="integer"),
 * 			 @SWG\Property(property="updateTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="createdTime", format="date-time", type="string")
 *       )
 *   }
 * )
 */

