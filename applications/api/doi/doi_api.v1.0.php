<?php
namespace ANDS\API;

use ANDS\DOI\DataCiteClient;
use ANDS\DOI\DOIServiceProvider;
use ANDS\DOI\Formatter\XMLFormatter;
use ANDS\DOI\Formatter\JSONFormatter;
use ANDS\DOI\Formatter\StringFormatter;
use ANDS\DOI\Repository\ClientRepository;
use ANDS\DOI\Repository\DoiRepository;
use \Exception as Exception;

class Doi_api
{

    protected $providesOwnResponse = false;
    public $outputFormat = "xml";

    private $client = null;

    public function handle($method = array())
    {
        $this->ci = &get_instance();
        $this->dois_db = $this->ci->load->database('dois', true);


        $this->params = array(
            'submodule' => isset($method[1]) ? $method[1] : 'list',
            'identifier' => isset($method[2]) ? $method[2] : false,
            'object_module' => isset($method[3]) ? $method[3] : false,
        );

        // common DOI API
        if (strpos($this->params['submodule'], '.' )  > 0 ) {
            if (strpos($this->params['submodule'],'10.') === false){
                return $this->handleDOIRequest();
            }
        }

        //everything under here requires a client, app_id
        $this->getClient();

        //get a potential DOI
        if ($this->params['object_module']) {
            array_shift($method);
            $potential_doi = join('/',$method);
            if ($doi = $this->getDOI($potential_doi)) {
                $doi->title = $this->getDoiTitle($doi->datacite_xml);
                return $doi;
            }
        }

        // extended DOI API
        try {
            if ($this->params['submodule'] == 'list') {
                return $this->listDois();
            } elseif ($this->params['submodule'] == 'log') {
                return $this->activitiesLog();
            } elseif ($this->params['submodule'] == 'client') {
                return $this->clientDetail();
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

    }

    /**
     * Handles all DOI related request
     */
    private function handleDOIRequest()
    {
        $this->providesOwnResponse = true;
        $split = explode('.', $this->params['submodule']);
        $method = $split[0];
        $format = $split[1];

        if ($format == "xml") {
            $this->outputFormat = "text/xml";
            $formater = new XMLFormatter();
        } else if ($format == 'json'){
            $formater = new JSONFormatter();
        }else if ($format == 'string'){
            $formater = new StringFormatter();
        }

        $appID = $this->ci->input->get('app_id');
        $sharedSecret = $this->ci->input->get('shared_secret');
        $manual = $this->ci->input->get('manual');

        if(!$appID && isset($_SERVER['PHP_AUTH_USER'])) {
            $appID = $_SERVER['PHP_AUTH_USER'];
        }

        if(!$sharedSecret && isset($_SERVER['PHP_AUTH_USER'])) {
            $sharedSecret = $_SERVER["PHP_AUTH_PW"];
        }

        if (!$appID) {
            return $formater->format([
                'responsecode' => 'MT010',
                'verbosemessage' => 'You must provide an app id to mint a doi'
            ]);
        }

        $clientRepository = new ClientRepository(
            $this->dois_db->hostname, 'dbs_dois', $this->dois_db->username, $this->dois_db->password
        );

        $doiRepository = new DoiRepository(
            $this->dois_db->hostname, 'dbs_dois', $this->dois_db->username, $this->dois_db->password
        );

        $client = $clientRepository->getByAppID($appID);


        $dataciteClient = new DataCiteClient(
            get_config_item("gDOIS_DATACENTRE_NAME_PREFIX").".".get_config_item("gDOIS_DATACENTRE_NAME_MIDDLE").str_pad($client->client_id,2,"-",STR_PAD_LEFT), get_config_item("gDOIS_DATACITE_PASSWORD")
        );


        $dataciteClient->setDataciteUrl(get_config_item("gDOIS_SERVICE_BASE_URI"));

        $doiService = new DOIServiceProvider($clientRepository, $doiRepository, $dataciteClient);


        $doiService->authenticate(
            $appID,
            $sharedSecret,
            $this->getIPAddress(),
            $manual
        );

        // @todo check authenticated client

        switch ($method) {
            case "mint":
                 $doiService->mint(
                    $this->ci->input->get('url'),
                    $this->getPostedXML()
                );
                break;

            case "update":
                $doiService->update(
                    $this->ci->input->get('doi'),
                    $this->ci->input->get('url'),
                    $this->getPostedXML()
                );
                break;
            case "activate":
                $doiService->activate(
                    $this->ci->input->get('doi')
                );
                break;
            case "deactivate":
                $doiService->deactivate(
                    $this->ci->input->get('doi')
                );
                break;
        }

        if($manual){
            $manual="m_";
        } else{
            $manual='';
        }

        $this->doilog($doiService->getResponse(),'doi_'.$manual.$method,$client);


        // as well as set the HTTP header here
        if($format=="xml") {
            return $formater->format($doiService->getResponse());
        }
        else if ($format=='json'){
            return $formater->format($doiService->getResponse());
        }
        else if ($format=='string'){
            return $formater->format($doiService->getResponse());
        }else {
            return $doiService->getResponse();
        }


    }

    private function getIPAddress()
    {
        if ( isset($_SERVER["HTTP_X_FORWARDED_FOR"]) )    {
            return $_SERVER["HTTP_X_FORWARDED_FOR"];
        } else if ( isset($_SERVER["HTTP_CLIENT_IP"]) )    {
            return $_SERVER["HTTP_CLIENT_IP"];
        } else if ( isset($_SERVER["REMOTE_ADDR"]) )    {
            return $_SERVER["REMOTE_ADDR"];
        } else {
            // Run by command line??
            return "127.0.0.1";
        }
    }

    private function getPostedXML()
    {
        $output= '';
        $data = file_get_contents("php://input");
        parse_str(htmlentities($data), $output);
        if (isset($output['xml'])) {
            return trim($output['xml']);
        } elseif (count($output) > 1) {
            //hotfix to return XML that is not empty,
            //implode($output) returns empty for no reason
            //todo verify and check
            return trim($data);
            //return trim(implode($output));
        } else {
            return trim($data);
        }
    }


    private function getAssociateAppID($role_id)
    {
        if (!$role_id) throw new Exception('role id required');
        $result = array();
        $roles_db = $this->ci->load->database('roles', true);
        $user_affiliations = array('1');
        $roles_db->distinct()->select('*')
                // ->where_in('child_role_id', $user_affiliations)
                ->where('role_type_id', 'ROLE_DOI_APPID      ', 'after')
                ->join('roles', 'role_id = parent_role_id')
                ->from('role_relations');
        $query = $roles_db->get();

        dd($query->result());

        if ($query->num_rows() > 0) {
            foreach ($query->result() AS $r) {
                $result[] = $r->parent_role_id;
            }
        }
        return $result;
    }

    private function getDOI($doi)
    {
        $query = $this->dois_db
            ->where('doi_id', $doi)
            ->get('doi_objects');
        if ($query->num_rows() > 0) {
            $result = $query->first_row();
            return $result;
        } else return false;
    }

    private function getClient()
    {
        $app_id = $this->ci->input->get('app_id') ? $this->ci->input->get('app_id') : false;

        if (!$app_id) {
            throw new Exception('App ID required');
        }

        $query = $this->dois_db
            ->where('app_id', $app_id)
            ->select('*')
            ->get('doi_client');

        if (!$this->client = $query->result()) {
            throw new Exception('Invalid App ID');
        }

        //permitted_url_domains
        $this->client = array_pop($this->client);
        $query = $this->dois_db
            ->where('client_id',$this->client->client_id)
            ->select('client_domain')
            ->get('doi_client_domains');
        foreach ($query->result_array() AS $domain) {
            $this->client->permitted_url_domains[] =  $domain['client_domain'];
        }
    }

    private function clientDetail()
    {
        return array(
            'client' => $this->client,
        );
    }

    public function isProvidingOwnResponse()
    {
        return $this->providesOwnResponse;
    }

    private function listDois()
    {
        $limit = $this->ci->input->get('limit') ?: 50;
        $offset = $this->ci->input->get('offset') ?: 0;
        $search = $this->ci->input->get('search') ?: '';

        $query = $this->dois_db
            ->order_by('updated_when', 'desc')
            ->order_by('created_when', 'desc')
            ->where('client_id', $this->client->client_id)
            ->limit($limit, $offset)
            ->where('status !=', 'REQUESTED')
            ->select('*');

        if ($search) {
            $query = $this->dois_db->where("doi_id LIKE '%{$search}%'");
        }

        $query = $this->dois_db
            ->get('doi_objects');

        $data['dois'] = array();
        foreach ($query->result() as $doi) {
            $obj = $doi;
            $obj->title = $this->getDoiTitle($doi->datacite_xml);
            $data['dois'][] = $obj;
        }

        $data['total'] = $this->dois_db
            ->where('client_id', $this->client->client_id)
            ->where('status !=', 'REQUESTED')
            ->where("doi_id LIKE '%{$search}%'")
            ->count_all_results('doi_objects');

        return $data;
    }

    private function getDoiTitle($doiXml)
    {
        $doiObjects = new \DOMDocument();
        $titleFragment = 'No Title';
        if (strpos($doiXml, '<') === 0) {
            $result = $doiObjects->loadXML(trim($doiXml));
            $titles = $doiObjects->getElementsByTagName('title');

            if ($titles->length > 0) {
                $titleFragment = '';
                for ($j = 0; $j < $titles->length; $j++) {
                    if ($titles->item($j)->getAttribute("titleType")) {
                        $titleType = $titles->item($j)->getAttribute("titleType");
                        $title = $titles->item($j)->nodeValue;
                        $titleFragment .= $title . " (" . $titleType . ") ";
                    } else {
                        $titleFragment .= $titles->item($j)->nodeValue;
                    }
                }
            }
        } else {
            $titleFragment = $doiXml;
        }

        return $titleFragment;

    }

    private function activitiesLog()
    {
        $offset = $this->ci->input->get('start') ? $this->ci->input->get('start') : 0;
        $limit = $this->ci->input->get('limit') ? $this->ci->input->get('limit') : 50;
        $query = $this->dois_db
            ->order_by('timestamp', 'desc')
            ->where('client_id', $this->client->client_id)
            ->select('*')
            ->limit($limit)->offset($offset)
            ->get('activity_log');
        $data['activities'] = $query->result();
        return $data;
    }


    private function doilog($log_response,$event="doi_xml",$client=NULL){


        $message = array();
        $message["event"] = strtolower($event);
        $message["response"]= $log_response;
        $message["doi"]["id"] = $log_response["doi"];
        $message["client"]["id"] = NULL;
        $message["client"]["name"] = NULL;

        //determine client name
        if($client){
            $message["client"]["name"] = $client->client_name;
            $message["client"]["id"] = $client->client_id;
        }

        //determine if event is manual or m2m
        if(strtolower(substr($event,0,6))=='doi_m_'){
            $message['request']['manual']= true;
            $message["event"] = str_replace("_m_","_", $message["event"]);
        }else{
            $message['request']['manual']= false;
        }

        //determine if doi is a test doi
        $test_check = strpos($log_response["doi"],'10.5072');
        if($test_check||$test_check===0) {
            $message["doi"]["production"] = false;
        }else{
            $message["doi"]["production"] = true;
        }

        $message["api_key"] = $log_response["app_id"];

        monolog($message,"doi_api", "info", true) ;

    }


    public function __construct()
    {
        $this->ci = &get_instance();
        require_once APP_PATH . 'vendor/autoload.php';
    }
}
