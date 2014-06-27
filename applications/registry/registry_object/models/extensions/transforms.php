<?php

class Transforms_Extension extends ExtensionBase
{

	function __construct($ro_pointer)
	{
		parent::__construct($ro_pointer);
	}		


	function transformForSOLR($add_tags = true)
	{
		try{
			$xslt_processor = Transforms::get_extrif_to_solr_transformer();
			$xslt_processor->setParameter('','recordCreatedDate', gmdate('Y-m-d\TH:i:s\Z', $this->ro->created));
			$xslt_processor->setParameter('','recordUpdatedDate', gmdate('Y-m-d\TH:i:s\Z', ($this->ro->updated ? $this->ro->updated : $this->ro->created)));
			
			if ($this->ro->search_boost)
			{
				$xslt_processor->setParameter('','boost', $this->ro->search_boost);
			}

			$dom = new DOMDocument();
			$dom->loadXML(htmlspecialchars_decode(htmlspecialchars($this->ro->getExtRif())), LIBXML_NOENT);
			if ($add_tags)
			{
				return "<add>" . $xslt_processor->transformToXML($dom) . "</add>";
			}
			else
			{
				return  $xslt_processor->transformToXML($dom);
			}
		}catch (Exception $e)
		{
			echo "UNABLE TO TRANSFORM" . BR;	
			echo "<pre>" . nl2br($e->getMessage()) . "</pre>" . BR;
		}
	}


	function transformForQA($xml, $data_source_key = null)
	{
		try{
			$xslt_processor = Transforms::get_qa_transformer();
			$dom = new DOMDocument();
			$dom->loadXML(htmlspecialchars_decode($xml), LIBXML_NOENT);
			$xslt_processor->setParameter('','dataSource', $data_source_key ?: $this->ro->data_source_key );
			$xslt_processor->setParameter('','relatedObjectClassesStr',$this->ro->getRelatedClassesString());
			return $xslt_processor->transformToXML($dom);
		}catch (Exception $e)
		{
			echo "UNABLE TO TRANSFORM" . BR;	
			echo "<pre>" . nl2br($e->getMessage()) . "</pre>" . BR;
		}
	}
	
	function transformForHtml($revision='', $data_source_key = null)
	{
		try{
			$xslt_processor = Transforms::get_extrif_to_html_transformer();
			$dom = new DOMDocument();
			$dataSource = $this->ro->data_source_key;
			if($revision=='') {
				$dom->loadXML(wrapRegistryObjects($this->ro->getRif()));
			}else $dom->loadXML(wrapRegistryObjects($this->ro->getRif($revision)));
			$xslt_processor->setParameter('','dataSource', $data_source_key ?: $this->ro->data_source_key );
			return html_entity_decode($xslt_processor->transformToXML($dom));
		}catch (Exception $e)
		{
			echo "UNABLE TO TRANSFORM" . BR;	
			echo "<pre>" . nl2br($e->getMessage()) . "</pre>" . BR;
		}
	}
	
	
	
	function transformToDC()
	{
		try{
			$xslt_processor = Transforms::get_extrif_to_dc_transformer();
			$dom = new DOMDocument();
			$dom->loadXML(htmlspecialchars_decode($this->ro->getExtRif()), LIBXML_NOENT);
			$xslt_processor->setParameter('','base_url',portal_url());
			return trim($xslt_processor->transformToXML($dom));
		}catch (Exception $e)
		{
			echo "UNABLE TO TRANSFORM" . BR;	
			echo "<pre>" . nl2br($e->getMessage()) . "</pre>" . BR;
		}
	}


