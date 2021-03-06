# sfDoctrineNestedSetPlugin

Fork of http://svn.symfony-project.com/plugins/sfDoctrineNestedSetPlugin

The sfDoctrineNestedSetPlugin allows use of the doctrine behaviour NestedSet.
It allows move, add, delete and sort nodes. Plugin contains widget
(sfWidgetFormDoctrineChoiceNestedSet) and validator
(sfValidatorDoctrineNestedSet) which you can use separately.

## Installation

Install the plugin

    $ symfony plugin:install sfDoctrineNestedSetPlugin

## Documentation

To get fully functional NestedSet behaviour you need to change this files:

* schema.yml
* generator.yml
* actions.class.php
* MODULE_NAMEForm.class.php
* MODULE_NAMEFilter.class.php
* _list_td_tabular.php
* view.yml

#### schema.yml

Apply the behavior to your model in your schema file:

``` yaml
	# config/doctrine/schema.yml
    model:
      actAs:
        DoctrineNestedSetPlugin: ~
```

Note. This work only with option "hasManyRoots: true".
Plugin extends Doctrine behaviour NestedSet and adds column "position". Columns
"root_id", "lft", "rgt" and "level" are added by NestedSet.

#### generator.yml

In your module, edit config/generator.yml, and under list, object actions, add:

``` yaml
	# config/generator.yml
    list:
      object_actions:
        promote:
          action: promote
        demote:
          action: demote
        _edit:  ~
        _delete: ~
```

#### actions.class.php

In your module, edit 'actions.class.php', Add the following methods:

``` php
    <?php
	// YourModuleName/actions.class.php
    public function executeDelete(sfWebRequest $request)
    {
      $request->checkCSRFProtection();

      $this->dispatcher->notify(new sfEvent($this, 'admin.delete_object', array('object' => $this->getRoute()->getObject())));

      if ($this->getRoute()->getObject()->getNode()->delete())
      {
        $this->getUser()->setFlash('notice', 'The item was deleted successfully.');
      }

      $this->redirect("@moduleIndexRoute");
    }

    protected function executeBatchDelete(sfWebRequest $request)
    {
      $ids = $request->getParameter('ids');

      $records = Doctrine_Query::create()
        ->from('MODULE_NAME')
        ->whereIn('id', $ids)
        ->execute();

      foreach ($records as $record)
      {
        $record->getNode()->delete();
      }

      $this->getUser()->setFlash('notice', 'The selected items have been deleted successfully.');
      $this->redirect("@moduleIndexRoute");
    }

    protected function addSortQuery($query)
    {
      $query->addOrderBy('position asc');
      $query->addOrderBy('lft asc');
    }

    public function executePromote(sfWebRequest $request)
    {
      $object = $this->getRoute()->getObject();
      if ($object->promote())
      {
        $this->getUser()->setFlash('notice', 'The selected node has been moved successfully.');    
      }
      else
      {
        $this->getUser()->setFlash('error', 'The selected node cannot be moved.');     
      }
      $this->redirect("@moduleIndexRoute");
    }

    public function executeDemote(sfWebRequest $request)
    {
      $object = $this->getRoute()->getObject();
      if ($object->demote())
      {
        $this->getUser()->setFlash('notice', 'The selected node has been moved successfully.');    
      }
      else
      {
        $this->getUser()->setFlash('error', 'The selected node cannot be moved.');    
      }
      $this->redirect("@moduleIndexRoute");
    }
```

Methods "executeDelete", "executeBatchDelete" are similar to methods you can
find in cache. The only difference is method "getNode()" prepending method
"delete()". Change MODULE_NAME in method "executeBatchDelete()". Method
"addSortQuery()" completely rewritten because the order of nestedset is already
defined ('position asc, lft asc'). Change "@moduleIndexRoute" according to your
router.

#### MODULE_NAMEForm.class.php

Edit MODULE_NAMEForm.class.php file, add the following code:

