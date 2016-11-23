<?php


namespace ANDS\Test;


use ANDS\API\Task\ImportTask;
use ANDS\DataSource;
use ANDS\Payload;
use ANDS\Registry\Providers\GrantsConnectionsProvider;
use ANDS\Registry\Providers\RelationshipProvider;
use ANDS\RegistryObject;
use ANDS\Repository\DataSourceRepository;
use ANDS\Repository\RegistryObjectsRepository;

/**
 * Class TestIndexRelationshipTask
 * @package ANDS\Test
 */
class TestIndexRelationshipTask extends UnitTest
{

    /** @test **/
    public function test_it_should_sample()
    {
        $record = RegistryObjectsRepository::getRecordByID(574582);
        $affected = RelationshipProvider::getAffectedIDs($record);
        dd($affected);

        $record = RegistryObjectsRepository::getRecordByID(574580);
        RelationshipProvider::process($record);
        $parents = GrantsConnectionsProvider::create()->init()->getParentsCollections($record);
        dd($parents->pluck('title'));
    }

    /** @test **/
    public function test_it_should_import_clean_grants_network()
    {
        $deleteTask = $this->deleteRecords();
//        var_dump($deleteTask->getBenchmarkData());
        $importTask = $this->importRecords("clean_grants_test_records.xml");
//        var_dump($importTask->getBenchmarkData());

        // funder of a1 is f1
        $a1 = RegistryObjectsRepository::getPublishedByKey("GrantsTestActivity1_key");
        $a1funder = GrantsConnectionsProvider::create()->getFunder($a1);
        $this->assertEquals($a1funder->key, "GrantsTestFunder1_key");

        // funder of a2 is f1
        $a2 = RegistryObjectsRepository::getPublishedByKey("GrantsTestActivity1_key");
        $a2funder = GrantsConnectionsProvider::create()->getFunder($a2);
        $this->assertEquals($a2funder->key, "GrantsTestFunder1_key");

        // funder of c3 is f1
        $c3 = RegistryObjectsRepository::getPublishedByKey("GrantsTestCollection3_key");
        $c3funder = GrantsConnectionsProvider::create()->getFunder($c3);
        $this->assertEquals($c3funder->key, "GrantsTestFunder1_key");

        // c1 producedBy a1 and a2
        $c1 = RegistryObjectsRepository::getPublishedByKey("GrantsTestCollection1_key");
        $c1parentActivities = GrantsConnectionsProvider::create()->getParentsActivities($c1);
        $keys = collect($c1parentActivities)->pluck('key')->toArray();
        $this->assertContains("GrantsTestActivity2_key", $keys);
        $this->assertContains("GrantsTestActivity1_key", $keys);

        // c2 has c1 as parent
        $c2 = RegistryObjectsRepository::getPublishedByKey("GrantsTestCollection2_key");
        $c2parentCollections = GrantsConnectionsProvider::create()->getParentsCollections($c2);
        $keys = collect($c2parentCollections)->pluck('key')->toArray();
        $this->assertContains("GrantsTestCollection1_key", $keys);

        // c3 has c1 as parent
        $c3 = RegistryObjectsRepository::getPublishedByKey("GrantsTestCollection3_key");
        $c3parentCollections = GrantsConnectionsProvider::create()->getParentsCollections($c3);
        $keys = collect($c3parentCollections)->pluck('key')->toArray();
        $this->assertContains("GrantsTestCollection1_key", $keys);

        // c2 has a1 and a2 as parent activities
        $c2 = RegistryObjectsRepository::getPublishedByKey("GrantsTestCollection2_key");
        $c2parentActivities = GrantsConnectionsProvider::create()->getParentsActivities($c2);
        $keys = collect($c2parentActivities)->pluck('key')->toArray();
        $this->assertContains("GrantsTestActivity2_key", $keys);
        $this->assertContains("GrantsTestActivity1_key", $keys);

        // c3 has a1 and a2 as parent activities
        $c3 = RegistryObjectsRepository::getPublishedByKey("GrantsTestCollection3_key");
        $c3parentActivities = GrantsConnectionsProvider::create()->getParentsActivities($c3);
        $keys = collect($c3parentActivities)->pluck('key')->toArray();
        $this->assertContains("GrantsTestActivity2_key", $keys);
        $this->assertContains("GrantsTestActivity1_key", $keys);

        // a4 has f1 as funder
        $a4 = RegistryObjectsRepository::getPublishedByKey("GrantsTestActivity4_key");
        $a4funder = GrantsConnectionsProvider::create()->getFunder($a4);
        $this->assertEquals($a4funder->key, "GrantsTestFunder1_key");

        // import the second part
        $importTask = $this->importRecords("clean_grants_test_records_part2.xml");

        // c7 does not have a funder
        $c7 = RegistryObjectsRepository::getPublishedByKey("GrantsTestCollection7_key");
        $c7funder = GrantsConnectionsProvider::create()->getFunder($c7);
        $this->assertNull($c7funder);

        // c4 does not have a funder
        $c4 = RegistryObjectsRepository::getPublishedByKey("GrantsTestCollection4_key");
        $c4funder = GrantsConnectionsProvider::create()->getFunder($c4);
        $this->assertNull($c4funder);

        // c1 has c4 as collection parent
        $c1 = RegistryObjectsRepository::getPublishedByKey("GrantsTestCollection1_key");
        $c1parentCollections = GrantsConnectionsProvider::create()->getParentsCollections($c1);
        $keys = collect($c1parentCollections)->pluck('key')->toArray();
        $this->assertContains("GrantsTestCollection4_key", $keys);

        // c3 has c4 as collection parent
        $c3 = RegistryObjectsRepository::getPublishedByKey("GrantsTestCollection3_key");
        $c3parentCollections = GrantsConnectionsProvider::create()->getParentsCollections($c3);
        $keys = collect($c3parentCollections)->pluck('key')->toArray();
        $this->assertContains("GrantsTestCollection4_key", $keys);

        // c1 has c6 has collection parent
        $c1 = RegistryObjectsRepository::getPublishedByKey("GrantsTestCollection1_key");
        $c1parentCollections = GrantsConnectionsProvider::create()->getParentsCollections($c1);
        $keys = collect($c1parentCollections)->pluck('key')->toArray();
        $this->assertContains("GrantsTestCollection6_key", $keys);

        // c5 has funder f1
        $c5 = RegistryObjectsRepository::getPublishedByKey("GrantsTestCollection5_key");
        $c5funder = GrantsConnectionsProvider::create()->getFunder($c5);
        $this->assertEquals($c5funder->key, "GrantsTestFunder1_key");

        // import part 3
        $this->importRecords("clean_grants_test_records_part3.xml");

        // a5 is the same as a4, so it has a funder
        $a5 = RegistryObjectsRepository::getPublishedByKey("GrantsTestActivity5_key");
        $a5funder = GrantsConnectionsProvider::create()->getFunder($a5);
        $this->assertEquals($a5funder->key, "GrantsTestFunder1_key");

        // TODO: Test SOLR relationship

//        $task = $this->deleteRecords();
    }

