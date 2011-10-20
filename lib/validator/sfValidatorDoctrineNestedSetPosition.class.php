<?php
/**
 * sfValidatorDoctrineNestedSetPosition.
 *
 * Checks that the position of the node not used by other nodes.
 *
 * @author frost-nzcr4 <frost-nzcr4@github.com>
 */
class sfValidatorDoctrineNestedSetPosition extends sfValidatorBase {
	/**
	 * Configures the current validator.
	 *
	 * Available options:
	 *
	 *   * model: The model class (required)
	 *   * node:  The node being moved (required)
	 *
	 * @see sfValidatorBase
	 */
	protected function configure($options = array(), $messages = array()) {
		$this->addRequiredOption('model');
		$this->addRequiredOption('node');

		$this->addMessage('position', 'This node position is in use by other node.');
	}

	/**
	 * Verifies that the target node will not cause the current node to become a descendant of itself.
	 *
	 * @see sfValidatorBase
	 */
	protected function doClean($value) {
		if (isset($value) && !$value) {
			unset($value);
		} else {
			$object = $this->getOption('node');
			$q = Doctrine::getTable($this->getOption('model'))->createQuery()
				->where('position = ?', $value)
				->limit(1)
				->fetchOne();

			if ($q && $object->getPrimaryKey() !== $q->getPrimaryKey()) {
				// cannot set this position because it exists and ZANYATA. should do exit.
				// or should it be checked at form with new class that extends sfValidatorInteger?
				throw new sfValidatorError($this, 'position', array('value' => $value));
			}

			return $value;
		}
	}
}