<?php
/**
 * This behavior allows to save model with INSERT DELAYED query
 * Author: Anatoly Rugalev <anatoly.rugalev@gmail.com>
 * Github: http://github.com/AnatolyRugalev/yii-insertDelayedBehavior
 * Version: 0.1
 */ 
class InsertDelayedBehavior extends CBehavior {
    /**
     * @var string|null name of function to call before save or null to not call
     */
    public $beforeSaveFunction = 'beforeSave';
    /**
     * @var string|null name of function to call after save or null to not call
     */
    public $afterSaveFunction = null;
    /**
     * @var bool true for native insert on insert delayed fail
     */
    public $onFailSimpleInsert = true;

    public function saveDelayed($runValidation=true,$attributes=null)
    {
        if(!$runValidation || $this->owner->validate($attributes))
            return $this->owner->getIsNewRecord() ? $this->insertDelayed($attributes) : $this->owner->update($attributes);
        else
            return false;
    }

    public function insertDelayed($attributes)
    {
        if(!$this->owner->getIsNewRecord())
            throw new CDbException(Yii::t('yii','The active record cannot be inserted to database because it is not new.'));
        if(is_null($this->beforeSaveFunction) || $this->owner->{$this->beforeSaveFunction}())
        {
            Yii::trace(get_class($this->owner).'.insertDelayed()','application.behaviors.InsertDelayedBehavior');
            $builder=$this->owner->getCommandBuilder();
            $table=$this->owner->getMetaData()->tableSchema;
            $command=$builder->createInsertCommand($table,$this->owner->getAttributes());
            $command->text = preg_replace('#^INSERT #is', 'INSERT DELAYED ', $command->text, 1);
            if(preg_match('#INSERT DELAYED INTO `\w+` \((.*?)\) VALUES \((.*?)\)#is', $command->text, $matches))
            {
                $fields = explode(',', $matches[1]);
                $params = explode(',', $matches[2]);
                $execParams = array();
                foreach($fields as $i => $field)
                {
                    $field = trim($field, '` ');
                    $params[$i] = trim($params[$i], '` ');
                    if($this->owner->hasAttribute($field))
                        $execParams[$params[$i]] = $this->owner->$field;
                    else
                        $execParams[$params[$i]] = 'NULL';
                }
            }
            else
            {
                Yii::log(get_class($this->owner).'.insertDelayed() - Cannot insert because of query does not match a regular expression', CLogger::LEVEL_ERROR,'application.behaviors.InsertDelayedBehavior');
                if($this->onFailSimpleInsert)
                    return $this->owner->insert($attributes);
                return false;
            }
            if($command->execute($execParams))
            {
                $this->owner->afterInsertDelayed();
                $this->owner->setIsNewRecord(false);
                $this->owner->setScenario('update');
                return is_null($this->afterSaveFunction) || $this->owner->{$this->afterSaveFunction}();
            }
        }
        return false;
    }


}
