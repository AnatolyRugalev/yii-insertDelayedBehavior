<?php
/**
 * This behavior allows to save model with INSERT DELAYED query
 * Author: Anatoly Rugalev <anatoly.rugalev@gmail.com>
 * Github: http://github.com/AnatolyRugalev/yii-insertDelayedBehavior
 */ 
class InsertDelayedBehavior extends CBehavior {

    public function saveDelayed($runValidation=true,$attributes=null)
    {
        if(!$runValidation || $this->owner->validate($attributes))
            return $this->owner->getIsNewRecord() ? $this->insertDelayed() : $this->owner->update($attributes);
        else
            return false;
    }

    /**
     * Inserts a row into the table based on this active record attributes.
     * If the table's primary key is auto-incremental and is null before insertion,
     * it will be populated with the actual value after insertion.
     * Note, validation is not performed in this method. You may call {@link validate} to perform the validation.
     * After the record is inserted to DB successfully, its {@link isNewRecord} property will be set false,
     * and its {@link scenario} property will be set to be 'update'.
     * @return boolean whether the attributes are valid and the record is inserted successfully.
     * @throws CDbException if the record is not new
     */
    public function insertDelayed()
    {
        if(!$this->owner->getIsNewRecord())
            throw new CDbException(Yii::t('yii','The active record cannot be inserted to database because it is not new.'));
        if($this->owner->beforeInsertDelayed())
        {
            Yii::trace(get_class($this->owner).'.insertDelayed()','application.behaviors.InsertDelayedBehavior');
            $builder=$this->owner->getCommandBuilder();
            $table=$this->owner->getMetaData()->tableSchema;
            /** @var $command CDbCommand */
            /** @var $builder CDbCommandBuilder */
            $command=$builder->createInsertCommand($table,$this->owner->getAttributes());
            $command->text = preg_replace('#^INSERT #is', 'INSERT DELAYED ', $command->text, 1);
            /** @var $table CDbTableSchema */
            $table = $this->owner->tableSchema;
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
                return false;
            }
            if($command->execute($execParams))
            {
                $this->owner->afterInsertDelayed();
                $this->owner->setIsNewRecord(false);
                $this->owner->setScenario('update');
                return true;
            }
        }
        return false;
    }


}
