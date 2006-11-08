<?php

/**
 * A node of a hierarchial database record structure (preorder tree traversal implementation)
 * 
 * <code>
 * //Defining a new class for some table
 * class Catalog
 * {
 *    public static function defineSchema($className = __CLASS__)
 *    {
 *        // <strog>Note:</strong> The folowing methods must be called in an exact order as shown in example.
 *        // 1. Get a schema instance, 
 *        // 2. set a schema name, 
 *        // 3. call a parent::defineSchema() to register schema fields needed for a hierarchial data structure
 *        // 4. Add your own fields if needed
 *        $schema = self::getSchemaInstance($className);
 *		  $schema->setName("Catalog");
 *		  
 *		  parent::defineSchema($className);
 *		  $schema->registerField(new ARField("name", Varchar::instance(40)));
 *		  $schema->registerField(new ARField("description", Varchar::instance(200)));
 *    }
 * }
 * 
 * // Retrieving a subtree
 * $catalog = ARTreeNode::getRootNode("Catalog");
 * $catalog->loadChildNodes();
 * 
 * // or...
 * $catalog = ARTreeNode::getInstanceByID("Catalog", $catalogNodeID);
 * $catalog->loadChildNodes();
 * 
 * $childList = $catalog->getChildNodes();
 * 
 * // Inserting a new node
 * $parent = getParentNodeFromSomewhere();
 * $catalogNode = ARTreeNode::getNewInstance("Catalog", $parent);
 * $catalogNode->name->set("This is my new catalog node!");
 * $catalogNode->name->set("This node will be created as child for a gived $parent instance");
 * $catalogNode->save();
 * 
 * // Deleting a node and all its childs
 * ARTreeNode::deleteByID("Catalog", $catalogNodeID);
 * 
 * </code>
 *
 * @link http://www.sitepoint.com/article/hierarchical-data-database/
 * @author Saulius Rupainis <saulius@integry.net>
 * @package activerecord.util.tree
 */
class ARTreeNode extends ActiveRecord
{
	/**
	 * Table field name for left value container of tree traversal order
	 *
	 */
	const LEFT_NODE_FIELD_NAME = 'lft';
	
	/**
	 * Table field name for right value container of tree traversal order
	 *
	 */
	const RIGHT_NODE_FIELD_NAME = 'rgt';
	
	/**
	 * The name of table field that represents a parent node ID
	 *
	 */
	const PARENT_NODE_FIELD_NAME = 'parentNodeID';
	const LOAD_CHILD_RECORDS = false;
	
	/**
	 * Root node ID
	 *
	 */
	const ROOT_ID = 0;
	
	/**
	 * Child node container
	 *
	 * @var ARTreeNode[]
	 */
	private $childList = null;
	
	/**
	 * Indicator wheather child nodes are loaded or not for this node
	 *
	 * @var bool
	 */
	private $isChildNodeListLoaded = false;
	
	/**
	 * Gets a persisted record object
	 *
	 * @param string $className
	 * @param mixed $recordID
	 * @param bool $loadRecordData
	 * @param bool $loadReferencedRecords
	 * @param bool $loadChildRecords
	 * @return ARTreeNode
	 */
	public static function getInstanceByID($className, $recordID, $loadRecordData = false, $loadReferencedRecords = false, $loadChildRecords = false)
	{
		$instance = parent::getInstanceByID($className, $recordID, $loadRecordData, $loadReferencedRecords);

		if ($loadChildRecords)
		{
			$instance->loadChildNodes($loadReferencedRecords);
		}
		return $instance;
	}
	
	public static function getNewInstance($className, ARTreeNode $parentNode)
	{
		$instance = parent::getNewInstance($className);
		$instance->setParentNode($parentNode);
		return $instance;
	}
	