	function transformToDCI()
	{
		$this->_CI->load->helper('normalisation');
        $ds = $this->_CI->ds->getByID($this->ro->data_source_id);
        if($ds->export_dci == 1 || $ds->export_dci == 't')
        {
            try{
			$xslt_processor = Transforms::get_extrif_to_dci_transformer();
			$dom = new DOMDocument();
			$dom->loadXML(htmlspecialchars_decode($this->ro->getExtRif()), LIBXML_NOENT);
			$xslt_processor->setParameter('','dateHarvested', date("Y", $this->ro->created));
			$xslt_processor->setParameter('','dateRequested', date("Y-m-d"));
			$xml_output = $xslt_processor->transformToXML($dom);
            if($xml_output == '') return "";
			$dom = new DOMDocument;
			$dom->loadXML($xml_output);
			$sxml = simplexml_import_dom($dom);

			// Post-process the AuthorRole element
            /*
             *                        <xsl:choose>
                            <!-- see if we have citationMetadatata -->
                            <xsl:when test="ro:collection/ro:citationInfo/ro:citationMetadata/ro:contributor">
                                <xsl:apply-templates select="ro:collection/ro:citationInfo/ro:citationMetadata/ro:contributor"/>
                            </xsl:when>
                            <!-- use RelatedObjects then -->
                <xsl:when test="
               ro:collection/ro:relatedObject[ro:relation/@type = 'hasPrincipalInvestigator']
            or ro:collection/ro:relatedObject[ro:relation/@type = 'principalInvestigator']
            or ro:collection/ro:relatedObject[ro:relation/@type =  'author']
            or ro:collection/ro:relatedObject[ro:relation/@type = 'coInvestigator']
            or ro:collection/ro:relatedObject[ro:relation/@type =  'isOwnedBy']
            or ro:collection/ro:relatedObject[ro:relation/@type =  'hasCollector']">
                    <xsl:apply-templates select="ro:collection/ro:relatedObject[ro:relation/@type =  'hasPrincipalInvestigator'] | ro:collection/ro:relatedObject[ro:relation/@type = 'principalInvestigator'] | ro:collection/ro:relatedObject[ro:relation/@type = 'author'] | ro:collection/ro:relatedObject[ro:relation/@type = 'coInvestigator'] | ro:collection/ro:relatedObject[ro:relation/@type = 'isOwnedBy'] | ro:collection/ro:relatedObject[ro:relation/@type =  'hasCollector']"/>
                </xsl:when>
                            <!-- otherwise anonymus -->
                            <xsl:otherwise>
                                <Author seq="1">
                                    <AuthorName>Anonymous</AuthorName>
                                </Author>
                            </xsl:otherwise>
                        </xsl:choose>
            */
            //check if we've got any Author from the CitationMetadata
            $TimePeriods = $sxml->xpath('//TimeperiodList/TimePeriod');
            if(sizeof($TimePeriods) > 2) // need to find earliest and latest
            {
                $startYear = 99999;
                $endYear = 0;
                foreach ($TimePeriods as $tYear)
                {
                    if($tYear['TimeSpan'] == 'Start' && intval($tYear) < $startYear)
                        $startYear = intval($tYear);
                    if($tYear['TimeSpan'] == 'End' && intval($tYear) > $endYear)
                        $endYear = intval($tYear);
                    unset($tYear[0][0]);
                }
                $TimePeriodList = $sxml->xpath('//TimeperiodList')[0];
                if($startYear != 99999){
                    $eStart = $TimePeriodList->addChild('TimePeriod', $startYear); // uses the first father tag
                    $eStart['TimeSpan']= 'Start';
                }
                if($endYear != 0){
                    $eEnd = $TimePeriodList->addChild('TimePeriod', $endYear); // uses the first father tag
                    $eEnd['TimeSpan']= 'End';
                }


            }
            $eAuthorList = $sxml->xpath('//AuthorList')[0];

            if(sizeof($sxml->xpath('//AuthorList/Author')) == 0){
                $relationshipTypeArray = ['hasPrincipalInvestigator','principalInvestigator','author','coInvestigator','isOwnedBy','hasCollector'];
                $classArray = ['party'];
                $authorList = $this->ro->getRelatedObjectsByClassAndRelationshipType($classArray ,$relationshipTypeArray);

                $seq = 0;
                if(sizeof($authorList) > 0)
                {
                    foreach ($authorList as $author)
                    {
                        // Include identifiers and addresses for this author (if they exist in the registry)
                        $researcher_object = $this->_CI->ro->getPublishedByKey($author['key']);

                        if ($researcher_object && $researcher_sxml = $researcher_object->getSimpleXML(NULL, true))
                        {
                            try
                            {
                                $eAuthor = $eAuthorList->addChild('Author');
                                $eAuthor['seq'] = $seq++;

                                // Change the value of the relation to be human-readable
                                $eAuthor["AuthorRole"] =  format_relationship("collection",(string)$author["relation_type"]);
                                // Do we have an address? (using the normalisation_helper.php)
                                $authorNames = $researcher_sxml->xpath('//extRif:displayTitle');
                                foreach($authorNames as $authorName)
                                    $eAuthor->addChild('AuthorName', (string)$authorName);
                                $researcher_addresses = $researcher_sxml->xpath('//ro:location/ro:address');
                                $address_string = "";
                                if (is_array($researcher_addresses))
                                {
                                    foreach($researcher_addresses AS $_addr)
                                    {
                                        if ($_addr->physical)
                                        {
                                            $address_string .= normalisePhysicalAddress($_addr->physical). " ";
                                        }
                                        else if ($_addr->electronic)
                                        {
                                            $address_string .= (string) $_addr->electronic->value. " ";
                                        }
                                    }
                                }
                                if ($address_string)
                                {
                                    $eAuthor->AuthorAddress->AddressString = $address_string;
                                }
                            }
                            catch (Exception $e)
                            {
                                // ignore sloppy coding errors...SimpleXML is awful
                            }


                            // Handle the researcher IDs (using the normalisation_helper.php)
                            $researcher_ids = $researcher_sxml->xpath('//ro:party/ro:identifier');
                            //var_dump($researcher_ids);
                            if (is_array($researcher_ids))
                            {
                                $idArray = Array();
                                foreach($researcher_ids as $researcher_id){
                                    if((string)$researcher_id != '' && !in_array((string)$researcher_id, $idArray))
                                    {
                                        if(strtoupper($researcher_id['type']) == 'DOI')
                                        {
                                            $doiVal = $this->substringAfter((string)$researcher_id, 'doi.org/');
                                            $author = $eAuthor->addChild('AuthorID', $doiVal); // uses the first father tag
                                            $author['type']= $researcher_id['type'];
                                        }
                                        else if(strtoupper($researcher_id['type']) == 'AU-ANL:PEAU')
                                        {
                                            $doiVal = $this->substringAfter((string)$researcher_id, 'nla.gov.au/');
                                            $author = $eAuthor->addChild('AuthorID', $doiVal); // uses the first father tag
                                            $author['type']= $researcher_id['type'];
                                        }
                                        else if(substr('nla.gov.au/', (string)$researcher_id) !== false)
                                        {
                                            $doiVal = $this->substringAfter((string)$researcher_id, 'nla.gov.au/');
                                            $author = $eAuthor->addChild('AuthorID', $doiVal); // uses the first father tag
                                            $author['type']= $researcher_id['type'];
                                        }
                                        else
                                        {
                                            $author = $eAuthor->addChild('AuthorID', (string)$researcher_id); // uses the first father tag
                                            $author['type']= $researcher_id['type'];
                                        }
                                        $idArray[] = (string)$researcher_id;
                                        $idArray[] = $doiVal;

                                    }
                                }
                            }

                        }

                    }



                }
            }
            if(sizeof($sxml->xpath('//AuthorList/Author')) == 0)// if still no Author found call it Anonymous
            {
                $eAuthor = $eAuthorList->addChild('Author');
                $eAuthor['seq'] = '1';
                $eAuthor->addChild('AuthorName44', 'Anonymous');
            }


                // Post-process the Grant and Funding info elements
                $fundingInfoList = $sxml->xpath('//FundingInfoList[@postproc="1"]');

                foreach($fundingInfoList as $fundingInfo)
                    unset($fundingInfo["postproc"]);
                $grants = $sxml->xpath('//ParsedFunding');
                foreach($grants as $grant){
                    $grantNumber = (string) $grant->GrantNumber;
                    // Include identifiers and addresses for this author (if they exist in the registry)
                    $grant_object = $this->_CI->ro->getPublishedByKey($grantNumber);
                    if ($grant_object && $grant_object->status == PUBLISHED)
                    {
                        $grant_sxml = $grant_object->getSimpleXML(NULL, true);
                        // Handle the researcher IDs (using the normalisation_helper.php)
                        $grant_id = $grant_sxml->xpath("//ro:identifier[@type='arc'] | //ro:identifier[@type='nhmrc'] | //ro:identifier[@type='purl']");
                        $related_party = $grant_object->getRelatedObjectsByClassAndRelationshipType(['party'] ,['isFunderOf','isFundedBy']);
                        if (is_array($grant_id))
                        {
                            $grant->GrantNumber = implode("\n", array_map('normaliseIdentifier', $grant_id));
                            if (is_array($related_party) && isset($related_party[0]))
                            {
                                $grant->addChild("FundingOrganization",$related_party[0]['title']);
                            }
                        }
                        else
                        {
                            unset($grant[0][0]);
                        }
                    }
                    else
                    {
                        unset($grant[0][0]);
                    }
                }
                $blankFundingInfoList = $sxml->xpath('//FundingInfoList[ParsedFunding/GrantNumber/text() = ""]');

                foreach($blankFundingInfoList as $blankFundingInfo){
                    unset($blankFundingInfo[0][0]);
               }


                $blankFundingInfos = $sxml->xpath('//FundingInfo[not(FundingInfoList)]');
                foreach($blankFundingInfos as $blankFundingInfo)
                    unset($blankFundingInfo[0][0]);


                // Post-process the Citations element
                $citations = $sxml->xpath('//CitationList[@postproc="1"]');
                foreach ($citations AS $i => $citations)
                {
                    // Remove the "to-process" marker
                    unset($citations[$i]["postproc"]);

                    /*$role->ResearcherID[0] = implode("\n", array_map('normaliseIdentifier', $researcher_ids));
                    if ((string) $role->ResearcherID[0] == "")
                    {
                        unset($roles[$i]->ResearcherID[0]);
                    }*/
                }


			    return trim(removeXMLDeclaration($sxml->asXML())) . NL;

		    }
            catch (Exception $e)
            {
                echo "UNABLE TO TRANSFORM" . BR;
                echo "<pre>" . nl2br($e->getMessage()) . "</pre>" . BR;
            }
        }
        else{
            return "";
        }
	}