``` php
    <?php
    public function configure()
    {
        // ...

        $this->setWidget('parent', new sfWidgetFormDoctrineChoiceNestedSet(array(
            'model'     => $this->getModelName(),
            'add_empty' => true,
            'query'     => $this->getObject()
                              ->getTable()
                              ->createQuery()
                              ->select()
                              ->orderBy('position asc, lft asc')
    	)));

        if ($this->getObject()->getNode()->hasParent())
        {
            // FIXME: You can change getPrimaryKey() method to that your own model used (i. e. getModelId())
            $this->setDefault('parent', $this->getObject()->getNode()->getParent()->getPrimaryKey());
        }

        $this->setValidator('parent', new sfValidatorDoctrineChoiceNestedSet(array(
	        'required' => false,
	        'model'    => $this->getModelName(),
	        'node'     => $this->getObject()
        )));
        $this->getValidator('parent')->setMessage('node', 'A category cannot be made a descendent of itself.');
        
        $this->setValidator('position', new sfValidatorDoctrineNestedSetPosition(array(
            'required' => false,
            'model'    => $this->getModelName(),
            'node'     => $this->getObject()
        )));
        $this->getValidator('position')->setMessage('position', 'This node position is in use by other node.');
    }

    protected function doUpdateObject($values)
    {
        $values = $this->getObject()->updateNestedSetObject($values, $this->isNew());

        parent::doUpdateObject($values);
    }

    protected function doSave($con=null)
    {
        parent::doSave($con);

        $this->getObject()->saveNestedSetObject();
    }
```

#### MODULE_NAMEFilter.class.php

If you want to filter records by root_id column you can add following code to
file MODULE_NAMEFilter.class.php:

``` php
    <?php
    public function configure()
    {
        $this->setWidget('parent', new sfWidgetFormDoctrineChoice(array(
	        'model' => $this->getModelName(),
	        'add_empty' => true,
            'key_method' => 'getRootId',
            'query' => $this->getTable()->createQuery()
                                        ->select()
                                        ->where('level = ?', '0')
                                        ->orderBy('position asc')
        )));

        $this->setValidator('parent', new sfValidatorDoctrineChoice(array(
            'required' => false,
            'model' => $this->getModelName(),
            'column' => 'root_id'
        )));
    }

    public function addParentColumnQuery($query, $field, $value)
    {
        if (!empty($value))
        {
            $query->andWhere(sprintf('%s.root_id = ?', $query->getRootAlias()), $value);
        }
    }
```

#### _list_td_tabular.php

Add indentation to make nestedset list looks more appealing. Copy file
'_list_td_tabular.php' from cache to 'templates' directory. Add inline style to
TD tag where you want to see indentation:

``` html
    style="padding-left: <?php echo $MODULENAME->getLevel()*20;?>px;"
```

Code should look like this:

``` html
    <!-- /apps/backend/modules/module_name/templates/_list_td_tabular.php -->
    
    <td class="sf_admin_text sf_admin_list_td_name" style="padding-left: <?php echo $MODULENAME->getLevel()*20;?>px;">
      <?php echo $MODULENAME->getCOLUNMNAME() ?>
    </td>
```

Change $MODULENAME and getCOLUNMNAME();

#### view.yml

To add arrow images to list you need to publish plugin assets:

    $ symfony plugin:publish-assets

and add plugin stylesheets to view.yml file

``` yaml
    stylesheets: [/sfDoctrineNestedSetPlugin/css/nestedset.css]
```

As you can see above we use widget sfWidgetFormDoctrineChoiceNestedSet and
validator sfValidatorDoctrineNestedSet which included in the plugin. You can use
its as ordinary Symfony widgets and validators.

### sfWidgetFormDoctrineChoiceNestedSet

The sfWidgetFormDoctrineChoiceNestedSet functions nearly the same as
sfWidgetFormDoctrineChoice, the only difference being that it will automatically
sort the items by their hierarchy, and will indent each item according to its
level. As this widget extends sfWidgetFormDoctrineChoice, it can be added
without any other code changes necessary:

``` php
    <?php
    $this->setWidget('item', new sfWidgetFormDoctrineChoiceNestedSet(array(
        'model'     => $this->getModelName(),
        'add_empty' => true,
        'query'     => $this->getObject()
                            ->getTable()
                            ->createQuery()
                            ->select()
                            ->orderBy('position asc, lft asc')
    )));
```

The order you can define in 'query' option.

### sfValidatorDoctrineNestedSet

The sfValidatorDoctrineNestedSet provides validation by checking that the
selected node is not a descendant of the node passed to it during configuration
(and that they are not the same). This can be used to ensure that when moving a
node, it is not made a descendant of itself. The validator extends from
sfValidatorBase and includes two required options: 'model', which is the model
class, and 'node', which is the node that the selected item is being checked
against. Normally, this will be the form's object:

``` php
    <?php
    $this->setValidator('item', new sfValidatorDoctrineNestedSet(array(
        'model' => $this->getModelName(),
        'node'  => $this->getObject(),
    )));
```

## Support

For more information please feel free to visit
[halestock.wordpress.com](http://halestock.wordpress.com) and leave a comment.
