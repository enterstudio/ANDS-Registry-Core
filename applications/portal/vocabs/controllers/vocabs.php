<?php

/**
 * Vocabs controller
 * This is the primary controller for the vocabulary module
 * This module is meant as a standalone with all assets, views and models self contained
 * within the applications/vocabs directory
 * @version 1.0
 * @author  Minh Duc Nguyen <minh.nguyen@ands.org.au>
 */
class Vocabs extends MX_Controller
{

    /**
     * Index / Home page
     * Displaying the Home Page
     * @return view/html
     * @author  Minh Duc Nguyen <minh.nguyen@ands.org.au>
     */
    public function index()
    {
        // Redirect /vocabs/ to the root. Without this,
        // a page is generated that has vocabulary links
        // that are broken.
        if (uri_string() == 'vocabs') {
            redirect('/');
        }
        // header('Content-Type: text/html; charset=utf-8');
        $event = array(
            'event' => 'pageview',
            'page' => 'home',
            'ip' => $this->input->ip_address(),
            'user_agent' => $this->input->user_agent(),
        );
        vocab_log_terms($event);
        $this->blade
             ->set('search_app', true)
             ->set('title', 'Research Vocabularies Australia')
             ->render('index');
    }

    /**
     * Viewing a vocabulary by slug
     * @return view/html
     * @author  Minh Duc Nguyen <minh.nguyen@ands.org.au>
     */
    public function view()
    {
        //use test records for now
        $slug = $this->input->get('any');
        if ($slug) {
            $record = $this->vocab->getBySlug($slug);
        }
        // Be careful; $record not necessarily set yet.
        if ((!isset($record)) || (!$record)) {
            $record = $this->vocab->getByID($slug);
        }

        if ($record) {
            $vocab = $record->display_array();

            $event = array(
                'event' => 'vocabview',
                'vocab' => $vocab['title'],
                'slug' => $vocab['slug'],
                'id' => $vocab['id'],
            );
            vocab_log_terms($event);

            $vocab['current_version'] = $record->current_version();

            $this->blade
                 ->set('vocab', $vocab)
                 ->set('title', $vocab['title'] . ' - Research Vocabularies Australia')
                 ->render('vocab');
        } else {
            // No longer throw an exception, like this:
            // throw new Exception('No Record found with slug: ' . $slug);
            // But instead, show the soft 404 page.
            $message = '';
            $this->blade
                 ->set('message', $message)
                 ->render('soft_404');
        }
    }

    /**
     * Pre viewing a related entity
     * @return view/html
     * @author  Liz Woods <liz.woods@ands.org.au>
     */
    public function related_preview()
    {

        $related = json_decode($this->input->get('related'), true);
        $v_id = $this->input->get('v_id');
        $sub_type = $this->input->get('sub_type');
        $vocabs = $this->vocab->getAll();

        $others = array();

        foreach ($vocabs as $vocab) {
            $thevocab = $vocab->display_array();
            if ($thevocab['id'] != $v_id) {
                // find all other vocabs that this related entity also published

                if ($related['type'] == 'party') {
                    if (isset($thevocab['related_entity'])) {
                        foreach ($thevocab['related_entity'] as $anotherrelated) {
                            if (is_array($anotherrelated['relationship'])) {
                                foreach ($anotherrelated['relationship'] as $relation) {
                                    if ($relation == 'publishedBy' && $anotherrelated['title'] == $related['title']) {
                                        $thevocab['sub_type'] = 'publisher';
                                        $others[] = $thevocab;
                                    }

                                }
                                $relationships = implode($anotherrelated['relationship'], ',');
                                if ($relationships != 'publishedBy' && $relationships != 'publisherOf' && $anotherrelated['title'] == $related['title']) {
                                    $others[] = $thevocab;
                                }
                            } else {

                                if ($anotherrelated['relationship'] == 'publishedBy' && $anotherrelated['title'] == $related['title']) {
                                    $thevocab['sub_type'] = 'publisher';
                                    $others[] = $thevocab;

                                } elseif ($anotherrelated['title'] == $related['title']) {
                                    $others[] = $thevocab;
                                }

                            }
                        }
                    }
                }

                //if a related entity of type vocab is known to us then provide a link to it
                if ($related['type'] == 'vocabulary') {
                    if ($related['title'] == $thevocab['title']) {
                        $others[] = $thevocab;
                    }
                }
            }
        }
        // print_r($others);

        $others = array_unique($others, true);

        $related['other_vocabs'] = $others;
        $this->blade
             ->set('related', $related)
             ->set('sub_type', $sub_type)
             ->render('related_preview');

    }

