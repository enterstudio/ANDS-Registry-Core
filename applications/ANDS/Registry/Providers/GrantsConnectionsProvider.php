<?php


namespace ANDS\Registry\Providers;


use ANDS\Registry\Connections;
use ANDS\Registry\IdentifierRelationshipView;
use ANDS\RegistryObject;
use ANDS\Repository\RegistryObjectsRepository;
use Illuminate\Support\Collection;

/**
 * Class GrantsConnectionsProvider
 * @package ANDS\Registry\Providers
 */
class GrantsConnectionsProvider extends Connections
{

    /**
     * Get a funder of a particular record
     * TODO: when traversing a node that has a cached funder, return that
     *
     * @param RegistryObject $record
     * @return RegistryObject|null
     */
    public function getFunder(RegistryObject $record)
    {
        // see if it's saved in the metadata
        $saved = $record->getRegistryObjectMetadata('funder_id');
        if ($saved) {
            return RegistryObjectsRepository::getRecordByID($saved->value);
        }

        // if there's a direct funder, get that one
        $funder = $this->getDirectFunder($record);
        if ($funder !== null) {
            return $funder;
        }

        // if the node is a collection, look for parents collection
        if ($record->class == 'collection') {
            $parentCollections = $this->getDirectGrantCollections($record);
            foreach ($parentCollections as $collection) {
                $funder = $this->getFunder($collection);
                if ($funder !== null) {
                    return $funder;
                }
            }

            // and look for activities that produces this collection
            $activityProducers = $this->getDirectActivityProducer($record);
            foreach ($activityProducers as $activity) {
                $funder = $this->getFunder($activity);
                if ($funder !== null) {
                    return $funder;
                }
            }
        }

        // the record is an activity, find activities that this record is a part of
        if ($record->class == 'activity') {
            // from this node, find a funder of every activity that it is a part of
            $parentActivities = $this->getDirectGrantActivities($record);
            foreach ($parentActivities as $activity) {
                $funder = $this->getFunder($activity);
                if ($funder !== null) {
                    return $funder;
                }
            }
        }

        // find funders of duplicate records
        $duplicateRecords = $record->getDuplicateRecords();
        foreach ($duplicateRecords as $duplicate) {
            $funder = $this->getFunder($duplicate);
            if ($funder !== null) {
                return $funder;
            }
        }

        return null;
    }

    /**
     * Get parent activity
     * for collection node
     * relatedObject[class=activity][relation_type=isOutputOf]
     *
     * @param $record
     * @return array
     */
    public function getDirectActivityProducer($record)
    {
        $activities = [];

        // direct
        $direct = $this->init()
            ->setFilter('from_key', $record->key)
            ->setFilter('relation_type', 'isOutputOf')
            ->setFilter('to_class', 'activity')
            ->setLimit(300)
            ->get();

        if (count($direct) > 0) {
            foreach ($direct as $relation) {
                $activities[] = $relation->getObjects()->to();
            }
        }

        // reverse
        $reverse = $this->init()
            ->setFilter('to_key', $record->key)
            ->setFilter('relation_type', ['hasOutput', 'outputs', 'produces'])
            ->setFilter('from_class', 'activity')
            ->setLimit(300)
            ->get();


        if (count($reverse) > 0) {
            foreach ($reverse as $relation) {
                $activities[] = $relation->from();
            }
        }


        // identifier

        $directIdentifierRelationship = IdentifierRelationshipView::where('from_key', $record->key)->where('relation_type', 'isOutputOf')->get();
        foreach ($directIdentifierRelationship as $relation) {
            $activities[] = RegistryObjectsRepository::getPublishedByKey($relation->to_key);
        }

        $reverseIdentifierRelationship = IdentifierRelationshipView::where('to_key', $record->key)->where('relation_type', ['hasOutput', 'outputs', 'produces'])->get();
        foreach ($reverseIdentifierRelationship as $relation) {
            $activities[] = RegistryObjectsRepository::getPublishedByKey($relation->from_key);
        }

        return $activities;
    }

    /**
     * Get parent collection
     * for collection node
     * relatedObject[class=collection][relation_type=isPartOf]
     *
     * @param $record
     * @return array
     */
    public function getDirectGrantCollections($record)
    {
        $collections = [];

        $direct = $this->init()
            ->setFilter('from_key', $record->key)
            ->setFilter('relation_type', 'isPartOf')
            ->setFilter('to_class', 'collection')
            ->setLimit(300)
            ->get();

        if (count($direct) > 0) {
            foreach ($direct as $relation) {
                $collections[] = $relation->to();
            }
        }

        $reverse = $this->init()
            ->setFilter('to_key', $record->key)
            ->setFilter('relation_type', 'hasPart')
            ->setFilter('from_class', 'collection')
            ->setLimit(300)
            ->get();

        if (count($reverse) > 0) {
            foreach ($reverse as $relation) {
                $collections[] = $relation->from();
            }
        }

        $directIdentifierRelationship = IdentifierRelationshipView::where('from_key', $record->key)->where('relation_type', 'isPartOf')->get();
        foreach ($directIdentifierRelationship as $relation) {
            $collections[] = RegistryObjectsRepository::getPublishedByKey($relation->to_key);
        }

        $reverseIdentifierRelationship = IdentifierRelationshipView::where('to_key', $record->key)->where('relation_type', 'hasPart')->get();
        foreach ($reverseIdentifierRelationship as $relation) {
            $collections[] = RegistryObjectsRepository::getPublishedByKey($relation->from_key);
        }

        return $collections;
    }

