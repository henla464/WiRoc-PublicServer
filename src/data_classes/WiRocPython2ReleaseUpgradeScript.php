<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="NewWiRocPython2ReleaseUpgradeScript", type="object", required={"releaseId", "scriptText", "scriptNote"})
 */
class WiRocPython2ReleaseUpgradeScript
{
	public static $tableName = 'WiRocPython2ReleaseUpgradeScripts';
	
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
 *   definition="WiRocPython2ReleaseUpgradeScript",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewWiRocPython2ReleaseUpgradeScript"),
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
 *   definition="UpsertWiRocPython2ReleaseUpgradeScript",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewWiRocPython2ReleaseUpgradeScript"),
 *       @SWG\Schema(
 *           required={"id"},
 *           @SWG\Property(property="id", format="integer", type="integer")
 *       )
 *   }
 * )
 */
