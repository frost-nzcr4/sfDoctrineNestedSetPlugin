<?php

/**
 * Easily adds sorting functionality to a nestedset record.
 *
 * @package     DoctrineNestedSetPlugin
 * @subpackage  template
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link
 * @since       1.0
 * @version     $Revision$
 * @author      Dmitri Chuvikovsky <chuvikovsky@gmail.com>
 */
class Doctrine_Template_DoctrineNestedSetPlugin extends Doctrine_Template_NestedSet
{
  /**
   * Array of NestedSet options
   *
   * @var string
   */
  protected $_nestedset_options = array('name'        =>  'position',
                                        'alias'       =>  null,
                                        'type'        =>  'integer',
                                        'length'      =>  8,
                                        'hasManyRoots' => 1,
                                        'rootColumnName' => 'root_id'
  );

  // Doctrine_Record object of parent node
  private $_parent = null;

  // The object is new by default
  private $_isnew = true;

  // If object wasn't root previously then false
  // If object was root then integer
  private $_prev_root_position = false;

  protected $_options;

  /**
   * __construct
   *
   * @param string $array
   * @return void
   */
  public function __construct(array $options = array())
  {
    $this->_nestedset_options = Doctrine_Lib::arrayDeepMerge($this->_nestedset_options, $options);

    if (!isset($options['hasManyRoots']))
    {
      $options['hasManyRoots'] = 1;

      if (!isset($options['rootColumnName']))
      {
        $options['rootColumnName'] = 'root_id';
      }
    }

    $this->_options = $options;
  }

    /**
     * Set up NestedSet template
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
    }

  /**
   * Set table definition for nestedset behavior
   * (borrowed and modified from csDoctrineActAsSortablePlugin)
   *
   * @return void
   */
  public function setTableDefinition()
  {
        if (isset($this->_nestedset_options['hasManyRoots']) && $this->_nestedset_options['hasManyRoots'])
        {
            $name = $this->_nestedset_options['name'];

            if ($this->_nestedset_options['alias'])
            {
              $name .= ' as ' . $this->_nestedset_options['alias'];
            }

            $this->hasColumn($name, $this->_nestedset_options['type'], $this->_nestedset_options['length']);

            $this->addListener(new Doctrine_Template_Listener_DoctrineNestedSetPlugin($this->_nestedset_options));
        }

        parent::setTableDefinition();
  }


  /**
   * Demotes a nestedset object to a lower position
   *
   * @return void
   */
  public function demote()
  {
    $object = $this->getInvoker();
    $position = $object->get($this->_nestedset_options['name']);

    if ($object->getNode()->isRoot())
    {
        if($object->getFinalPosition() == $position)
        {
            return false;
        }
        else
        {
            $this->move(false);
            return true;
        }

    }
    else
    {
        if ($object->getNode()->hasNextSibling())
        {
            $object->getNode()->moveAsNextSiblingOf($object->getNode()->getNextSibling());
            return true;
        }
        else
        {
            return false;
        }
    }
  }

  /**
   * Promotes a nestedset object to a higher position
   *
   * @return void
   */
  public function promote()
  {
    $object = $this->getInvoker();

    if ($object->getNode()->isRoot())
    {
        if(1 == $object[$this->_nestedset_options['name']])
        {
            return false;
        }
        else
        {
            $this->move(true);
            return true;
        }

    }
    else
    {
        if ($object->getNode()->hasPrevSibling())
        {
            $object->getNode()->moveAsPrevSiblingOf($object->getNode()->getPrevSibling());
            return true;
        }
        else
        {
            return false;
        }
    }
  }

  /**
   * Internal function for node moving
   *
   * @param boolean $up promote by default
   */
  private function move($up=true)
  {
    $object = $this->getInvoker();

    if (true == $up)
    {
        $set = $this->_nestedset_options['name'].' + 1';
        $pos = $object[$this->_nestedset_options['name']] - 1;
    }
    else
    {
        $set = $this->_nestedset_options['name'].' - 1';
        $pos = $object[$this->_nestedset_options['name']] + 1;
    }

    $connection = $object->getTable()->getConnection();

    //begin Transaction
    $connection->beginTransaction();

    $object->getTable()->createQuery()
            ->update()
            ->set($this->_nestedset_options['name'], $set)
            ->where($this->_nestedset_options['name'].' = ?', $pos)
            ->execute();
    $object->getTable()->createQuery()
            ->update()
            ->set($this->_nestedset_options['name'], $pos)
            ->where($this->_nestedset_options['name'].' = ?', $object[$this->_nestedset_options['name']])
            ->andWhere($this->_nestedset_options['rootColumnName'].' = ?', $object[$this->_nestedset_options['rootColumnName']])
            ->execute();

    // Commit Transaction
    $connection->commit();
  }


  /**
   * Get the final position of a model
   *
   * @return integer
   */
  public function getFinalPosition()
  {
    $object = $this->getInvoker();

    $q = $object->getTable()->createQuery()
            ->select($this->_nestedset_options['name'])
            ->orderBy($this->_nestedset_options['name'].' desc')
            ->limit(1)
            ->fetchArray();

    $r = (count($q) > 0) ? (int) $q[0][$this->_nestedset_options['name']] : 0;

    return $r;
  }

  /**
   * Get the information about node and his future parent
   * Node future position calculation
   *
   * @param array $values
   * @param boolean $isNew
   * @return array
   */
  public function updateNestedSetObject($values, $isNew=true)
  {

      $object = $this->getInvoker();
      $this->_isnew = $isNew;

      // if not root

      if (isset($values['parent']) && !is_null($values['parent']))
      {
            if ($object->getNode()->isRoot())
            {
                $this->_prev_root_position = ((int)$values[$this->_nestedset_options['name']] > 0) ? (int)$values[$this->_nestedset_options['name']] : false;
            }
            $this->_parent = $object->getTable()->find((int)$values['parent']);
            $values[$this->_nestedset_options['name']] = $this->_parent[$this->_nestedset_options['name']];
      }

      // if root
      else
      {
            $values[$this->_nestedset_options['name']] = $this->getFinalPosition()+1;
      }

      return $values;
  }

  /**
   * Save nestedset node
   *
   * @return void
   */
  public function saveNestedSetObject()
  {
    $object = $this->getInvoker();

	if ($this->_parent) {
		if ($this->_isnew) {
			$object->getNode()->insertAsLastChildOf($this->_parent);
		} else {
			$object->getNode()->moveAsLastChildOf($this->_parent);
			$this->changeDependentPositions();
		}
	} else {
		$categoryTree = $object->getTable()->getTree();

		if ($this->_isnew) {
			$categoryTree->createRoot($object);
		} else {
			$object->getNode()->makeRoot($object->getPrimaryKey());
			$this->changeDependentPositions();
		}
	}
  }

  /**
   * Changing positions after node moving
   *
   * @return void
   */
  private function changeDependentPositions()
  {
      $object = $this->getInvoker();

      // change the position of children nodes

      if ($object->getNode()->hasChildren())
      {
          foreach ($object->getNode()->getDescendants() as $child)
          {
              $child[$this->_nestedset_options['name']] = $object[$this->_nestedset_options['name']];
              $child->save();
          }

      }

      // recalculate positions if node was root but now not

      if (false !== $this->_prev_root_position)
      {
          $object->getTable()->createQuery()
                  ->update()
                  ->set($this->_nestedset_options['name'], $this->_nestedset_options['name'].' - 1')
                  ->where($this->_nestedset_options['name'].' > ?', $this->_prev_root_position)
                  ->execute();
      }
  }

}