    /**
     * Pre viewing a non current version
     * @return view/html
     * @author  Liz Woods <liz.woods@ands.org.au>
     */
    public function version_preview()
    {
        //echo "we are here";
        // echo $this->input->get('version');
        $version = json_decode($this->input->get('version'), true);

        // print_r($version);
        //$v_id = $this->input->get('v_id');

        $this->blade
             ->set('version', $version)
             ->render('version_preview');

    }

    /**
     * Search
     * Displaying the search page
     * @ignore Not used for now. Home page is a search hybrid
     * @version 1.0
     * @return view/html
     * @author  Minh Duc Nguyen <minh.nguyen@ands.org.au>
     */
    public function search()
    {
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
    public function add()
    {
        $event = array(
            'event' => 'pageview',
            'page' => 'add',
        );
        vocab_log_terms($event);
        $this->blade
             ->set('scripts', array('vocabs_cms', 'versionCtrl', 'relatedCtrl'))
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
    public function edit($slug = false)
    {
        if (!$this->user->isLoggedIn()) {
            // throw new Exception('User not logged in');
            redirect(get_vocab_config('auth_url') . 'login#?redirect=' . portal_url('vocabs/edit/' . $slug));
        }
        if (!$slug) {
            throw new Exception('Require a Vocabulary Slug to edit');
        }

        $vocab = $this->vocab->getByID($slug);
        // var_dump($vocab);
        // throw new Exception($vocab->prop['status']);
        if ($vocab->prop['status'] == 'published') {
            // throw new Exception('This is published');
            $draft_vocab = $this->vocab->getDraftBySlug($vocab->prop['slug']);
            if ($draft_vocab) {
                redirect(portal_url('vocabs/edit/') . $draft_vocab->id);
                //throw new Exception($vocab->id);
            }
        }
        //do some checking of vocab here, ACL stuff @todo
        if (!$vocab) {
            throw new Exception('Vocab Slug ' . $slug . ' not found');
        }

        $event = array(
            'event' => 'pageview',
            'page' => 'edit',
            'vocab' => $vocab->title,
            'slug' => $vocab->slug,
            'id' => $vocab->id,
        );
        vocab_log_terms($event);

        $this->blade
             ->set('scripts', array('vocabs_cms', 'versionCtrl', 'relatedCtrl'))
             ->set('vocab', $vocab)
             ->set('title', 'Edit - ' . $vocab->title . ' - Research Vocabularies Australia')
             ->render('cms');
    }

    /**
     * Page Controller
     * For displaying static pages that belongs to the vocabs module
     * @author  Minh Duc Nguyen <minh.nguyen@ands.org.au>
     * @param  $slug supported: [help|about|contribute]
     * @return view
     */
    public function page($slug)
    {
        $event = array(
            'event' => 'pageview',
            'page' => $slug,
        );
        vocab_log_terms($event);
        $title = '';
        switch ($slug) {
            case 'about':$title = 'About';
                break;
            case 'feedback':$title = 'Feedback';
                break;
            case 'contribute':$title = 'Publish a Vocabulary';
                break;
            case 'use':$title = 'Use a Vocabulary';
                break;
            case 'disclaimer':$title = 'Disclaimer';
                break;
            case 'privacy':$title = 'Privacy';
                break;
        }
        $this->blade
             ->set('title', $title . ' - Research Vocabularies Australia')
             ->render($slug);
    }

    /**
     * Primary search functionality
     * data is obtained from angularjs php input POST
     * @author  Minh Duc Nguyen <minh.nguyen@ands.org.au>
     * @return json search result
     */
    public function filter()
    {
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
             ->setFacetOpt('field', 'publisher')
             ->setFacetOpt('field', 'language')
             ->setFacetOpt('field', 'access')
             ->setFacetOpt('field', 'format')
             ->setFacetOpt('field', 'licence')
             ->setFacetOpt('sort', 'index asc')
             ->setFacetOpt('mincount', '1');

        //highlighting
        $this->solr
             ->setOpt('hl', 'true')
             ->setOpt('hl.fl', '*')
             ->setOpt('hl.simple.pre', '&lt;b&gt;')
             ->setOpt('hl.simple.post', '&lt;/b&gt;')
             ->setOpt('hl.snippets', '2');

        //search definition
        $this->solr
             ->setOpt('defType', 'edismax')
             ->setOpt('rows', '250')
             ->setOpt('q.alt', '*:*')
             ->setOpt('qf', 'title_search^1 subject_search^0.5 description_search~10^0.01 fulltext^0.001 concept_search^0.02 publisher^0.5');

        foreach ($filters as $key => $value) {
            switch ($key) {
                case "q":
                    if ($value != '') {
                        $this->solr->setOpt('q', $value);
                    }

                    break;
                case 'subjects':
                case 'publisher':
                case 'access':
                case 'format':
                case 'language':
                case 'licence':
                    if (is_array($value)) {
                        $fq_str = '';
                        foreach ($value as $v) {
                            $fq_str .= ' ' . $key . ':("' . $v . '")';
                        }

                        $this->solr->setOpt('fq', $fq_str);
                    } else {
                        $this->solr->setOpt('fq', '+' . $key . ':("' . $value . '")');
                    }
                    break;
            }
        }

        //CC-1298 If there's no search term, order search result by title asc
        if (!isset($filters['q']) || trim($filters['q']) == '') {
            $this->solr->setOpt('sort', 'title_sort asc');
        }

        // $this->solr->setFilters($filters);
        $result = $this->solr->executeSearch(true);
        $event = array(
            'event' => 'search',
            'filters' => $filters,
        );
        if ($filters) {
            $event = array_merge($event, $filters);
        }

        vocab_log_terms($event);
        echo json_encode($result);
    }

    /**
     * MyVocabs functionality
     * If the user is not logged in, redirects them to the login screen with redirection back to this page
     * @author  Minh Duc Nguyen <minh.nguyen@ands.org.au>
     * @return view
     */
    public function myvocabs()
    {
        if (!$this->user->isLoggedIn()) {
            // throw new Exception('User not logged in');
            redirect(get_vocab_config('auth_url') . 'login#?redirect=' . portal_url('vocabs/myvocabs'));
        }
        $owned = $this->vocab->getOwned();

        $event = array(
            'event' => 'pageview',
            'page' => 'myvocabs',
        );
        vocab_log_terms($event);
        $this->blade
             ->set('owned_vocabs', $owned)
             ->set('title', 'My Vocabs - Research Vocabularies Australia')
             ->render('myvocabs');
    }

    /**
     * Logging the user out via a the auth_url
     * Redirects the user back to the home page after logging out
     * @return redirection to home page
     */
    public function logout()
    {
        redirect(get_vocab_config('auth_url') . 'logout?redirect=' . portal_url());
    }

    /**
     * Services Controller
     * For allowing RESTful API against the Vocabs Portal Database / SOLR
     * @param  string $class [vocabs] context
     * @param  string $id [id] of the context
     * @param  string $method [method] description of the query
     * @return API response / JSON
     * @example services/vocabs/ , services/vocabs/anzsrc-for , services/vocabs/rifcs/versions
     * @author  Minh Duc Nguyen <minh.nguyen@ands.org.au>
     */
    public function services($class = '', $id = '', $method = '', $type = '')
    {

        //header
        header('Cache-Control: no-cache, must-revalidate');
        header('Content-type: application/json');
        set_exception_handler('json_exception_handler');

        if ($class != 'vocabs') {
            throw new Exception('/vocabs required');
        }
        //accesspoint service for all or just one vocab(
        if ($method == 'accessPoints') {
            $result = array();
            if($id == 'all' || $id == ''){
                $vocabs = $this->vocab->getAll();
            } else{
                $vocabs[] = $this->vocab->getByID($id);
            }

            if($vocabs){
                $status = "OK";
                foreach ($vocabs as $v) {

                    $vId = $v->prop['id'];
                    $title = $v->prop['title'];

                    $versions = false;//$v['versions'];
                    $accessPoints = array();

                        foreach($v->versions as $version){
                            $versionIds[] =  $version['id'];
                            $accessPoints = $this->vocab->getAccessPoints($version['id'], $type);
                        }
                    if(!($id == 'all' && $accessPoints == false)){
                        $result[] = array('id' => $vId, 'title' => $title , 'accessPoints'=>$accessPoints);
                    }
                }

            }
            else{
                $status = "No vocabulary found";
            }
            echo json_encode(
                array(
                    'status' => $status,
                    'message' => $result,
                )
            );
            exit();
        }

        $result = '';
        if ($id == 'all' || $id == '') {
            //get All vocabs listed
            //use test data for now
            $vocabs = $this->vocab->getAll();
            $result = array();

            if ($vocabs) {
                foreach ($vocabs as $vocab) {
                    $result[] = $vocab->display_array();
                }
            }

            if ($method == 'related') {
                $result = array();
                $type = $this->input->get('type') ? $this->input->get('type') : false;
                if ($type == 'vocabulary') {
                    $allVocabs = $this->vocab->getAllVocabs();
                    foreach ($allVocabs as $v) {
                        $result[] = array('title' => $v['title'], 'vocab_id' => $v['id'], 'type' => 'vocabulary', 'identifiers' => array('slug' => $v['slug']));
                    }
                } else {
                    foreach ($vocabs as $vocab) {
                        $vocab_array = $vocab->display_array();
                        if (isset($vocab_array['related_entity'])) {
                            foreach ($vocab_array['related_entity'] as $re) {
                                if ($type == 'publisher') {
                                    if ($re['type'] == 'party') {
                                        if (isset($re['relationship']) && is_array($re['relationship'])) {
                                            foreach ($re['relationship'] as $rel) {
                                                if ($rel == 'publishedBy') {
                                                    $re['vocab_id'] = $vocab_array['id'];
                                                    $result[] = $re;
                                                }
                                            }
                                        }
                                    }
                                    if ($re['type'] == 'party' && isset($re['relationship']) && $re['relationship'] == 'publishedBy') {
                                        $re['vocab_id'] = $vocab_array['id'];
                                        $result[] = $re;
                                    }
                                } elseif ($type) {
                                    if ($re['type'] == $type) {
                                        $re['vocab_id'] = $vocab_array['id'];
                                        $result[] = $re;
                                    }
                                } else {
                                    $result[] = $re;
                                }
                            }
                        }
                    }
                }
            } else if ($method == 'user') {
                $result = array();
                $result['affiliations'] = array_values(array_unique($this->user->affiliations()));
                $result['role_id'] = $this->user->localIdentifier();

            } else if ($method == 'index') {
                $result = array();

                //clear all vocabs before adding
                $this->load->library('solr');
                $vocab_config = get_config_item('vocab_config');
                if (!$vocab_config['solr_url']) {
                    throw new Exception('Indexer URL for Vocabulary module is not configured correctly');
                }

                $this->solr->setUrl($vocab_config['solr_url']);
                $this->solr->deleteByQueryCondition('*:*');

                //index each vocab one by one
                foreach ($vocabs as $vocab) {
                    $result[] = $vocab->indexable_json();
                    $this->index_vocab($vocab);
                }
            }

            // POST request, for adding new item
            $angulardata = json_decode(file_get_contents("php://input"), true);
            $data = isset($angulardata['data']) ? $angulardata['data'] : false;
            if ($data) {
                //deal with POST request, adding new vocabulary
                $vocab = $this->vocab->addNew($data);
                if (!$vocab) {
                    throw new Exception('Error Adding New Vocabulary');
                }

                if ($vocab) {
                    $result = $vocab;
                    //index just added one
                    $this->index_vocab($vocab);

                    //log
                    $event = array(
                        'event' => 'add',
                        'vocab' => $vocab->title,
                    );
                    vocab_log_terms($event);
                }

            }

        } else if ($id != '') {

            $vocab = $this->vocab->getBySlug($id);
            if (!$vocab) {
                $vocab = $this->vocab->getByID($id);
            }

            if (!$vocab) {
                throw new Exception('Vocab ID ' . $id . ' not found');
            }

            $result = $vocab->display_array();

            //POST Request, for saving this vocab
            $angulardata = json_decode(file_get_contents("php://input"), true);
            $data = isset($angulardata['data']) ? $angulardata['data'] : false;

            if ($data) {
                //if id refers to a draft look up to see if there is a published for this draft
                if ($vocab->prop['status'] == 'draft' && $data['status'] == 'published') {
                    $vocab = $this->vocab->getBySlug($vocab->prop['slug']);
                }

                $result = $vocab->save($data);

                if (null == $this->user->affiliations() && $data['status'] == 'published') {
                    $data['status'] = 'draft';
                    $vocab->prop['status'] = 'draft';
                    $vocab->save($data);
                    $to_email = $this->config->item('site_admin_email');
                    $content = 'Vocabulary' . $data['title'] . ' is published by a user with no affiliations' . NL;
                    $email = $this->load->library('email');
                    $email->to($to_email);
                    $email->from($to_email);
                    $email->subject('Vocabulary' . $data['title'] . ' published without an organisational role');
                    $email->message($content);
                    $email->send();
                    $vocab->log('An email of this action has been sent to' . $this->config->item('site_admin_email'));
                }

                //throw new Exception($data['status']);

                //result should be an object
                //result.status = 'OK'
                //result.message = array()

                if (!$result) {
                    throw new Exception('Error while saving vocabulary');
                }

                if ($result && $vocab->prop['status'] == 'published') {
                    if ($this->index_vocab($vocab)) {
                        $vocab->log('Indexing Success');
                    }
                }

                if ($result) {
                    $result = $vocab;
                }

                $event = array(
                    'event' => 'edit',
                    'vocab' => $vocab->title,
                );
                vocab_log_terms($event);

            }
            if ($method == 'index') {
                $result = $vocab->indexable_json();
                $this->index_vocab($vocab);
            } elseif ($method == 'versions') {
                $result = $result['versions'];
            } else if ($method == 'tree') {
                $result = $vocab->display_tree();
            } else if ($method == 'tree-raw') {
                $result = $vocab->display_tree(true);
            }
        }

        echo json_encode(
            array(
                'status' => 'OK',
                'message' => $result,
            )
        );
    }

    /**
     * Indexing a single vocab helper method
     * @access private
     * @param  _vocabulary $vocab
     * @return boolean
     */
    private function index_vocab($vocab)
    {

        //load necessary stuff
        $this->load->library('solr');
        $vocab_config = get_config_item('vocab_config');
        if (!$vocab_config['solr_url']) {
            throw new Exception('Indexer URL for Vocabulary module is not configured correctly');
        }

        $this->solr->setUrl($vocab_config['solr_url']);

        //only index published records
        if ($vocab->status == 'published') {
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

    }

    /**
     * Delete a vocabulary
     * @todo Need ACL on this feature
     * @param  id $id POST
     * @return boolean
     */
    public function delete()
    {
        if ($this->user->isLoggedIn() && $this->input->post('id')) {
            $this->vocab->delete($this->input->post('id'));
        }
    }

    /**
     * ToolKit Service provider
     * To interact with 3rd party application in order to get vocabularies metadata
     * Requires a ?GET request
     * @example vocabs/toolkit/?request=listPooLPartyProjects returns all the PoolParty project available
     * @return view
     */
    public function toolkit()
    {
        //header
        header('Cache-Control: no-cache, must-revalidate');
        header('Content-type: application/json');
        set_exception_handler('json_exception_handler');

        //if (!get_config_item('vocab_toolkit_url')) throw new Exception('Vocab ToolKit URL not configured correctly');
        $request = $this->input->get('request');
        if (!$request) {
            throw new Exception('Request Not Found');
        }

        $url = get_vocab_config('toolkit_url');
        if (!$url) {
            throw new Exception('Vocab Toolkit URL not configured correctly');
        }

        switch ($request) {
            case 'listPoolPartyProjects':
                $sample = @file_get_contents($url . 'getInfo/PoolPartyProjects');
                echo $sample;
                break;
            case 'getMetadata':
                $ppid = $this->input->get('ppid') ? $this->input->get('ppid') : false;
                if (!$ppid) {
                    throw new Exception('Pool Party ID required to get metadata');
                }

                $metadata = @file_get_contents($url . 'getMetadata/poolParty/' . $ppid);
                echo $metadata;
                break;
            default:
                throw new Exception('Request Not Recognised');
        }
    }

    /**
     * Upload API entry point for uploading a file
     * @author Minh Duc Nguyen <minh.nguyen@ands.org.au>
     * @return json response
     */
    public function upload()
    {
        header('Cache-Control: no-cache, must-revalidate');
        header('Content-type: application/json');
        set_exception_handler('json_exception_handler');

        $upload_path = get_vocab_config('upload_path');
        if (!is_dir($upload_path)) {
            if (!mkdir($upload_path)) {
                throw new Exception('Upload path are not created correctly. Contact server administrator');
            }

        }

        $config['upload_path'] = $upload_path;
        $config['allowed_types'] = 'xml|rdf|pdf|nt|json|trig|trix|n3|csv|tsv|xls|xlsx|ods|zip|txt|ttl';
        $config['overwrite'] = true;
        $config['max_size'] = '50000';
        $this->load->library('upload', $config);
        $this->upload->initialize($config);

        if (!$this->upload->do_upload('file')) {
            $upload_file_exceeds_limit = "The uploaded file exceeds the maximum allowed size in your PHP configuration file.";
            $upload_invalid_filesize = "The file you are attempting to upload is larger than the permitted size.";
            $upload_invalid_filetype = "The filetype you are attempting to upload is not allowed.";
            $theError = $this->upload->display_errors();
            if (strrpos($theError, $upload_file_exceeds_limit) > 0 || strrpos($theError, $upload_invalid_filesize) > 0) {
                $theError = "Maximum file size exceeded. Please select a file smaller than 50MB.";
            } elseif (strrpos($theError, $upload_invalid_filetype) > 0) {
                $theError = "Unsupported file format. Please select a png, jpg or gif.";
            }
            echo json_encode(
                array(
                    'status' => 'ERROR',
                    'message' => $theError,
                )
            );
        } else {
            $data = $this->upload->data();
            $name = $data['orig_name'];
            echo json_encode(
                array(
                    'status' => 'OK',
                    'message' => 'File uploaded successfully!',
                    'data' => $this->upload->data(),
                    'url' => $name,
                )
            );
        }
    }

    /**
     * Does haystack start with needle?
     * Taken from http://stackoverflow.com/questions/834303/startswith-and-endswith-functions-in-php
     */
    public function startsWith($haystack, $needle)
    {
        // Search backwards starting from haystack length
        // characters from the end.
        return $needle === "" ||
        strrpos($haystack, $needle, -strlen($haystack)) !== false;
    }

    /**
     * Automated test tools
     * @version 1.0
     * @internal Used as internal testing before rolling out automated test cases
     * @author  Minh Duc Nguyen <minh.nguyen@ands.org.au>
     */
    public function test()
    {
        //test getting the documents
        // echo json_encode($test_records);

        //test indexing the documents
        // $solr_doc = array();
        // foreach ($test_records as $record) {
        //     $solr_doc[] = $record->indexable_json();
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
    public function __construct()
    {
        parent::__construct();
        $this->load->model('vocabularies', 'vocab');
        $this->load->library('blade');
    }
}
