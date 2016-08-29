<?php

namespace ANDS\Test;


use ANDS\API\Task\ImportSubTask\PopulateImportOptions;
use ANDS\API\Task\ImportSubTask\ValidatePayload;
use ANDS\API\Task\ImportTask;

class TestValidatePayload extends UnitTest
{
    /** @test * */
    public function test_it_should_be()
    {
        $task = $this->getImportTask();
        $this->assertEquals("ValidatePayload", $task->name);
        $this->assertEquals("PENDING", $task->status);
    }

    /** @test **/
    public function test_it_should_load_payload_to_parent_task()
    {
        $task = $this->getImportTask();
        $task->loadPayload();
        $payload = $task->parent()->getPayloads();
        $this->assertTrue(is_array($payload));
        $this->assertTrue(count($payload) > 0);
        $this->assertTrue(count(array_first($payload)) > 0);
    }


    /** @test **/
    public function test_it_should_validate_rifcs_xml()
    {
        $task = $this->getImportTask();
        $task->run();
        //@todo check validated file generated
        //@todo check parent payload updated to be validated form
    }

    /** @test */
    public function test_it_should_validate_rifcs_xml_but_remove_invalidated_ones()
    {
        $task = $this->getImportTask();
        $task->parent()->setBatchID("AUTestingRecords");

        $task->loadPayload();
        $xml = array_first($task->parent()->getPayloads());
        $this->assertEquals(15, $task->countElement($xml, 'registryObject'));

        $task->run();
        $xml = array_first($task->parent()->getPayloads());
        $this->assertEquals(13, $task->countElement($xml, 'registryObject'));
    }

    private function getImportTask()
    {
        $importTask = new ImportTask();
        $importTask->init([
            'name' => 'ImportTask',
            'params' => 'ds_id=209&batch_id=593EB384AFFE59EAEB2CADE99E39454361C1C0AC'
        ])->loadParams()->loadSubTasks();
        $task = $importTask->getTaskByName("ValidatePayload");
        return $task;
    }

    public function setUp()
    {
        $this->ci->load->model('registry/data_source/data_sources', 'ds');
        require_once(API_APP_PATH.'vendor/autoload.php');
    }

    public function tearDown()
    {
        // @todo delete _validated.xml
        // @todo delete _processed.xml
    }

}