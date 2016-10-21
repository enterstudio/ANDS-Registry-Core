<?php
/**
 * Created by PhpStorm.
 * User: leomonus
 * Date: 20/09/2016
 * Time: 1:33 PM
 */

namespace ANDS\API\Task\ImportSubTask;

use ANDS\DataSource;
use ANDS\Repository\DataSourceRepository;
use ANDS\DataSourceAttribute;
use ANDS\Repository\RegistryObjectsRepository as Repo;

class FinishImport extends ImportSubTask
{

    private $harvestStarted;

    protected $requireDataSource = true;
    protected $title = "FINISHING IMPORT";

    public function run_task()
    {
        $dataSource = $this->getDataSource();

        $this->setHarvestTime();

        $this->parent()->setTaskData(
            "datasourceRecordAfterCount",
            Repo::getCountByDataSourceIDAndStatus($this->parent()->dataSourceID,
                $this->parent()->getTaskData("dataSourceDefaultStatus")
            )
        );

        $this->handleAdvancedHarvest($dataSource);
        $this->updateDataSourceLogs($dataSource);
        $this->setHarvestStatus($dataSource);
        $this->updateDataSourceStats($dataSource);

        return $this;
    }

    private function setHarvestTime()
    {
        $dataSource = $this->getDataSource();
        $harvestMessage = json_decode($dataSource->getHarvest($this->parent()->harvestID)->message);

        if (isset($harvestMessage->start_utc)) {
            $this->harvestStarted = $harvestMessage->start_utc;
            return;
        }

        date_default_timezone_set('UTC');
        $this->harvestStarted = date("Y-m-d\TH:i:s\Z", time());
        date_default_timezone_set('Australia/Canberra');
    }

    public function handleAdvancedHarvest($dataSource)
    {
        $advancedHarvestMode = $dataSource->getDataSourceAttribute("advanced_harvest_mode")->value;

        $dataSource->setDataSourceAttribute("last_harvest_run_date",'');

        if ($advancedHarvestMode == 'INCREMENTAL') {
            $this->log("Next from_date ". $this->harvestStarted);
            $this->parent()->updateHarvest(['last_run'=>$this->harvestStarted]);
            $dataSource->setDataSourceAttribute("last_harvest_run_date", $this->harvestStarted);
        }

        $dataSource->save();
    }

    public function setHarvestStatus($dataSource){
        $harvestFrequency = $dataSource->getDataSourceAttribute("harvest_frequency")->value;
        $harvestDate = strtotime($dataSource->getDataSourceAttribute("harvest_date")->value);

        if($harvestFrequency  == 'once only' || $harvestFrequency == ''){
            $this->parent()->updateHarvest(['status'=>'COMPLETED', "importer_message" => ""]);
            return;
        }

        $nextRun = $this->getNextHarvestDate($harvestDate, $harvestFrequency);
        $nextHarvestDate = date("Y-m-d\TH:i:s\Z", $nextRun);
        $this->log("Harvest rescheduled to run at ". $nextHarvestDate);
        $this->log("Next from_date ". $this->harvestStarted);
        $dataSource->setDataSourceAttribute("last_harvest_run_date", $this->harvestStarted);
        $batchNumber = strtoupper(sha1($nextRun));

        $dataSource->appendDataSourceLog(
            "Harvest rescheduled for: ".date('Y-m-d\TH:i:s.uP', $nextRun). " with previous settings",
            "info", "IMPORTER"
        );

        $this->parent()->updateHarvest([
            'status' => 'SCHEDULED',
            'last_run' => $this->harvestStarted,
            'next_run' => date('Y-m-d\TH:i:s.uP', $nextRun),
            'batch_number' => $batchNumber,
            'importer_message' => "Harvest rescheduled for: ".date('Y-m-d\TH:i:s.uP', $nextRun)
        ]);

    }


