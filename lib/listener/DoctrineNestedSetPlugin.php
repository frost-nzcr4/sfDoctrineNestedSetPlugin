<?php

/**
 * Easily sort each nestedset record based on position
 *
 * @package     DoctrineNestedSetPlugin
 * @subpackage  listener
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        
 * @since       1.0
 * @version     $Revision$
 * @author      Dmitri Chuvikovsky <chuvikovsky@gmail.com>
 */
class Doctrine_Template_Listener_DoctrineNestedSetPlugin extends Doctrine_Record_Listener
{
  /**
   * Array of sortable options
   *
   * @var array
   */
  protected $_options = array();


  /**
   * __construct
   *
   * @param array $options
   * @return void
   */
  public function __construct(array $options)
  {
    $this->_options = $options;
  }


  /**
   * When a nestedset object is deleted, promote all objects positioned lower than itself
   *
   * @param string $Doctrine_Event
   * @return void
   */
  public function postDelete(Doctrine_Event $event)
  {

    $object = $event->getInvoker();

    if($object->getNode()->isRoot())
    {
        $object->getTable()->createQuery()
                ->update()
                ->set($this->_options['name'], $this->_options['name'].' - 1')
                ->where($this->_options['name'].' > ?', $object[$this->_options['name']])
                ->execute();
    }
  }

}
