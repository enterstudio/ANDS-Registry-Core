<?php


namespace ANDS\API\Task\ImportSubTask;


use ANDS\RegistryObject;
use ANDS\Util\XMLUtil;

/**
 * Class ProcessCoreMetadata
 * @package ANDS\API\Task\ImportSubTask
 */
class ProcessCoreMetadata extends ImportSubTask
{
    protected $requireHarvestedOrImportedRecords = true;
    protected $title = "PROCESSING CORE METADATA";

    public function run_task()
    {
        $this->processUpdatedRecords();
    }

    /**
     * Update importedRecords core metadata
     *
     */
    public function processUpdatedRecords()
    {
        $importedRecords = $this->parent()->getTaskData("importedRecords");

        if ($importedRecords === false || $importedRecords === null) {
            return;
        }

        $total = count($importedRecords);
        debug("Processing Core Metadata for $total records");
        foreach ($importedRecords as $index => $roID) {
            // $this->log('Processing (updated) record: ' . $roID);

            $record = RegistryObject::find($roID);
            $recordData = $record->getCurrentData();

            // determine class, type and group in the record data
            $classes = ['collection', 'party', 'service', 'activity'];
            foreach ($classes as $class) {
                $registryObjectsElement = XMLUtil::getSimpleXMLFromString($recordData->data);
                $element = $registryObjectsElement->xpath('//ro:registryObject/ro:' . $class);
                $registryObjectElement = array_first(
                    $registryObjectsElement->xpath('//ro:registryObject')
                );
                if (count($element) > 0) {
                    $element = array_first($element);
                    $record->class = $class;
                    $record->type = (string)$element['type'];
                    $record->group = (string)$registryObjectElement['group'];
                    $record->save();
                    break;
                }
            }

            //determine harvest_id
            $record->setRegistryObjectAttribute('harvest_id',
                $this->parent()->batchID);
            
            $record->status = $this->parent()->getTaskData("targetStatus");
            
            $record->save();

            // titles and slug require the ro object
            $this->parent()->getCI()->load->model(
                'registry/registry_object/registry_objects', 'ro'
            );
            $ro = $this->parent()->getCI()->ro->getByID($roID);
            $ro->updateTitles();
            $ro->generateSlug();

            $ro->save();

            $this->updateProgress($index, $total, "Processed ($index/$total) $ro->title($roID)");
            unset($ro);
        }
        debug("Finished Processing Core Metadata for $total records");
    }


}