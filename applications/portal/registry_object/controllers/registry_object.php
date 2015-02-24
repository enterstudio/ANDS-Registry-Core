<?php

/**
 * Class Registry_object
 */
class Registry_object extends MX_Controller {

	private $components = array();

	/**
	 * Viewing a single registry object
	 * @return HTML generated by view
	 * @internal param $_GET ['id'] parsed through the dispatcher
	 * @todo  $_GET['slug'] or $_GET['any']
	 */
	function view(){

		if($this->input->get('id')){
			$ro = $this->ro->getByID($this->input->get('id'));
		}

		$this->load->library('blade');

		$theme = ($this->input->get('theme') ? $this->input->get('theme') : '2-col-wrap');

        switch($ro->core['class']){
            case 'collection':
                $render = 'registry_object/view';
                break;
            case 'activity':
                $render = 'registry_object/activity';
                $theme = ($this->input->get('theme') ? $this->input->get('theme') : 'activity');
                break;
            default:
                $render = 'registry_object/view';
                break;
        }

		//record event
		$ro->event('view');
		ulog_terms(
			array(
				'event' => 'portal_view',
				'roid' => $ro->core['id'],
				'roclass' => $ro->core['class'],
				'dsid' => $ro->core['data_source_id'],
				'group' => $ro->core['group'],
				'ip' => $this->input->ip_address(),
				'user_agent' => $this->input->user_agent()
			),'portal', 'info'
		);

		$this->blade
			->set('scripts', array('view', 'view_app', 'tag_controller'))
			->set('lib', array('jquery-ui', 'dynatree', 'qtip', 'map'))
			->set('ro', $ro)
			->set('contents', $this->components['view'])
            ->set('activity_contents',$this->components['activity'])
			->set('aside', $this->components['aside'])
            ->set('activity_aside', $this->components['activity_aside'])
            ->set('view_headers', $this->components['view_headers'])
			->set('url', $ro->construct_api_url())
			->set('theme', $theme)
			->render($render);
	}

	function preview() {
		$this->load->library('blade');

		if ($this->input->get('ro_id')){
			$ro = $this->ro->getByID($this->input->get('ro_id'));
			$this->blade
				->set('ro', $ro)
				->render('registry_object/preview');
		} elseif($this->input->get('identifier_relation_id')) {

			//hack into the registry network and grab things
			//@todo: figure things out for yourself
			$rdb = $this->load->database('registry', TRUE);
			$result = $rdb->get_where('registry_object_identifier_relationships', array('id'=>$this->input->get('identifier_relation_id')));

			if ($result->num_rows() > 0) {
				$fr = $result->first_row();

				$ro = false;

				$pullback = false;
				//ORCID "Pull back"
				if($fr->related_info_type=='party' && $fr->related_object_identifier_type == 'orcid' && isset($fr->related_object_identifier)) {
					$pullback = $this->ro->resolveIdentifier('orcid', $fr->related_object_identifier);
					$filters = array('identifier_value'=>$fr->related_object_identifier);
					$ro = $this->ro->findRecord($filters);
				}

				$this->blade
					->set('record', $fr)
					->set('ro', $ro)
					->set('pullback', $pullback)
					->render('registry_object/preview-identifier-relation');
			}
		} else if ($this->input->get('identifier_doi')) {
			$identifier = $this->input->get('identifier_doi');
			
			//DOI "Pullback"
			$pullback = $this->ro->resolveIdentifier('doi', $identifier);
			$ro = $this->ro->findRecord(array('identifier_value'=>$identifier));

			$this->blade
				->set('ro', $ro)
				->set('pullback', $pullback)
				->render('registry_object/preview_doi');
		}
	}

