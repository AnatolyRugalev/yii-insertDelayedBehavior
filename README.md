yii-insertDelayedBehavior
=========================

This behavior allows to save model with INSERT DELAYED.

Usage:

Model.php
```php
public function behaviors()
{
	return array(
		'saveDelayed' => array(
			'class' => 'application.behaviors.InsertDelayedBehavior'
			'afterSaveFunction' => 'afterSave',
			'beforeSaveFunction' => 'beforeSave', 
			'onFailSimpleInsert' => true,
		);
	);
}
```

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
