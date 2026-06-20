<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="NewControl", type="object", required={"competitionId", "controlNumber", "name"})
 */
class Control
{
	public static $tableName = 'Controls';
		
    public $id;
    /**
     * @SWG\Property()
     * @var int
     */
    public $competitionId;
    /**
     * @SWG\Property()
     * @var int
     */
    public $controlNumber;
    /**
     * @SWG\Property()
     * @var string
     */
    public $name;
    /**
     * @SWG\Property()
     * @var string
     */
    public $description;

	public $updateTime;
    public $createdTime;
}

/**
 *  @SWG\Definition(
 *   definition="Control",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewControl"),
 *       @SWG\Schema(
 *           required={"id", "createdTime"},
 *           @SWG\Property(property="id", format="integer", type="integer"),
 * 			 @SWG\Property(property="updateTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="createdTime", format="date-time", type="string")
 *       )
 *   }
 * )
 */

