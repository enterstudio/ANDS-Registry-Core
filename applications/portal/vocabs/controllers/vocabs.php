<?php
/**
 * Vocabs controller
 * This is the primary controller for the vocabulary module
 * This module is meant as a standalone with all assets, views and models self contained
 * within the applications/vocabs directory
 * @version 1.0
 * @author  Minh Duc Nguyen <minh.nguyen@ands.org.au>
 */
class Vocabs extends MX_Controller {

	/**
	 * Index / Home page
	 * Displaying the Home Page
	 * @return view/html
	 * @author  Minh Duc Nguyen <minh.nguyen@ands.org.au>
	 */
	function index(){
		// header('Content-Type: text/html; charset=utf-8');
		$this->blade->render('index');
	}

	/**
	 * Viewing a vocabulary by slug
	 * @return view/html
	 * @author  Minh Duc Nguyen <minh.nguyen@ands.org.au>
	 */
	public function view() {
		//use test records for now
		$slug = $this->input->get('any');
		if ($slug) {
			$record = $this->vocab->getBySlug($slug);
		}

		//For Development Only
		if (!$record) {
			$test_records = $this->vocab->test_vocabs();
			$record = $test_records[$slug] ? $test_records[$slug] : false;
		}

		if ($record) {
            $vocab = $record->display_array();
            $vocab['current_version'] = $record->current_version();

			$this->blade
				->set('vocab', $vocab)
				->render('vocab');
		} else {
			throw new Exception('No Record found with slug: '.$slug);
		}
	}

    /**
     * Pre viewing a related entity
     * @return view/html
     * @author  Liz Woods <liz.woods@ands.org.au>
     */
    public function related_preview() {

        $related = json_decode($this->input->get('related'),true);
        $v_id = $this->input->get('v_id');
        $vocabs = $this->vocab->getAll();

        $others = array();

        foreach ($vocabs as $vocab) {
            $thevocab=$vocab->display_array();
            if($thevocab['id']!=$v_id){
                // find all other vocabs that this related entity also published
                if($related['type']=='publisher'){
                    if(isset($thevocab['related_entity'])){
                        foreach($thevocab['related_entity'] as $anotherrelated){
                            if($anotherrelated['type']=='publisher'&& $anotherrelated['id']==$related['id']){
                                $others[] = $thevocab;
                            }
                        }
                    }
                }
                // find all other vocabs that this related entity also contributed to
                if($related['type']=='contributor'){
                    if(isset($thevocab['related_entity'])){
                        foreach($thevocab['related_entity'] as $anotherrelated){
                            if($anotherrelated['type']=='contributor'&& $anotherrelated['id']==$related['id']){
                                $others[] = $thevocab;
                            }
                        }
                    }
                }
                //if a related entity of type vocab is known to us then provide a link to it
                if($related['type']=='vocab'){
                    if($related['id']==$thevocab['id']){
                        $others[]=$thevocab;
                    }
                }
            }
        }



        $related['other_vocabs'] = $others;
            $this->blade
                ->set('related', $related)
                ->render('related_preview');

    }

	/**
	 * Search
	 * Displaying the search page
	 * @ignore Not used for now. Home page is a search hybrid
	 * @version 1.0
	 * @return view/html
	 * @author  Minh Duc Nguyen <minh.nguyen@ands.org.au>
	 */
	public function search() {
		$this->blade->render('search');
	}

	/**
	 * Adding a vocabulary
	 * Displaying a view for adding a vocabulary
	 * Using the same CMS as edit
	 * @todo  ACL
	 * @return view
	 * @author  Minh Duc Nguyen <minh.nguyen@ands.org.au>
	 */	
	public function add() {
		$this->blade
			->set('scripts', array('vocabs_cms'))
			->set('vocab', false)
			->render('cms');
	}

	/**
	 * Edit a vocabulary
	 * Displaying a view for editing a vocabulary
	 * Using the same CMS as add but directed towards a vocabulary
	 * @todo ACL
	 * @param  string $slug slug of the vocabulary, unique for a vocabulary
	 * @return view
	 * @author  Minh Duc Nguyen <minh.nguyen@ands.org.au>
	 */
	public function edit($slug=false) {
		if (!$this->user->isLoggedIn()) throw new Exception('User not logged in');
		if (!$slug) throw new Exception('Require a Vocabulary Slug to edit');
		$vocab = $this->vocab->getBySlug($slug);
        if($vocab->prop['status']=='published'||$vocab->prop['status']=='deprecated') {
            $draft_vocab = $this->vocab->getBySlug($slug.'DRAFT');
            if($draft_vocab) {
                $vocab = $draft_vocab;
            }
        }
		//do some checking of vocab here, ACL stuff @todo
		if (!$vocab) throw new Exception('Vocab Slug '.$slug. ' not found');

		$this->blade
			->set('scripts', array('vocabs_cms'))
			->set('vocab', $vocab)
			->render('cms');
	}

	/**
	 * Page Controller
	 * For displaying static pages that belongs to the vocabs module
	 * @author  Minh Duc Nguyen <minh.nguyen@ands.org.au>
	 * @param  $slug supported: [help|about|contribute]
	 * @return view
	 */
	public function page($slug) {
		$this->blade->render($slug);
	}

