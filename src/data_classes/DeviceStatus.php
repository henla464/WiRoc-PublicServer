<?php
use Swagger\Annotations as SWG;

/**
 * @SWG\Definition(definition="NewDeviceStatus", type="object", required={"BTAddress", "batteryLevel", "siStationNumber", "noOfLoraMsgSentNotAcked"})
 */
class DeviceStatus
{
	public static $tableName = 'DeviceStatuses';
		
    public $id;
    /**
     * @SWG\Property()
     * @var string
     */
    public $BTAddress;
    /**
     * @var integer
     * @SWG\Property()
     */
    public $batteryLevel;
    /**
     * @var integer
     * @SWG\Property()
     */
    public $siStationNumber;
    /**
     * @var integer
     * @SWG\Property()
     */
    public $noOfLoraMsgSentNotAcked;
    /**
     * @var boolean
     * @SWG\Property()
     */
	public $allLoraPunchesSentOK;
    /**
     * @var boolean
     * @SWG\Property()
     */
	public $internalSRRREDEnabled;
     /**
     * @var boolean
     * @SWG\Property()
     */
	public $internalSRRREDAckEnabled;
    /**
     * @var boolean
     * @SWG\Property()
     */
	public $internalSRRBLUEEnabled;
    /**
     * @var boolean
     * @SWG\Property()
     */
    public $internalSRRBLUEAckEnabled;
    /**
     * @var boolean
     * @SWG\Property()
     */    
    public $SRRDongleREDFound;
    /**
     * @var boolean
     * @SWG\Property()
     */    
    public $SRRDongleREDAckEnabled;
    /**
     * @var boolean
     * @SWG\Property()
     */
    public $SRRDongleBLUEFound;
    /**
     * @var boolean
     * @SWG\Property()
     */
    public $SRRDongleBLUEAckEnabled;
    /**
     * @var boolean
     * @SWG\Property()
     */
    public  $internalSRRREDDirection; 
    /**
     * @var boolean
     * @SWG\Property()
     */
    public  $internalSRRBLUEDirection;
    /**
     * @var boolean
     * @SWG\Property()
     */
    public  $tcpIPSirapEnabled;
    /**
     * @var boolean
     * @SWG\Property()
     */
    public  $serialPortBaudRate;
    /**
     * @var boolean
     * @SWG\Property()
     */
    public  $serialPortDirection;
    /**
     * @var boolean
     * @SWG\Property()
     */
    public  $siMasterConnectedOnUSB1;
    /**
     * @var boolean
     * @SWG\Property()
     */
    public  $siMasterConnectedOnUSB2;
    

	public $updateTime;
    public $createdTime;
}

/**
 *  @SWG\Definition(
 *   definition="DeviceStatus",
 *   type="object",
 *   allOf={
 *       @SWG\Schema(ref="#/definitions/NewDeviceStatus"),
 *       @SWG\Schema(
 *           required={"id", "createdTime"},
 *           @SWG\Property(property="id", format="integer", type="integer"),
 * 			 @SWG\Property(property="updateTime", format="date-time", type="string"),
 * 			 @SWG\Property(property="createdTime", format="date-time", type="string")
 *       )
 *   }
 * )
 */