	public function loadChildNodes($loadReferencedRecords = false)
	{
		$className = get_class($this);
		
		$nodeFilter = new ARSelectFilter();
		$cond = new OperatorCond(new ArFieldHandle($className, self::LEFT_NODE_FIELD_NAME), $this->getField(self::LEFT_NODE_FIELD_NAME)->get(), ">");
		$cond->addAND(new OperatorCond(new ArFieldHandle($className, self::RIGHT_NODE_FIELD_NAME), $this->getField(self::RIGHT_NODE_FIELD_NAME)->get(), "<"));
		$nodeFilter->setCondition($cond);
		$nodeFilter->setOrder(new ArFieldHandle($className, self::LEFT_NODE_FIELD_NAME));
		
		$childList = ActiveRecord::getRecordSet($className, $nodeFilter, $loadReferencedRecords);
		$indexedNodeList = array();
		$indexedNodeList[$this->getID()] = $this;
			
		foreach ($childList as $child)
		{
			$nodeId = $child->getID();
			$indexedNodeList[$nodeId] = $child;
		}
		foreach ($childList as $child)
		{
			$parentId = $child->getParentNode()->getID();
			$indexedNodeList[$parentId]->registerChildNode($child);
		}
		$this->isChildNodeListLoaded = true;
	}
	
	/**
	 * Get a record set of child nodes
	 *
	 * @return ARSet
	 */
	public function getChildNodeList()
	{
		if (!$this->isChildNodeListLoaded)
		{
			$this->loadChildNodes();
		}
		return $this->childList;
	}
	
	public function getSuccessorList()
	{
		
	}
	
	public function save()
	{
		if (!$this->hasID())
		{
			// Inserting new node
			$parentNode = $this->getField(self::PARENT_NODE_FIELD_NAME)->get();
			$parentNode->load();
			$parentRightValue = $parentNode->getFieldValue(self::RIGHT_NODE_FIELD_NAME);
			$nodeLeftValue = $parentRightValue;
			$nodeRightValue = $nodeLeftValue + 1;
		
			$tableName = self::getSchemaInstance(get_class($this))->getName();
			$db = self::getDBConnection();	
			$db->executeUpdate("UPDATE " . $tableName . " SET " . self::RIGHT_NODE_FIELD_NAME . " = "  . self::RIGHT_NODE_FIELD_NAME . " + 2 WHERE "  . self::RIGHT_NODE_FIELD_NAME . ">=" . $parentRightValue);
			$db->executeUpdate("UPDATE " . $tableName . " SET " . self::LEFT_NODE_FIELD_NAME . " = "  . self::LEFT_NODE_FIELD_NAME . " + 2 WHERE "  . self::LEFT_NODE_FIELD_NAME . ">=" . $parentRightValue);
			
			$this->getField(self::RIGHT_NODE_FIELD_NAME)->set($nodeRightValue);
			$this->getField(self::LEFT_NODE_FIELD_NAME)->set($nodeLeftValue);
		}
		parent::save();
	}
	
	public static function deleteByID($className, $recordID)
	{
		$node = self::getInstanceByID($className, $recordID, self::LOAD_DATA);
		$nodeRightValue = $node->getFieldValue(self::RIGHT_NODE_FIELD_NAME);
		
		$result = parent::deleteByID($className, $recordID);
		
		$tableName = self::getSchemaInstance($className)->getName();
		$db = self::getDBConnection();
		$db->executeUpdate("UPDATE " . $tableName . " SET " . self::RIGHT_NODE_FIELD_NAME . " = "  . self::RIGHT_NODE_FIELD_NAME . " - 2 WHERE "  . self::RIGHT_NODE_FIELD_NAME . ">=" . $nodeRightValue);
		$db->executeUpdate("UPDATE " . $tableName . " SET " . self::LEFT_NODE_FIELD_NAME . " = "  . self::LEFT_NODE_FIELD_NAME . " - 2 WHERE "  . self::LEFT_NODE_FIELD_NAME . ">=" . $nodeRightValue);
		
		return $result;
	}
	