	/**
	 * Primary search functionality
	 * data is obtained from angularjs php input POST
	 * @author  Minh Duc Nguyen <minh.nguyen@ands.org.au>
	 * @return json search result
	 */
	public function filter() {
		//header
		header('Cache-Control: no-cache, must-revalidate');
		header('Content-type: application/json');
		set_exception_handler('json_exception_handler');
		$data = json_decode(file_get_contents("php://input"), true);
		$filters = isset($data['filters']) ? $data['filters'] : false;
		$this->load->library('solr');
		$this->solr->setUrl('http://localhost:8983/solr/vocabs/');

		//facets
		$this->solr
			->setFacetOpt('field', 'subjects')
			->setFacetOpt('field', 'language')
			->setFacetOpt('field', 'licence')
			->setFacetOpt('mincount', '1');

		//highlighting
		$this->solr
			->setOpt('hl', 'true')
			->setOpt('hl.fl', 'description, subject_search, title, concept, language')
			->setOpt('hl.simple.pre', '&lt;b&gt;')
			->setOpt('hl.simple.post', '&lt;/b&gt;')
			->setOpt('hl.snippets', '2');

		//search definition
		$this->solr
			->setOpt('defType', 'edismax')
			->setOpt('q.alt', '*:*')
			->setOpt('qf', 'title_search^1 subject_search^0.5 description_search~10^0.01 fulltext^0.001 concept^0.02');;

		foreach ($filters as $key=>$value) {
			switch ($key) {
				case "q" :
					if ($value!='') $this->solr->setOpt('q', $value);
					break;
				case 'subjects':
				case 'language':
				case 'licence':
					if(is_array($value)){
						$fq_str = '';
						foreach($value as $v) $fq_str .= ' '.$key.':("'.$v.'")';
						$this->solr->setOpt('fq', $fq_str);
					}else{
						$this->solr->setOpt('fq', '+'.$key.':("'.$value.'")');
					}
					break;
			}
		}

		// $this->solr->setFilters($filters);
		$result = $this->solr->executeSearch(true);
		echo json_encode($result);
	}

	/**
	 * MyVocabs functionality
	 * If the user is not logged in, redirects them to the login screen with redirection back to this page
	 * @author  Minh Duc Nguyen <minh.nguyen@ands.org.au>
	 * @return view
	 */
	public function myvocabs() {
		if (!$this->user->isLoggedIn()) {
			// throw new Exception('User not logged in');
			redirect(get_vocab_config('auth_url').'login#?redirect='.portal_url('vocabs/myvocabs'));
		}
		$owned = $this->vocab->getOwned();
		$this->blade
			->set('owned_vocabs', $owned)
			->render('myvocabs');
	}

	/**
	 * Logging the user out via a the auth_url
	 * Redirects the user back to the home page after logging out
	 * @return redirection to home page
	 */
	public function logout() {
		redirect(get_vocab_config('auth_url').'logout?redirect='.portal_url());
	}

	/**
	 * Services Controller
	 * For allowing RESTful API against the Vocabs Portal Database / SOLR
	 * @param  string $class  [vocabs] context
	 * @param  string $id     [id] of the context
	 * @param  string $method [method] description of the query
	 * @return API response / JSON
	 * @example services/vocabs/ , services/vocabs/anzsrc-for , services/vocabs/rifcs/versions
	 * @author  Minh Duc Nguyen <minh.nguyen@ands.org.au>
	 */
	public function services($class='', $id='', $method='') {

		//header
		header('Cache-Control: no-cache, must-revalidate');
		header('Content-type: application/json');
		set_exception_handler('json_exception_handler');

		if ($class != 'vocabs') throw new Exception('/vocabs required');

		$result = '';
		if ($id=='all' || $id=='') {
			//get All vocabs listed
			//use test data for now
			$vocabs = $this->vocab->getAll();
			$result = array();

			foreach ($vocabs as $vocab) {
				$result[] = $vocab->display_array();
			}

			if ($method=='related') {
				$result = array();
				$type = $this->input->get('type') ? $this->input->get('type') : false;
				foreach($vocabs as $vocab) {
					$vocab_array = $vocab->display_array();
					foreach($vocab_array['related_entity'] as $re) {
						if ($type) {
							if ($re['type']==$type) {
								$re['vocab_id'] = $vocab_array['id'];
								$result[] = $re;
							}
						} else {
							$result[] = $re;
						}
					}
				}
			} else if($method=='user') {
                $result = array_values(array_unique($this->user->affiliations()));
            }

			// POST request, for adding new item
			$angulardata = json_decode(file_get_contents("php://input"), true);
			$data = isset($angulardata['data']) ? $angulardata['data'] : false;
			if ($data) {
				//deal with POST request, adding new vocabulary
				$vocab = $this->vocab->addNew($data);
				if (!$vocab) throw new Exception('Error Adding New Vocabulary');
				if ($vocab) {
					$result = $vocab;
					//index just added one
					$this->index_vocab($vocab);
				}
			}

		} else if($id!='') {

			$vocab = $this->vocab->getBySlug($id);
			if (!$vocab) $vocab = $this->vocab->getByID($id);

			if (!$vocab) throw new Exception('Vocab ID '. $id. ' not found');

			$result = $vocab->display_array();

			//POST Request, for saving this vocab
			$angulardata = json_decode(file_get_contents("php://input"), true);
			$data = isset($angulardata['data']) ? $angulardata['data'] : false;
			if ($data) {
				$result = $vocab->save($data);
				if (!$result) throw new Exception('Error Saving Vocabulary');
				if ($result) {
					$result = 'Success in saving vocabulary';
					//index saved one
                    if($vocab->prop['status']=='published'){
                        $this->index_vocab($vocab);
                        if ($this->index_vocab($vocab)) {
                            $result .= '. Success in indexing vocabulary';
                        }
                    }
				}
			}

			if ($method=='index') {
				$result = $vocab->indexable_json();
				$this->index_vocab($vocab);
			} elseif($method=='versions') {
				$result = $result['versions'];
            } else if ($method=='tree') {
            	$result = $vocab->display_tree();
            } else if ($method=='tree-raw') {
            	$result = $vocab->display_tree(true);
            }
		}

		echo json_encode(
			array(
				'status' => 'OK',
				'message' => $result
			)
		);
	}