    public function updateDataSourceLogs($dataSource)
    {
        //append_log($log_message, $log_type = "info | error", $log_class="data_source", $harvester_error_type=NULL)
        $targetStatus = $this->parent()->getTaskData("targetStatus");
        $selectedKeys = [
            "dataSourceDefaultStatus"=>"Default Import Status for Data Source",
            "recordsInFeedCount"=>"Valid Records Received in Harvest",
            "invalidRegistryObjectsCount"=>"Failed to Validate",
            "duplicateKeyinFeedCount"=>"Duplicated Records",
            "recordsExistOtherDataSourceCount"=>"Record exist in other Datasource(s)",
            "missingRegistryObjectKeyCount"=>"Invalid due to Missing key",
            "missingOriginatingSourceCount"=>"Invalid due to missing OriginatingSource",
            "missingGroupAttributeCount"=>"Invalid missing group Attribute",
            "recordsCreatedCount"=>"New Records Created",
            "recordsUpdatedCount"=>"Records updated",
            "recordsNotUpdatedCount"=>"Records content unchanged",
            "recordsDeletedCount"=>"Records deleted (due to OAI or Refresh mode)",
            "datasourceRecordBeforeCount"=>"Number of ".$targetStatus." records Before Import",
            "datasourceRecordAfterCount"=>"Number of ".$targetStatus." records After Import"
        ];

        if ($errorList = $this->parent()->getError()) {
            $message = "IMPORT COMPLETED WITH ERROR(S)" . NL;
            $message .= "Batch ID: ".$this->parent()->batchID.NL;
            $message .= "Time: ".date("Y-m-d\TH:i:s\Z", time()).NL;
            foreach ($selectedKeys as $key=>$title){
                $taskData = $this->parent()->getTaskData($key);
                if($taskData !== 0) {
                    $message .= $title . ": " . $taskData . NL;
                }
            }
            $message .= NL.NL. implode(NL.NL, $errorList);

            $dataSource->appendDataSourceLog($message, "error", "IMPORTER", "");
            return;
        }

        $message = "IMPORT COMPLETED" . NL;
        $message .= "Batch ID: ".$this->parent()->batchID.NL;
        $message .= "Time: ".date("Y-m-d\TH:i:s\Z", time()).NL;
        $message .= "TaskID: ".$this->parent()->getId();
        foreach ($selectedKeys as $key=>$title){
            $taskData = $this->parent()->getTaskData($key);
            if($taskData !== 0) {
                $message .= $title . ":" . $taskData . NL;
            }
        }

        $dataSource->appendDataSourceLog($message, "info", "IMPORTER", "");
        return;
    }

    function getNextHarvestDate($harvestDate, $harvestFrequency){

        $now = time();
        if($harvestDate !== null){
            $nextHarvest = $harvestDate;
        }else{
            $nextHarvest = 0;
        }

        while($nextHarvest < $now)
        {
            if($harvestFrequency == 'daily')
                $nextHarvest = strtotime('+1 day', $nextHarvest);
            elseif($harvestFrequency == 'weekly')
                $nextHarvest = strtotime('+1 week', $nextHarvest);
            elseif($harvestFrequency == 'fortnightly')
                $nextHarvest = strtotime('+2 week', $nextHarvest);
            elseif($harvestFrequency == 'monthly')
                $nextHarvest = strtotime('+1 month', $nextHarvest);
            elseif($harvestFrequency =='hourly')
                $nextHarvest += 60*60;
        }

        return $nextHarvest;
    }

    public function updateDataSourceStats($dataSource)
    {
        // update count_total
        $dataSource->setDataSourceAttribute(
            'count_total',
            RegistryObject::where('data_source_id', $dataSource->data_source_id)->count()
        );

        // count_$status
        $validStatuses = ["MORE_WORK_REQUIRED", "DRAFT", "SUBMITTED_FOR_ASSESSMENT", "ASSESSMENT_IN_PROGRESS", "APPROVED", "PUBLISHED"];
        foreach ($validStatuses as $status) {
            $dataSource->setDataSourceAttribute(
                'count_'.$status,
                RegistryObject::where('data_source_id', $dataSource->data_source_id)
                    ->where('status', $status)->count()
            );
        }

    }

}