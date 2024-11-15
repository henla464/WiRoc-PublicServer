<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="NewWiRocBLEAPIReleaseUpgradeScript", type="object", required={"releaseId", "scriptText", "scriptNote"})
 */
class WiRocBLEAPIReleaseUpgradeScript
{
	public static $tableName = 'WiRocBLEAPIReleaseUpgradeScripts';
	
    public $id;
    /**
     * @SWG\Property()
     * @var int
     */
    public $releaseId;
    /**
     * @SWG\Property()
     * @var string
     */
    public $scriptText;
    /**
     * @SWG\Property()
     * @var string
     */
    public $scriptNote;

	public $updateTime;
    public $createdTime;
    public $releaseName;
    public $versionNumber;
}

/**
 *  @SWG\Definition(
 *   definition="WiRocBLEAPIReleaseUpgradeScript",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewWiRocBLEAPIReleaseUpgradeScript"),
 *       @SWG\Schema(
 *           required={"id"},
 *           @SWG\Property(property="id", format="integer", type="integer"),
 * 			 @SWG\Property(property="updateTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="createdTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="releaseName", type="string"),
 *           @SWG\Property(property="versionNumber", type="integer")
 *       )
 *   }
 * )
 */

/**
 *  @SWG\Definition(
 *   definition="UpsertWiRocBLEAPIReleaseUpgradeScript",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewWiRocBLEAPIReleaseUpgradeScript"),
 *       @SWG\Schema(
 *           required={"id"},
 *           @SWG\Property(property="id", format="integer", type="integer")			
 *       )
 *   }
 * )
 */