    /**
     * get parent activities
     * for activity
     * relatedObject[class=activity][relation_type=isPartOf]
     *
     * @param $record
     * @return array
     */
    public function getDirectGrantActivities($record)
    {

        $activities = [];

        $direct = $this->init()
            ->setFilter('from_key', $record->key)
            ->setFilter('relation_type', 'isPartOf')
            ->setFilter('to_class', 'activity')
            ->setLimit(300)
            ->get();

        if (count($direct) > 0) {
            foreach ($direct as $relation) {
                $activities[] = $relation->to();
            }
        }

        $reverse = $this->init()
            ->setFilter('to_key', $record->key)
            ->setFilter('relation_type', 'hasPart')
            ->setFilter('from_class', 'activity')
            ->setLimit(300)
            ->get();

        if (count($reverse) > 0) {
            foreach ($reverse as $relation) {
                $activities[] = $relation->from();
            }
        }

        return $activities;
    }

    /**
     * Returns the Funder Object
     * that is directly related to given object
     *
     * @param RegistryObject $record
     * @return RegistryObject | null
     */
    public function getDirectFunder(RegistryObject $record)
    {
        // find a direct relation
        $direct = $this->init()
            ->setFilter('from_key', $record->key)
            ->setFilter('to_class', 'party')
            ->setFilter('relation_type', 'isFundedBy')
            ->setLimit(1)
            ->get();

        if (count($direct) > 0) {
            $relation = array_first($direct);
            $funder = $relation->getObjects()->to();
            return $funder;
        }

        // if there's no direct relation, find a reverse one
        $reverse = $this->init()
            ->setFilter('to_key', $record->key)
            ->setFilter('from_class', 'party')
            ->setFilter('relation_type', 'funds')
            ->setLimit(1)
            ->get();

        if (count($reverse) > 0) {
            $relation = array_first($reverse);
            $funder = $relation->getObjects()->from();
            return $funder;
        }

        // no direct funder
        return null;
    }

    /**
     * Get all parents activities from a given node
     * TODO: cache
     *
     * @param RegistryObject $record
     * @param array $processed
     * @return array
     */
    public function getParentsActivities(RegistryObject $record, $processed = [])
    {
        // check saved
        $saved = $record->getRegistryObjectMetadata('parents_activity_ids');
        if ($saved) {
            return RegistryObject::whereIn(
                'registry_object_id', explode(',', $saved->value)
            )->get();
        }

        $activities = new Collection();

        if ($record->class == "activity") {
             $activities = $this->getDirectGrantActivities($record);
        }

        if ($record->class == "collection") {
            $activities = $this->getDirectActivityProducer($record);
        }

        if (count($processed) == 0) {
            $processed = collect($activities)->pluck('registry_object_id')->unique()->toArray();
        }

        if (count($activities) == 0) {
            $parentCollections = $this->getDirectGrantCollections($record);
            if (count($parentCollections) > 0) {
                foreach ($parentCollections as $parentCollection) {
                    $grandParents = $this->getParentsActivities($parentCollection, $processed);
                    // make sure to only include grandParents who have not already been processed
                    $grandParents = collect($grandParents)
                        ->filter(function($item) use ($processed){
                        return !in_array($item->registry_object_id, $processed);
                    });

                    if (count($grandParents) > 0) {
                        $activities = collect($activities)->merge($grandParents);
                    }
                }
            }
        }

        foreach ($activities as $parentActivity) {
            $grandParents = $this->getParentsActivities($parentActivity, $processed);

            // make sure to only include grandParents who have not already been processed
            $grandParents = collect($grandParents)->filter(function($item) use ($processed){
               return !in_array($item->registry_object_id, $processed);
            });

            if ($grandParents->count() > 0) {
                $activities = collect($activities)->merge($grandParents);
            }
        }

        return $activities;
    }

    /**
     * Get all parents collections from a given node
     * TODO: cache
     * @param RegistryObject $record
     * @param array $processed
     * @return array
     */
    public function getParentsCollections(RegistryObject $record, $processed = [])
    {
        // check saved
        $saved = $record->getRegistryObjectMetadata('parents_collection_ids');
        if ($saved) {
            return RegistryObject::whereIn(
                'registry_object_id', explode(',', $saved->value)
            )->get();
        }

        $collections = $this->getDirectGrantCollections($record);

        if (count($processed) == 0) {
            $processed = collect($collections)->pluck('registry_object_id')->toArray();
        }

        foreach ($collections as $parent) {
            $grandParents = $this->getParentsCollections($parent, $processed);

            // make sure to only include grandParents who have not already been processed
            $grandParents = collect($grandParents)->filter(function($item) use ($processed){
                return !in_array($item->registry_object_id, $processed);
            });

            if (count($grandParents) > 0) {
                $collections = collect($collections)->merge($grandParents);
            }
        }

        return $collections;
    }

}