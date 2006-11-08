<?php

/**
 * ActiveRecord query / action logger
 *
 */
class ARLogger
{
	private $isEnabled = true;
	private $startTime = null;
	private $log = array();
	private $outputType = 1;
	private $logFileName = "";

	const OUTPUT_FILE = 1;
	const OUTPUT_SCREEN = 2;

	const LOG_ACTION = 1;
	const LOG_OBJECT = 2;
	const LOG_QUERY = 3;

	public function __construct()
	{
		$this->startTime = microtime(true);
	}

	public function logQuery($queryStr)
	{
		$this->addLogItem($queryStr, self::LOG_QUERY);
	}

	public function logObject(ActiveRecord $ARInstance, $restoredFromPool = false)
	{
		$ID = $ARInstance->getID();

		if ($ARInstance->isLoaded())
		{
			$loadedStr = "data is loaded";
		}
		else
		{
			$loadedStr = "no data loaded";
		}

		if ($restoredFromPool)
		{
			$msg = "Restoring object from pool (".get_class($ARInstance).":$ID, $loadedStr)";
		}
		else
		{
			$msg = "Building new object (".get_class($ARInstance).":$ID, $loadedStr)";
		}
		$this->addLogItem($msg, self::LOG_OBJECT);
	}

	private function addLogItem($msg, $logType)
	{
		$logItem = array("type" => $logType, "msg" => $msg);
		$this->log[] = $logItem;

		$logData = $this->startTime." | ".$this->createLogItemStr($logItem);
		if (empty($this->logFileName))
		{
			$filePath = dirname(__FILE__).DIRECTORY_SEPARATOR."activerecord.log";
		}
		else
		{
			$filePath = $this->logFileName;
		}
		file_put_contents($filePath, $logData, FILE_APPEND);
	}

	private function createLogItemStr($itemArray)
	{
		$str = "";
		$str = $itemArray['msg']."\n";
		return $str;
	}

	public function logAction($actionInfo)
	{
		$this->addLogItem($actionInfo, self::LOG_ACTION);
	}

	/*
	public function output() {
	$logData = "";
	$logID = $this->startTime;

	foreach ($this->log as $item) {
	$logData .=  $logID . " | " . $this->createLogItemStr($item);
	}
	if (empty($this->logFileName)) {
	$filePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . "activerecord.log";
	} else {
	$filePath = $this->logFileName;
	}
	file_put_contents($filePath, $logData, FILE_APPEND);
	return $logData;
	}
	 */

	public function enable()
	{
		$this->isEnabled = true;
	}

	public function disable()
	{
		$this->isEnabled = false;
	}
	
	public function setLogFileName($path)
	{
		$this->logFileName = $path;
	}
}

?>