	function vocab($vocab='anzsrc-for') {
		$uri = $this->input->get('uri');
		$data = json_decode(file_get_contents("php://input"), true);
		header('Cache-Control: no-cache, must-revalidate');
		header('Content-type: application/json');
		set_exception_handler('json_exception_handler');
		$filters = $data['filters'];
		$this->load->library('vocab');
		if (!$uri) { //get top level
			$toplevel = $this->vocab->getTopLevel('anzsrc-for', $filters);
			// foreach ($toplevel['topConcepts'] as &$l) {
			// 	$r = array();
			// 	$result = json_decode($this->vocab->getConceptDetail('anzsrc-for', $l['uri']), true);
			// 	if(isset($result['result']['primaryTopic']['narrower'])){
			// 		foreach($result['result']['primaryTopic']['narrower'] as $narrower) {
			// 			$curi = $narrower['_about'];
			// 			$concept = json_decode($this->vocab->getConceptDetail('anzsrc-for', $curi), true);
			// 			$concept = array(
			// 				'notation' => $concept['result']['primaryTopic']['notation'],
			// 				'prefLabel' => $concept['result']['primaryTopic']['prefLabel']['_value'],
			// 				'uri' => $curi,
			// 				'collectionNum' => $this->vocab->getNumCollections($curi, array())
			// 			);
			// 			array_push($r, $concept);
			// 		}
			// 	}
			// 	$l['subtree'] = $r;
			// }
			echo json_encode($toplevel['topConcepts']);
		} else {
			$r = array();
			$result = json_decode($this->vocab->getConceptDetail('anzsrc-for', $uri), true);
			if(isset($result['result']['primaryTopic']['narrower'])){
				foreach($result['result']['primaryTopic']['narrower'] as $narrower) {
					$curi = $narrower['_about'];
					$concept = json_decode($this->vocab->getConceptDetail('anzsrc-for', $curi), true);
					$concept = array(
						'notation' => $concept['result']['primaryTopic']['notation'],
						'prefLabel' => $concept['result']['primaryTopic']['prefLabel']['_value'],
						'uri' => $curi,
						'collectionNum' => $this->vocab->getNumCollections($curi, $filters)
					);
					array_push($r, $concept);
				}
			}
			echo json_encode($r);
		}
	}

	function getSubjects() {
		header('Cache-Control: no-cache, must-revalidate');
		header('Content-type: application/json');
		set_exception_handler('json_exception_handler');
		$result = array();
		foreach($this->config->item('subjects') as $subject) {
			$slug = url_title($subject['display'], '-', true);
			foreach($subject['codes'] as $code) {
				$result[$slug][] = 'http://purl.org/au-research/vocabulary/anzsrc-for/2008/'.$code;
			}
		}
		echo json_encode($result);
	}

	function resolveSubjects() {
		header('Cache-Control: no-cache, must-revalidate');
		header('Content-type: application/json');
		set_exception_handler('json_exception_handler');
		$data = json_decode(file_get_contents("php://input"), true);
		$subjects = $data['data'];

		$this->load->library('vocab');

		$result = array();

		if (is_array($subjects)) {
			foreach ($subjects as $subject) {
				$r = json_decode($this->vocab->getConceptDetail('anzsrc-for', 'http://purl.org/au-research/vocabulary/anzsrc-for/2008/'.$subject), true);
				$result[$subject] = $r['result']['primaryTopic']['prefLabel']['_value'];
			}
		} else {
			$r = json_decode($this->vocab->getConceptDetail('anzsrc-for', 'http://purl.org/au-research/vocabulary/anzsrc-for/2008/'.$subjects), true);
			$result[$subjects] = $r['result']['primaryTopic']['prefLabel']['_value'];
		}

		
		echo json_encode($result);
	}

	function addTag() {
		header('Cache-Control: no-cache, must-revalidate');
		header('Content-type: application/json');
		set_exception_handler('json_exception_handler');
		$data = json_decode(file_get_contents("php://input"), true);

		$data = $data['data'];
		$data['user'] = $this->user->name();
		$data['user_from'] = $this->user->authDomain();

		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL,base_url().'registry/services/rda/addTag');//post to SOLR
		curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$content = curl_exec($ch);//execute the curl
		curl_close($ch);//close the curl

