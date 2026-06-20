<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="NewCompetitionAccess", type="object", required={"competitionId", "UserId"})
 */
class CompetitionAccess
{
	public static $tableName = 'CompetitionAccesses';
	
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
    public $UserId;
    public $GrantedAt;
    /**
     * @SWG\Property()
     * @var int
     */
    public $GrantedByUserId;

	public $updateTime;
    public $createdTime;
}

/**
 *  @SWG\Definition(
 *   definition="CompetitionAccess",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewCompetitionAccess"),
 *       @SWG\Schema(
 *           required={"id", "createdTime"},
 *           @SWG\Property(property="id", format="integer", type="integer"),
 *           @SWG\Property(property="GrantedAt", format="date-time", type="string"),
 *           @SWG\Property(property="GrantedByUserId", type="integer"),
 * 			 @SWG\Property(property="updateTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="createdTime", format="date-time", type="string")
 *       )
 *   }
 * )
 */

/**
 *  @SWG\Definition(
 *   definition="CompetitionAccessGrantRequest",
 *   type="object",
 *   required={"competitionId", "UserEmail"},
 *   @SWG\Property(property="competitionId", type="integer"),
 *   @SWG\Property(property="UserEmail", type="string")
 * )
 */

/**
 *  @SWG\Definition(
 *   definition="CompetitionAccessRevokeRequest",
 *   type="object",
 *   required={"competitionId", "UserId"},
 *   @SWG\Property(property="competitionId", type="integer"),
 *   @SWG\Property(property="UserId", type="integer")
 * )
 */

