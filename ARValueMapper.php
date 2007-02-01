<?php

require_once("schema/ARField.php");

/**
 * Binds a value to a schema field
 * (it is just an ordinary row field value container)
 *
 * @package activerecord
 * @author Saulius Rupainis <saulius@integry.net>
 */
class ARValueMapper
{
	/**
	 * Schema field instance
	 *
	 * @var ARSchemaField
	 */
	private $field = null;

	/**
	 * Value assigned to a schema field
	 *
	 * @var mixed
	 */
	private $value = null;

	/**
	 * Initial value (reset after saving)
	 *
	 * Necessary whena record primary key values change and record needs to be updated
	 *
	 * @var mixed
	 */
	private $initialID = null;

	/**
	 * A mark to indicate if a record was modified
	 *
	 * @var bool
	 */
	private $isModified = false;

	private $isNull = false;

	public function __construct(ARField $field, $value = null)
	{
		$this->field = $field;
		$this->value = $value;
	}

	/**
	 * Gets a related schema field
	 *
	 * @return ARField
	 */
	public function getField()
	{
		return $this->field;
	}

	/**
	 * Assignes a value to a field
	 *
	 * @param mixed $value
	 */
	public function set($value, $markAsModified = true)
	{
		if ($this->field instanceof ARForeignKey && !($value instanceof ActiveRecord))
		{
			throw new ARException("Invalid value parameter: must be an instance of ActiveRecord");
		}
		//if ($this->field->getDataType() instanceof ARArray && !is_array($value))
		//{
		//	throw new ARException("Invalid value parameter: must be an array");
		//}
		
		if ($this->field instanceof ARForeignKey && !$this->initialID)
		{
			$this->initialID = $value->getID();
		}

		$this->value = $value;
		
		if ($markAsModified)
		{
			$this->isModified = true;
		}
	}

	public function setNull()
	{
		$this->isNull = true;
		$this->isModified = true;
	}

	public function isNull()
	{
		return $this->isNull;
	}

	/**
	 * Gets a field value
	 *
	 * @return mixed
	 */
	public function get()
	{
		return $this->value;
	}

	/**
	 * Gets initial field value
	 *
	 * @return mixed
	 */
	public function getInitialID()
	{
		if (!$this->initialID)
		{
			return $this->value->getID();  
		}
		else
		{
			return $this->initialID;  
		}		
	}

	/**
	 * Returns true if field is being modified
	 *
	 * @return bool
	 */
	public function isModified()
	{
		return $this->isModified;
	}

	/**
	 * Marks the field as not modified (after saving, etc.)
	 */
	public function resetModifiedStatus()
	{
		$this->isModified = false;
		if ($this->field instanceof ARForeignKey)
		{
			$this->initialID = $this->value->getID();		  
		}
	}

	/**
	 * Checks if this instance has an assigned value
	 *
	 * @return bool
	 */
	public function hasValue()
	{
		if ($this->value != null)
		{
			return true;
		}
		else
		{
			return false;
		}
	}
}

?>