<?php


namespace ANDS\API\Task\ImportSubTask;

use ANDS\RegistryObject;
use ANDS\Repository\RegistryObjectsRepository;
use ANDS\Registry\Providers\RelationshipProvider;
use Illuminate\Support\Collection;

/**
 * Class ProcessDelete
 * @package ANDS\API\Task\ImportSubTask
 */
class ProcessDelete extends ImportSubTask
{
    protected $requireDeletedRecords = true;
    protected $title = "DELETING RECORDS";

    public function run_task()
    {
        $deletedRecords = $this->parent()->getTaskData('deletedRecords');

        $publishedRecords = [];
        $draftRecords = [];
        foreach ($deletedRecords as $id) {
            $record = RegistryObject::find($id);
            if ($record && $record->isPublishedStatus()) {
                $publishedRecords[] = $record;
            } elseif ($record && $record->isDraftStatus()) {
                $draftRecords[] = $record;
            } else {
                $this->log("Record with ID " . $id . " doesn't exist for deletion");
            }
        }

        if (count($draftRecords) > 0) {
            $this->deleteDraftRecords($draftRecords);
        }

        if (count($publishedRecords) > 0) {
            $this->deletePublishedRecords($publishedRecords);
        }
    }

    /**
     * deleting DRAFT RegistryObject
     * will delete every instance of this records
     *
     * @param $records
     */
    public function deleteDraftRecords($records)
    {
        $count = count($records);
        $this->log("Deleting $count DRAFT records");

        // TODO: refactor to reduce SQL queries
        foreach ($records as $record) {
            RegistryObjectsRepository::completelyEraseRecordByID($record->registry_object_id);
            $this->log("Record $record->registry_object_id ($record->status) is completely DELETED");
        }
    }

    /**
     * A mini workflow to delete published RegistryObject
     * soft-delete implementation
     *
     * @param $records
     */
    public function deletePublishedRecords($records)
    {
        $deletedRecords = $this->parent()->getTaskData('deletedRecords');

        $count = count($records);
        $this->log("Deleting $count PUBLISHED records");

        // placeholder for index queries
        $portalQuery = "";
        $fromRelationQuery = "";
        $toRelationQuery = "";

        $ids = collect($records)->pluck('registry_object_id')->toArray();

        // get the affected records IDs before deleting the PUBLISHED records
        $affectedRecordIDs = RelationshipProvider::getAffectedIDsFromIDs($ids);

        // TODO: refactor to reduce SQL queries
        foreach ($records as $record) {
            $record->status = "DELETED";
            $record->save();

            // delete everything that could hold a problem
            // identifier
            RegistryObject\Identifier::where('registry_object_id', $record->registry_object_id)->delete();

            // identifier relation
            IdentifierRelationship::where('registry_object_id', $record->registry_object_id)->delete();

            // all relationships
            Relationship::where('registry_object_id', $record->registry_object_id)->delete();
            ImplicitRelationship::where('from_id', $record->registry_object_id)->delete();


            $portalQuery .= " id:$record->registry_object_id";
            $fromRelationQuery .= " from_id:$record->registry_object_id";
            $toRelationQuery .= " to_id:$record->registry_object_id";

            $this->parent()->incrementTaskData("recordsDeletedCount");
        }

        // there are nothing to be added to the affected here, because there
        // should be no new identifiers or anything created after the delete

        $this->log("Size of affected records: ".count($affectedRecordIDs));

        // get currently affected records, if set, merge
        $currentAffectedRecords = $this->parent()->getTaskData('affectedRecords');
        if ($currentAffectedRecords) {
            $affectedRecordIDs = array_merge($currentAffectedRecords, $affectedRecordIDs);
        }

        // only set if affected is greater than 0
        if (sizeof($affectedRecordIDs) > 0) {
            $this->parent()->setTaskData('affectedRecords', $affectedRecordIDs);
        }

        // delete from the solr index
        $this->parent()->getCI()->load->library('solr');
        $this->parent()->getCI()->solr->init()
            ->setCore('portal')
            ->deleteByQueryCondition($portalQuery);
        $this->parent()->getCI()->solr->commit();

        $this->parent()->getCI()->solr->init()
            ->setCore('relations')
            ->deleteByQueryCondition($fromRelationQuery.$toRelationQuery);
        $this->parent()->getCI()->solr->commit();
    }
}