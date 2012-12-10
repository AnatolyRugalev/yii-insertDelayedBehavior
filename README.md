Yii Insert Delayed Behavior
============================

This behavior allows to save model with INSERT DELAYED.

Installation
---------------
Place files in this repository under application/extensions/behaviors


Usage
--------
Define `behaviors()` method in your ActiveRecord mode as follows:

Model.php
```php
public function behaviors()
{
	return array(
		'saveDelayed' => array(
			'class' => 'ext.behaviors.insert-delayed.InsertDelayedBehavior'
			'afterSaveFunction' => 'afterSave',
			'beforeSaveFunction' => 'beforeSave', 
			'onFailSimpleInsert' => true,
		);
	);
}
```
and now you can call `saveDelayed()` instead of `save()` inside controller:

Controller.php
```php
public function saveModel($model)
{
	...
	$model->saveDelayed();
	...
}
```
***By default afterSave function call is disabled. If you specify a function to call after save, you can't get there primary key of inserted model.***

Note that INSERT DELAYED is possible only when model just created.