	/**
	 * Adds (registers) a child node to this node
	 *
	 * @param ARTreeNode $childNode
	 */
	public function registerChildNode(ARTreeNode $childNode)
	{
		if ($this->childList == null)
		{
			$this->childList = new ARSet(null);
		}
		$this->childList->add($childNode);
	}
	
	/**
	 * Sets a parent node
	 *
	 * @param ARTreeNode $parentNode
	 */
	public function setParentNode(ARTreeNode $parentNode)
	{
		$this->getField(self::PARENT_NODE_FIELD_NAME)->set($parentNode);
	}
	
	/**
	 * Gets a parent node
	 *
	 * @return unknown
	 */
	public function getParentNode()
	{
		return $this->getField(self::PARENT_NODE_FIELD_NAME)->get();
	}
	
	/**
	 * Gets a tree root node
	 *
	 * @param string $className
	 * @param bool $loadChildRecords
	 * @return ARTreeNode
	 */
	public static function getRootNode($className, $loadChildRecords)
	{
		return self::getInstanceByID($className, self::ROOT_ID, false, false, true);
	}
	
	/**
	 * Gets a hierarchial path to a given tree node
	 * 
	 * The result is a sequence of record starting from a root node
	 * E.x. Consider a tree branch: Electronics -> Computers -> Laptops
	 * The path of "Laptops" will be a record set (ARSet) with a following order of records:
	 * 1. Electronics
	 * 2. Computers
	 *
	 * @param bool $loadReferencedRecords
	 * @return ARSet
	 * @see ARSet
	 */
	public function getPathNodes($loadReferencedRecords = false)
	{
		$className = get_class($this);
		$this->load();
		$leftValue = $this->getFieldValue(self::LEFT_NODE_FIELD_NAME);
		$rightValue = $this->getFieldValue(self::RIGHT_NODE_FIELD_NAME);
		
		$filter = new ARSelectFilter();
		$cond = new OperatorCond(new ARFieldHandle($className, self::LEFT_NODE_FIELD_NAME), $leftValue, "<");
		$cond->addAND(new OperatorCond(new ARFieldHandle($className, self::RIGHT_NODE_FIELD_NAME), $rightValue, ">"));
		$filter->setCondition($cond);
		$filter->setOrder(new ARFieldHandle($className, self::LEFT_NODE_FIELD_NAME), ARSelectFilter::ORDER_ASC);
		
		$recordSet = self::getRecordSet($className, $filter, $loadReferencedRecords);
		return $recordSet;
	}

	/**
	 * Creates an array representation of this node
	 *
	 * @return unknown
	 */
	public function toArray()
	{
		$data = array();
		foreach ($this->data as $name => $field)
		{
			if ($name == self::PARENT_NODE_FIELD_NAME)
			{
				$data['parent'] = $field->get()->getID();
			}
			else
			{
				$data[$name] = $field->get();
			}
		}
		$childArray = array();
		foreach ($this->childList as $child)
		{
			$childArray[] = $child->toArray();	
		}
		$data['children'] = $childArray;
		return $data;
	}
	
	/**
	 * Partial schema definition for a hierarchial data storage in a database
	 *
	 * @param string $className
	 */
	public static function defineSchema($className = __CLASS__) 
	{			
		$schema = self::getSchemaInstance($className);
		$tableName = $schema->getName();		
		$schema->registerField(new ARPrimaryKeyField("ID", ARInteger::instance()));	
		$schema->registerField(new ARForeignKeyField(self::PARENT_NODE_FIELD_NAME, $tableName, "ID",$className, ARInteger::instance()));					
		$schema->registerField(new ARField(self::LEFT_NODE_FIELD_NAME, ARInteger::instance()));
		$schema->registerField(new ARField(self::RIGHT_NODE_FIELD_NAME, ARInteger::instance()));		
	}
}

?>