<?php
/**
 * Represents base class for orders
 *
 * @author    Youstice
 * @copyright (c) 2014, Youstice
 * @license   http://www.apache.org/licenses/LICENSE-2.0.html  Apache License, Version 2.0
 */

class YousticeReportsBaseReport {

	protected $exists = false;
	protected $data = array();

	public function __construct($data = array())
	{
		if (isset($data) && is_array($data) && count($data))
		{
			$this->exists = true;
			$this->data = $data;
		}
	}

	public function exists()
	{
		return $this->exists && $this->getStatus() !== null;
	}

	/**
	 * Creating another new report is allowed only on this conditions
	 * @return boolean
	 */
	public function canCreateNew()
	{
		if (!$this->exists())
			return true;
		
		$status = $this->getStatus();

		if (Tools::strtolower($status) == 'terminated')
			return true;

		if ($status == 'Problem reported')
			return true;

		return false;
	}

	public function getStatus()
	{
		if (count($this->data) && isset($this->data['status']))
			return $this->data['status'];

		if(isset($this->data['created_at']) && strtotime($this->data['created_at']) + 600 > time())
			return 'Problem reported';
		
		return null;
	}

	public function getRemainingTime()
	{
		$remaining_time = isset($this->data['remaining_time']) ? $this->data['remaining_time'] : 0;

		if (!isset($this->data['updated_at']))
			return 0;

		$actual_remaining_time = $remaining_time - (time() - strtotime($this->data['updated_at']));

		return $actual_remaining_time >= 0 ? $actual_remaining_time : 0;
	}

	public function toArray()
	{
		return $this->data;
	}

}