    /** @test **/
    public function test_it_should_contain_the_needed_relationship_index()
    {
        // $this->importRecords("clean_grants_test_records.xml");
        $dataSource = DataSourceRepository::getByKey("AUTEST1");
        $importTask = new ImportTask;
        $importTask->init([
            'params' => http_build_query([
                'ds_id' => $dataSource->data_source_id,
                'targetStatus' => 'PUBLISHED'
            ])
        ])->skipLoadingPayload()->enableRunAllSubTask()->initialiseTask();
        $importTask->setTaskData("importedRecords", [574582]);

        $processRelationshipTask = $importTask->getTaskByName("ProcessRelationships");
        $processRelationshipTask->run();
        var_dump($processRelationshipTask->getMessage());
//        var_dump($processRelationshipTask->getTaskData('benchmark'));

        $indexPortalTask = $importTask->getTaskByName("IndexPortal");
        $indexPortalTask->run();
//        var_dump($indexPortalTask->getTaskData('benchmark'));

        $indexRelationTask = $importTask->getTaskByName("IndexRelationship");
        $indexRelationTask->run();
//        var_dump($indexRelationTask->getTaskData('benchmark'));

//        $deleteTask = $this->deleteRecords();
    }

    /**
     * @param $file
     */
    private function importRecords($file)
    {
        $dataSource = DataSourceRepository::getByKey("AUTEST1");
        $importTask = new ImportTask();
        $importTask->init([
            'params' => http_build_query([
                'ds_id' => $dataSource->data_source_id,
                'targetStatus' => 'PUBLISHED'
            ])
        ])->skipLoadingPayload();

        $importTask->setPayload("grantsNetwork", new Payload(TEST_APP_PATH."core/data/$file"));
        $importTask->initialiseTask();
        $importTask->enableRunAllSubTask();
        $importTask->run();

        return $importTask;
    }

    /** @test **/
    public function test_it_should_update_relationship_of_a_record()
    {
        $importTask = new ImportTask();
        $importTask->init([
            'params' => http_build_query([
                'ds_id' => 213,
                'targetStatus' => 'PUBLISHED'
            ])
        ])->skipLoadingPayload();

        $ids = RegistryObject::where('data_source_id', 213)
            ->where('status', 'PUBLISHED')
            ->get()->pluck('registry_object_id')
            ->toArray();

        $importTask->setTaskData('importedRecords', $ids);

        $importTask->initialiseTask();
        $processRelationship = $importTask->getTaskByName("ProcessRelationships");
        $processRelationship->run();

        $indexPortal = $importTask->getTaskByName("IndexPortal");
        $indexPortal->run();

        $indexRelation = $importTask->getTaskByName("IndexRelationship");
        $indexRelation->run();

    }

    private function deleteRecords()
    {
        $dataSource = DataSourceRepository::getByKey("AUTEST1");
        $records = RegistryObjectsRepository::getRecordsByDataSourceIDAndStatus($dataSource->data_source_id, "PUBLISHED", 0, 100);
        $ids = collect($records)->pluck('registry_object_id')->toArray();
        $importTask = new ImportTask();
        $importTask->init([
            'params' => http_build_query([
                'ds_id' => $dataSource->data_source_id,
                'pipeline' => 'PublishingWorkflow'
            ])
        ])->skipLoadingPayload()->enableRunAllSubTask()->initialiseTask();
        $importTask->setTaskData("deletedRecords", $ids);
        $importTask->run();

        return $importTask;
    }


    public function setUpBeforeClass()
    {
        initEloquent();
    }

    public function tearDownAfterClass()
    {
        $files = [];
        foreach (glob(TEST_APP_PATH. "core/data/*.processed") as $filename) {
            $files[] = $filename;
        }
        foreach (glob(TEST_APP_PATH. "core/data/*.validated") as $filename) {
            $files[] = $filename;
        }
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}