	/**
	 * Indexing a single vocab helper method
	 * @access private
	 * @param  _vocabulary $vocab
	 * @return boolean
	 */
	private function index_vocab($vocab) {
		
		//load necessary stuff
		$this->load->library('solr');
		$vocab_config = get_config_item('vocab_config');
		if (!$vocab_config['solr_url']) throw new Exception('Indexer URL for Vocabulary module is not configured correctly');
		$this->solr->setUrl($vocab_config['solr_url']);


		//remove index
		$this->solr->deleteByID($vocab->id);
		
		//index
		$index = $vocab->indexable_json();
		$solr_doc = array();
		$solr_doc[] = $index;
		$solr_doc = json_encode($solr_doc);
		$add_result = json_decode($this->solr->add_json_commit($solr_doc), true);

		if ($add_result['responseHeader']['status'] === 0) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * ToolKit Service provider
	 * To interact with 3rd party application in order to get vocabularies metadata
	 * Requires a ?GET request 
	 * @example vocabs/toolkit/?request=listPooLPartyProjects returns all the PoolParty project available
	 * @return view
	 */
	public function toolkit() {
		//header
		header('Cache-Control: no-cache, must-revalidate');
		header('Content-type: application/json');
		set_exception_handler('json_exception_handler');

		//if (!get_config_item('vocab_toolkit_url')) throw new Exception('Vocab ToolKit URL not configured correctly');
		$request = $this->input->get('request');
		if (!$request) throw new Exception('Request Not Found');

		$url = get_vocab_config('toolkit_url');
		if (!$url) throw new Exception('Vocab Toolkit URL not configured correctly');

		switch ($request) {
			case 'listPoolPartyProjects':
				$sample = @file_get_contents($url.'getInfo/PoolPartyProjects');
				echo $sample;
				break;
			case 'getMetadata':
				$ppid = $this->input->get('ppid') ? $this->input->get('ppid') : false;
				if (!$ppid) throw new Exception('Pool Party ID required to get metadata');
				$metadata = @file_get_contents($url.'getMetadata/poolParty/'.$ppid);
				echo $metadata;
				break;
			default : throw new Exception('Request Not Recognised');
		}
	}

	/**
	 * Automated test tools
	 * @version 1.0
	 * @internal Used as internal testing before rolling out automated test cases
	 * @author  Minh Duc Nguyen <minh.nguyen@ands.org.au>
	 */
	function test() {
		//test getting the documents
		// echo json_encode($test_records);

		//test indexing the documents
		// $solr_doc = array();
		// foreach ($test_records as $record) {
		// 	$solr_doc[] = $record->indexable_json();
		// }
		// $this->load->library('solr');
		// $this->solr->setUrl('http://localhost:8983/solr/vocabs/');
		// $solr_doc = json_encode($solr_doc);
		// $add_result = $this->solr->add_json($solr_doc);
		// $commit_result = $this->solr->commit();

		// // echo json_encode($add_result);
		
		// $vocab = $this->vocab->getByID(13);
		// echo json_encode($vocab);
		$records = $this->vocab->getAll();
		
		//Index all vocabulary
		$solr_doc = array();
		foreach ($records as $record) {
			$solr_doc[] = $record->indexable_json();
		}
		$this->load->library('solr');
		$this->solr->setUrl('http://localhost:8983/solr/vocabs/');
		$solr_doc = json_encode($solr_doc);
		$add_result = $this->solr->add_json($solr_doc);

		$commit_result = $this->solr->commit();
		var_dump($add_result);
		var_dump($commit_result);
		// echo $data;
	}


	/**
	 * Constructor Method
	 * Autload blade by default
	 */
	public function __construct() {
		parent::__construct();
		$this->load->model('vocabularies', 'vocab');
		$this->load->library('blade');
	}
}