	function transformToORCID()
	{
		try{
			$xslt_processor = Transforms::get_extrif_to_orcid_transformer();
			$dom = new DOMDocument();
			$dom->loadXML(htmlspecialchars_decode($this->ro->getExtRif()), LIBXML_NOENT);
			$xslt_processor->setParameter('','dateProvided', date("Y-m-d"));
			$xslt_processor->setParameter('','rda_url', portal_url($this->ro->slug));
			return $xslt_processor->transformToXML($dom);
		}catch (Exception $e)
		{
			echo "UNABLE TO TRANSFORM" . BR;	
			echo "<pre>" . nl2br($e->getMessage()) . "</pre>" . BR;
		}
	}

	function transformCustomForFORM($rifcs){
		try{
			$xslt_processor = Transforms::get_extrif_to_form_transformer();
			$dom = new DOMDocument();
			$dom->loadXML(htmlspecialchars_decode(htmlspecialchars($rifcs)), LIBXML_NOENT);
			$xslt_processor->setParameter('','base_url',base_url());
			return html_entity_decode($xslt_processor->transformToXML($dom));
		} catch (Exception $e) {
			echo "UNABLE TO TRANSFORM" . BR;
			echo "<pre>" . nl2br($e->getMessage()) . "</pre>" . BR;
		}
	}

