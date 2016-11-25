<?php


namespace ANDS\API\Task\ImportSubTask;


use ANDS\Registry\Providers\RelationshipProvider;
use ANDS\Repository\RegistryObjectsRepository;

/**
 * Class IndexRelationship
 * Index Relationship data for importedRecords
 *
 * @package ANDS\API\Task\ImportSubTask
 */
class IndexRelationship extends ImportSubTask
{
    protected $title = "INDEXING RELATIONSHIP";

    public function run_task()
    {
        $targetStatus = $this->parent()->getTaskData('targetStatus');
        if (!RegistryObjectsRepository::isPublishedStatus($targetStatus)) {
            $this->log("Target status is ". $targetStatus.' No indexing required');
            return;
        }

        $this->parent()->getCI()->load->library('solr');

        $importedRecords = $this->parent()->getTaskData("importedRecords") ? $this->parent()->getTaskData("importedRecords") : [];

        $affectedRecords = $this->parent()->getTaskData("affectedRecords") ? $this->parent()->getTaskData("affectedRecords") : [];

        $totalRecords = array_merge($importedRecords, $affectedRecords);
        $totalRecords = array_values(array_unique($totalRecords));

        $total = count($totalRecords);

        if ($total == 0) {
            $this->log("No records needed to be reindexed");
            return;
        }

        $this->parent()->updateHarvest(
            ["importer_message" => "Indexing $total importedRecords"]
        );

        $this->log("Indexing $total records");

        // TODO: MAJORLY REFACTOR THIS
        foreach ($totalRecords as $index => $roID) {
            $record = RegistryObjectsRepository::getRecordByID($roID);

            $allRelationships = RelationshipProvider::getMergedRelationships($record);

            // update portal index
            $this->updatePortalIndex($record, $allRelationships);

            // update relation index
            $this->updateRelationIndex($record, $allRelationships);

            $this->updateProgress(
                $index, $total, "Processed ($index/$total) $record->title($roID)"
            );
        }

        $this->parent()->getCI()->solr->init()->setCore('portal')->commit();
        $this->parent()->getCI()->solr->init()->setCore('relations')->commit();
    }

    /**
     * @param $record
     * @param $relationships
     */
    public function updatePortalIndex($record, $relationships)
    {
        $this->parent()->getCI()->solr->init()->setCore('portal');
        // update portal index
        $updateDoc = [
            'id' => $record->registry_object_id
        ];

        foreach ($relationships as $relation) {
            $rel = $relation->format();
            $class = $rel['to_class'];
            if ($rel['to_class'] == 'party') {
                if ($rel['to_type'] == "group") {
                    $class = "party_multi";
                } else {
                    $class = "party_one";
                }
            }

            $relationType = is_array($rel['relation_type']) ? $rel['relation_type'] : [$rel['relation_type']];
            $updateDoc["related_".$class."_id"][] = $rel['to_id'];
            $updateDoc["related_".$class."_title"][] = $rel['to_title'];
            foreach ($relationType as $type) {
                $updateDoc["relationType_".$type."_id"][] = $rel['to_id'];
            }
        }

        // relation_grants_isFundedBy
        // relation_grants_isOutputOf
        // relation_grants_isPartOf

        foreach ($updateDoc as $key => &$value) {
            if (is_array($value)) {
                $value = array_unique($value);
            }
            if ($key != "id") {
                $value = ["set" => $value];
            }
        }

        $this->parent()->getCI()->solr->add_json(json_encode([$updateDoc]));
    }

    /**
     * @param $record
     * @param $relationships
     */
    public function updateRelationIndex($record, $relationships)
    {
        // delete all from_id
        $this->parent()->getCI()->solr->init()->setCore('relations');
        $this->parent()->getCI()->solr->deleteByQueryCondition('from_id:'.$record->registry_object_id);

        // add
        $docs = [];
        foreach ($relationships as $relation) {
            $doc = $relation->format();
            $doc['id'] = $doc['from_id'].$doc['to_key'];
            unset($doc['from_data_source_id']);
            unset($doc['to_data_source_id']);
            $doc['relation'] = [$doc['relation_type']];
            unset($doc['relation_type']);
            if (!is_array($doc['relation'])) {
                $doc['relation'] = [$doc['relation']];
                $doc['relation'] = [$doc['relation_origin']];
            }

            // to_finder is the title
            if ((in_array('funds', $doc['relation']) ||
                in_array('isFundedBy', $doc['relation']))
                && in_array($doc['to_class'], ['activity', 'collection'])
            ) {
                $doc['to_funder'] = $doc['from_title'];
            }

            $docs[] = $doc;
        }
        $this->parent()->getCI()->solr->add_json(json_encode($docs));
    }
}