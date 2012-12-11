<?php
/**
 * This behavior allows to save model with INSERT DELAYED query
 * @author Anatoly Rugalev <anatoly.rugalev@gmail.com>
 * @author Muhammad Shoaib <shoaibi@bitesource.com>
 * @link http://github.com/AnatolyRugalev/yii-insertDelayedBehavior
 * @version 0.2
 */
class InsertDelayedBehavior extends CActiveRecordBehavior
{
	/**
	 * @var string|null name of function to call before save or null to not call.
	 * function requires an $event parameter {@link http://www.yiiframework.com/doc/api/1.1/CActiveRecordBehavior#beforeSave-detail}
	 */
	public $beforeSaveFunction = 'beforeSave';
	/**
	 * @var string|null name of function to call after save or null to not call
	 * function requires an $event parameter {@link http://www.yiiframework.com/doc/api/1.1/CActiveRecordBehavior#afterSave-detail}
	 */
	public $afterSaveFunction = null;
	/**
	 * @var bool true for native insert on insert delayed fail
	 */
	public $onFailSimpleInsert = true;

	/**
	 * Properties is fully similar to CActiveRecord::save()
	 * @param bool $runValidation
	 * @param mixed $attributes
	 * @return bool
	 */
	public function saveDelayed($runValidation = true, $attributes = null)
	{
		if (!$runValidation || $this->owner->validate($attributes))
			return $this->owner->getIsNewRecord() ? $this->insertDelayed($attributes) : $this->owner->update($attributes);
		else
			return false;
	}

	/**
	 * Don't call this method directly. Use saveDelayed()
	 * @param mixed $attributes
	 * @return bool
	 * @throws CDbException
	 */
	public function insertDelayed($attributes)
	{
		if (!$this->owner->getIsNewRecord())
			throw new CDbException(Yii::t('yii', 'The active record cannot be inserted to database because it is not new.'));

		if (is_null($this->beforeSaveFunction) || $this->owner->{$this->beforeSaveFunction}(new CModelEvent($this))) {
			$this->log('Applying insert delayed', CLogger::LEVEL_TRACE);
			$builder = $this->owner->getCommandBuilder();
			$table = $this->owner->getMetaData()->tableSchema;
			$command = $builder->createInsertCommand($table, $this->owner->getAttributes());
			$command->text = preg_replace('#^INSERT #is', 'INSERT DELAYED ', $command->text, 1);
			if (preg_match('#INSERT DELAYED INTO `\w+` \((.*?)\) VALUES \((.*?)\)#is', $command->text, $matches)) {
				$fields = explode(',', $matches[1]);
				$params = explode(',', $matches[2]);
				$execParams = array();
				foreach ($fields as $i => $field) {
					$field = trim($field, '` ');
					$params[$i] = trim($params[$i], '` ');
					if ($this->owner->hasAttribute($field))
						$execParams[$params[$i]] = $this->owner->$field;
					else
						$execParams[$params[$i]] = 'NULL';
				}
			} else {
				$this->log('Cannot insert because query does not match a regular expression', CLogger::LEVEL_ERROR);
				if ($this->onFailSimpleInsert)
					return $this->owner->insert($attributes);
				return false;
			}
			if ($command->execute($execParams)) {
				$this->owner->setIsNewRecord(false);
				$this->owner->setScenario('update');
				return is_null($this->afterSaveFunction) || $this->owner->{$this->afterSaveFunction}(new CModelEvent($this));
			}
			else
			{
				$this->log('Execution of query failed. It seems like you using not MyISAM MySQL Engine', CLogger::LEVEL_ERROR);
				if ($this->onFailSimpleInsert)
					return $this->owner->insert($attributes);
				return false;
			}
		}
		return false;
	}



	/**
     * Logs a message.
     * @param string $message message to be logged
     * @param type $level A valid CLogger Level or Null(defaults to LEVEL_ERROR)
     */
	public function log($message, $level = CLogger::LEVEL_ERROR)
	{
        //setup owner to name of model behavior is being executed against.
        $owner = get_class($this->owner);

        //get details about caller, such as class and function name.
        $trace = debug_backtrace();
        $caller = $trace[1];

        //logger category
        $category = "{$caller['class']}.{$caller['function']}";

        //add prefix to message.
        //append .{$category}() after $owner if class and function names shall also appear in log message,
        // not really needed though as we can already see the category in log statements on any logger.
        $message = "{$owner}".(($message)?' - '.$message:$message); 

        //time to do some real work
        Yii::log($message, $level, $category);
    }


}
