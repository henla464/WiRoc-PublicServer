<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="NewCompetition", type="object", required={"name"})
 */
class Competition
{
	public static $tableName = 'Competitions';
		
    public $id;
    /**
     * @SWG\Property()
     * @var string
     */
    public $name;
   
	public $updateTime;
    public $createdTime;
}

/**
 *  @SWG\Definition(
 *   definition="Competition",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewCompetition"),
 *       @SWG\Schema(
 *           required={"id", "createdTime"},
 *           @SWG\Property(property="id", format="int64", type="integer"),
 * 			 @SWG\Property(property="updateTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="createdTime", format="date-time", type="string")
 *       )
 *   }
 * )
 */

