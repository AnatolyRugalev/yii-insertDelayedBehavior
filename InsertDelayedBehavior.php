<?php
/**
 * This behavior allows to save model with INSERT DELAYED query
 * @author Anatoly Rugalev <anatoly.rugalev@gmail.com>
 * @author Muhammad Shoaib <shoaibi@bitesource.com>
 * @link http://github.com/AnatolyRugalev/yii-insertDelayedBehavior
 * @version 0.3a
 */

class InsertDelayedBehavior extends CActiveRecordBehavior {
	/**
	 * @var bool whether call beforeSave function of CActiveRecord or not
 	*/
	public $callBeforeSave = true;
	/**
	 * @var bool whether call afterSave function of CActiveRecord or not
 	*/
	public $callAfterSave = false;
    /**
     * @var bool true for native insert on insert delayed fail
 	*/
    public $onFailSimpleInsert = true;
    /**
     * @var object|null name of the owner class on which behavior is being executed
 	*/
    private $_ownerClass = null;

	/**
     * Parameters is fully similar to CActiveRecord::save()
     * @param bool $runValidation
     * @param mixed $attributes
     * @return bool
 	*/
    public function saveDelayed($runValidation = true, $attributes = null) {
        if (!$runValidation || $this->owner->validate($attributes))
        	return $this->owner->getIsNewRecord() ? $this->insertDelayed($attributes) : $this->owner->update($attributes);
        else
        	return false;
    }
	/**
	 * Calls beforeSave of model and invalidates event if it's false
	 * @param CModelEvent $event
	 */
	protected function beforeSave($event) {
		if($this->callBeforeSave)
			$event->isValid = $this->owner->beforeSave();
	}
	/**
	 * Calls afterSave of model
	 * @param CModelEvent $event
	 */
	protected function afterSave($event) {
		if($this->callAfterSave)
			$this->owner->afterSave();
	}
	/**
     * Don't call this method directly. Use saveDelayed()
     * @param mixed $attributes
     * @return bool
     * @throws CDbException
 	*/
    public function insertDelayed($attributes) {
        if (!$this->owner->getIsNewRecord()) throw new CDbException(Yii::t('yii', 'The active record cannot be inserted to database because it is not new.'));
		$saveEvent = new CModelEvent($this);
		$this->beforeSave($saveEvent);
		if($saveEvent->isValid) {
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
					if(preg_match('#^:yp[0-9]+$#is', $params[$i]))  //if it really parameter, not an expression
					{
						if ($this->owner->hasAttribute($field))
							$execParams[$params[$i]] = $this->owner->$field;
						else
							$execParams[$params[$i]] = 'NULL';
					}
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
				$this->afterSave($saveEvent);
				return true;
            } else {
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
     * @param string $level A valid CLogger Level or Null(defaults to LEVEL_ERROR)
 	*/
    protected function log($message, $level = CLogger::LEVEL_ERROR) {
        //get details about caller, such as class and function name.
        $trace = debug_backtrace();
        $caller = $trace[1];
        //logger category
        $category = "{$caller['class']}.{$caller['function']}";
        //add prefix to message.
        //append .{$category}() after $owner if class and function names shall also appear in log message,
        // not really needed though as we can already see the category in log statements on any logger.
        $message = $this->getOwnerClass() . (($message) ? ' - ' . $message : $message);
        //time to do some real work
        Yii::log($message, $level, $category);
    }
    /**
     * Returns the class of owner of this behavior
     * @return string
 	*/
    protected function getOwnerClass() {
        if (is_null($this->_ownerClass)) $this->_ownerClass = get_class($this->owner);

        return $this->_ownerClass;
    }
}