	function cleanRIFCSofEmptyTags($rifcs, $removeFormAttributes='true', $throwExceptions = false){
		try{
			$xslt_processor = Transforms::get_form_to_cleanrif_transformer();
			$dom = new DOMDocument();
			//$dom->loadXML($this->ro->getXML());
			$dom->loadXML(str_replace('&', '&amp;' , $rifcs), LIBXML_NOENT);
			//$dom->loadXML($rifcs);
			$xslt_processor->setParameter('','removeFormAttributes',$removeFormAttributes);
			return $xslt_processor->transformToXML($dom);
		}catch (Exception $e)
		{

			if($throwExceptions)
			{
				throw new Exception("UNABLE TO TRANSFORM" . nl2br($e->getMessage()));
			}
			else{
				echo "UNABLE TO TRANSFORM" . BR;
				echo "<pre>" . nl2br($e->getMessage()) . "</pre>" . BR;
			}
		}
	}

    function substringAfter($string, $substring) {
        $pos = strpos($string, $substring);
        if ($pos === false)
            return $string;
        else
            return(substr($string, $pos+strlen($substring)));
    }

    function transformToEndnote()
    {
        $this->_CI->load->helper('normalisation');

        try{
            $xslt_processor = Transforms::get_extrif_to_endnote_transformer();
            $dom = new DOMDocument();
            $dom->loadXML(htmlspecialchars_decode($this->ro->getExtRif()), LIBXML_NOENT);
            $xslt_processor->setParameter('','dateHarvested', date("Y", $this->ro->created));
            $xslt_processor->setParameter('','dateRequested', date("Y-m-d"));
            $xslt_processor->setParameter('','portal_url', portal_url().$this->ro->slug."/".$this->ro->id);
            $xml_output = $xslt_processor->transformToXML($dom);

            //we want to post process the authors and funding name

            $authors = explode('%%%AU - ',$xml_output);

            for($i=1;$i<count($authors);$i++)
            {
                $author = explode(' - AU%%%',$authors[$i]);
                $author_object = $this->_CI->ro->getPublishedByKey(trim($author[0]));
                if($author_object->list_title){
                    $xml_output = str_replace('%%%AU - '.trim($author[0]).' - AU%%%','AU  - '.$author_object->list_title, $xml_output);
                }else{
                    $xml_output = str_replace('%%%AU - '.trim($author[0]).' - AU%%%
','', $xml_output);
                }
            }

           $funders = explode('%%%A4 - ', $xml_output);
           for($i=1;$i<count($funders);$i++)
           {

               $funder = explode(' - A4%%%',$funders[$i]);
               $grant_object = $this->_CI->ro->getPublishedByKey(trim($funder[0]));

               if ($grant_object && $grant_object->status == PUBLISHED && $grant_sxml = $grant_object->getSimpleXML(NULL, true))
               {
                   $grant_id = $grant_sxml->xpath("//ro:identifier[@type='arc'] | //ro:identifier[@type='nhmrc']");

                   $related_party = $grant_sxml->xpath("//extRif:related_object[extRif:related_object_relation = 'isFunderOf']");
                   if (is_array($grant_id))
                   {
                       if (is_array($related_party) && isset($related_party[0]))
                       {
                           $xml_output = str_replace('%%%A4 - '.trim($funder[0]).' - A4%%%','A4  - '.(string)$related_party[0]->children(EXTRIF_NAMESPACE)->related_object_display_title, $xml_output);
                       } else{
                           $xml_output = str_replace('%%%A4 - '.trim($funder[0]).' - A4%%%
','', $xml_output);
                       }

                   }
                   else
                   {
                       $xml_output = str_replace('%%%A4 - '.trim($funder[0]).' - A4%%%
','', $xml_output);
                   }
               }
               else
               {
                $xml_output = str_replace('%%%A4 - '.trim($funder[0]).' - A4%%%
','', $xml_output);
               }

           }

           return $xml_output;


        }
        catch (Exception $e)
        {
            echo "UNABLE TO TRANSFORM" . BR;
            echo "<pre>" . nl2br($e->getMessage()) . "</pre>" . BR;
        }
    }
}
	