		echo $content;
	}

	/**
	 * Returns the stat of a record
	 * @param  int $id
	 * @return json
	 */
	function stat($id) {
		header('Cache-Control: no-cache, must-revalidate');
		header('Content-type: application/json');
		set_exception_handler('json_exception_handler');
		$this->load->model('registry_objects', 'ro');
		$ro = $this->ro->getByID($id);
		$stats = $ro->stat();

		echo json_encode($stats);
	}

	/**
	 * Search View
	 * Displaying the search view for the current component
	 * @return HTML 
	 */
	function search() {
		//redirect to the correct URL if q is used in the search query
		if($this->input->get('q')) {
			redirect('search/#!/q='.$this->input->get('q'));
		}
		$this->load->library('blade');
		$this->blade
			->set('lib', array('ui-events', 'angular-ui-map', 'google-map'))
			// ->set('scripts', array('search_app'))
			->set('facets', $this->components['facet'])
			->set('search', true) //to disable the global search
			->render('registry_object/search');
	}

	/**
	 * Main search function
	 * SOLR search
	 * @param bool $no_record
	 * @return json
	 * @internal param string $class class restriction
	 */
	function filter($no_record = false) {
		header('Cache-Control: no-cache, must-revalidate');
		header('Content-type: application/json');
		set_exception_handler('json_exception_handler');

		$data = json_decode(file_get_contents("php://input"), true);

		$filters = isset($data['filters']) ? $data['filters'] : false;

		// experiment with delayed response time
		// sleep(2);

		$this->load->library('solr');

		//restrict to default class
		$default_class = isset($filters['class']) ? $filters['class'] : 'collection';
		if(!is_array($default_class)) {
			$this->solr->setOpt('fq', '+class:'.$default_class);
		}

		$this->solr->setFilters($filters);

		//test
		// $this->solr->setOpt('fq', '+spatial_coverage_centres:*');

		//not recording a hit for the quick search done for advanced search
		if (!$no_record) {
			$event = array(
				'event' => 'portal_search',
				'ip' => $this->input->ip_address(),
				'user_agent' => $this->input->user_agent()
			);
			if($filters){
				$event = array_merge($event, $filters);
			}
			
			ulog_terms($event,'portal');
		}
		

		//returns this set of Facets
		foreach($this->components['facet'] as $facet){
			if ($facet!='temporal' && $facet!='spatial') $this->solr->setFacetOpt('field', $facet);
		}

		//high level subjects facet
		// $subjects = $this->config->item('subjects');
		// foreach ($subjects as $subject) {
		// 	$fq = '(';
		// 	foreach($subject['codes'] as $code) {
		// 		$fq .= 'subject_vocab_uri:("http://purl.org/au-research/vocabulary/anzsrc-for/2008/'.$code.'") ';
		// 	}
		// 	$fq.=')';
		// 	$this->solr->setFacetOpt('query', 
		// 		'{! key='.url_title($subject['display'], '-', true).'}'.$fq
		// 	);
		// }

		//temporal facet
		$this->solr
			->setFacetOpt('field', 'earliest_year')
			->setFacetOpt('field', 'latest_year')
			->setOpt('f.earliest_year.facet.sort', 'count asc')
			->setOpt('f.latest_year.facet.sort', 'count');


		//flags, these are the only fields that will be returned in the search
		$this->solr->setOpt('fl', 'id,title,description,group,slug,spatial_coverage_centres,spatial_coverage_polygons');

		//highlighting
		$this->solr->setOpt('hl', 'true');
		$this->solr->setOpt('hl.fl', 'identifier_value_search, related_party_one_search, related_party_multi_search, group_search, related_info_search, subject_value_resolved_search, description_value, date_to, date_from, citation_info_search');
		$this->solr->setOpt('hl.simple.pre', '&lt;b&gt;');
		$this->solr->setOpt('hl.simple.post', '&lt;/b&gt;');

		//experiment hl attrs
		// $this->solr->setOpt('hl.alternateField', 'description');
		// $this->solr->setOpt('hl.alternateFieldLength', '100');
		// $this->solr->setOpt('hl.fragsize', '300');
		// $this->solr->setOpt('hl.snippets', '100');

		$this->solr->setFacetOpt('mincount','1');
		$this->solr->setFacetOpt('limit','100');
		$this->solr->setFacetOpt('sort','count');
		$result = $this->solr->executeSearch();
		$result->{'url'} = $this->solr->constructFieldString();

		echo json_encode($result);
	}

	/**
	 * List all attribute of a registry object
	 * for development only!
	 * @param $id
	 * @return json
	 */
	function get($id) {
		header('Cache-Control: no-cache, must-revalidate');
		header('Content-type: application/json');
		set_exception_handler('json_exception_handler');
		$this->load->model('registry_objects', 'ro');
		$ro = $this->ro->getByID($id);
		echo json_encode($ro->relationships);
	}

	/**
	 * Construction
	 * Defines the components that will be displayed and search for within the application
	 */
	function __construct() {
		parent::__construct();
		$this->load->model('registry_objects', 'ro');
		$this->components = array(
			'view' => array('descriptions','reuse-list','quality-list','dates-list', 'connectiontree','related-objects-list' ,'spatial-info', 'subjects-list', 'related-metadata', 'identifiers-list'),
			'aside' => array('rights-info','contact-info'),
            'view_headers' => array('title','related-parties'),
            'activity'=>array('descriptions','spatial-info','publications-list', 'subjects-list','identifiers-list','contact-info'),
            'activity_aside'=>('related-objects-list'),
			'facet' => array('spatial','group', 'license_class', 'type', 'temporal', 'access_rights')
		);
	}
}