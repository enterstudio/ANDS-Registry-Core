<?php
namespace ANDS\API\Task\ImportSubTask;


use ANDS\API\Task\ImportTask;
use ANDS\API\Task\Task;

class ImportSubTask extends Task
{
    private $parentTask;

    /**
     * @param $task
     * @return $this
     */
    public function setParentTask($task)
    {
        $this->parentTask = $task;
        return $this;
    }

    /**
     * @return ImportTask
     */
    public function getParentTask()
    {
        return $this->parentTask;
    }

    /**
     * Alias for getParentTask
     * for simpler usage
     *
     * @return ImportTask
     */
    public function parent()
    {
        return $this->getParentTask();
    }

    public function log($log)
    {
        $this->message['log'][] = $log;
        $this->parent()->log(get_class($this) . ": " . $log);
        return $this;
    }
}

//@todo move to ANDS\API\Task\Exception?
class NonFatalException extends \Exception
{

}