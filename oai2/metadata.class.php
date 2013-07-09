<?php
/**
 * Created on 23 June 2009
 * This file shares functions for creating LOM metadata on all export formats
 * from the OUContent module exporter, OAI-PMH service and block/formats
 * create a download function (LabSpace only) so that metadata
 * is provided in a consistent way in all places
 *
 * @copyright &copy; 2009 The Open University
 * @author j.m.gray@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

class metadata {
    
    private $type;
    private $attributes;
    private $post;
    private $langstring;
    private $tr; // tag renderer class instance
    /*
     * Constructor function
     * @param $p the prefix for each lom tag
     * @param $t indicates scorm, ims cc or ims cp to get the right flavour of LOM
     * @param $a array of attribute key value pairs for the lom tag
     * @param $i the level of indent to start from
     */
    function __construct($t, $p='', $a=array(), $i=0) {
        $this->type = $t;
        $this->attributes = $a;
        $langstring = ($this->type == 'imscp') ? 'langstring': 'string';
        $this->tr = new tag_renderer($p,$i,$langstring);
    }

    /*
     * function simply sets up the metadata tag itself with schema info
     * should be used in a pair with end_metadata
     * includes manipulation of the prefix so there is not one for this tag
     */
    public function start_metadata(){
        $oldprefix = $this->tr->get_prefix();
        $this->tr->set_prefix();
        $lom = $this->tr->start_tag('metadata');

        // different for the various types
        switch ($this->type) {
            case 'imscp':
                $schema     = "IMS Content Package";
                $version    = "1.1.4";
                break;
            case 'scorm':
                $schema     = "ADL SCORM";
                $version    = "1.2";
                break;
            case 'imscc':
                $schema     = "IMS Common Cartridge";
                $version    = "1.0.0";
        }

        if (isset($schema)) {
            $lom .= $this->tr->full_tag('schema',$schema);
            $lom .= $this->tr->full_tag('schemaversion',$version);
        }

        $this->tr->set_prefix($oldprefix);
        return $lom;
    }

    /*
     * function simply closes the metadata tag
     * should be used in a pair with start_metadata
     */
    public function end_metadata() {
        $oldprefix = $this->tr->get_prefix();
        $this->tr->set_prefix();
        $lom = $this->tr->end_tag('metadata');
        $this->tr->set_prefix($oldprefix);
        return $lom;
    }

    /*
     * Helper function allows lom tag to be called with surrounding metadata tags
     * in one line.
     */
    public function get_metadata($post, $schema) {
        $this->set_post($post);

        $lom = $this->start_metadata();
        switch ($schema) {
            case 'lom':
                $lom .= $this->get_lom();
                break;
            case 'dc':
                $lom .= $this->get_dc();
                break;
            default:
                $lom .= "<error>UNDEFINED METADATA SCHEMA</error>";
        }

        $lom .= $this->end_metadata();

        return $lom;
    }

    /*
     * This is the main function which generates the LOM metadata tag
     * @param $post the object for the post on which metadata is required 
     * @returns string
     */
    public function get_lom($post=null, $lommode='LOMv1.0', $subset=array()) {
        if (isset($post)) {
            $this->set_post($post);
        }
        $this->lommode = $lommode;

        $lom = $this->tr->start_tag('lom',$this->attributes);

        if (count($subset) == 0) {
            $lom .= $this->get_general();
            $lom .= $this->get_lifecycle();
            if ($this->type != 'imscc') {
                // $lom .= $this->get_metameta();
            }
            $lom .= $this->get_technical();
            $lom .= $this->get_educational();
            $lom .= $this->get_rights();
            //$lom .= $this->get_relation();
        } else {
            foreach ($subset as $tagset) {
                $func = 'get_'.$tagset;
                $lom .= $this->$func();
            }
        }

        $lom .= $this->tr->end_tag('lom');

        return $lom;
    }

    private function get_general() {
        global $OAI_CONFIG;
        
        $lang = get_bloginfo('language');
        $lom = $this->tr->start_tag('general');

        if ($this->type == 'imscp') { // tag ordering and naming is slightly different here
            if ($title = $this->get_meta('imsmd_general_title')) {
                $lom .= $this->tr->lang_tag('title', array($lang=>$title));
            }
            
            $lom .= $this->tr->start_tag('catalogentry');
            $lom .= $this->tr->full_tag('catalog', $OAI_CONFIG['oaiprefix']);
            $lom .= $this->tr->lang_tag('entry', array(''=>$this->post->ID));
            $lom .= $this->tr->end_tag('catalogentry');
        } else {
            $lom .= $this->tr->start_tag('identifier');
            $lom .= $this->tr->full_tag('catalog', $OAI_CONFIG['oaiprefix']);
            $lom .= $this->tr->lang_tag('entry', array(''=>$this->post->ID));
            $lom .= $this->tr->end_tag('identifier');
            
            if ($title = $this->get_meta('imsmd_general_title')) {
                $lom .= $this->tr->lang_tag('title', array($lang=>$title));
            }
        }
        
        // empty tag not supported, lang not wanted in ilox
        if ($this->lommode != 'ILOX') {
            foreach ($this->get_meta('imsmd_general_language') as $language) {
                $lom .= $this->tr->full_tag('language', $language);
            }
        }
        
        if ($description = $this->get_meta('imsmd_general_description')) {
            $lom .= $this->tr->lang_tag('description', array($lang=>$description));
        }
        
        foreach ($this->get_meta('imsmd_general_keyword') as $keyword) {
            $lom .= $this->tr->lang_tag('keyword', array($lang=>$keyword));
        }
        
        if ($coverage = $this->get_meta('imsmd_general_coverage')) {
            $lom .= $this->tr->lang_tag('coverage', array($lang=>$coverage));
        }
        
        if ($this->type != 'imscc' && $this->lommode != 'ILOX') {
            if ($structure = $this->get_meta('imsmd_general_structure')) {
                $lom .= $this->vocab_tag('structure', $structure, 'structureValues');
            }
            
            $al_tag = ($this->type == 'imscp') ? 'aggregationlevel' : 'aggregationLevel';
            if ($level = $this->get_meta('imsmd_general_aggregationlevel')) {
                $lom .= $this->vocab_tag($al_tag, $level, 'aggregationLevelValues');
            }
        }
        
        $lom .= $this->tr->end_tag('general');

        return $lom;
    }

    private function get_lifecycle() {
        $tag = ($this->type=='imscp') ? 'lifecycle' : 'lifeCycle';
        $lom = $this->tr->start_tag($tag);
        
        if ($this->type != 'imscc' && $this->lommode != 'ILOX') {  // these tags not supported in CC
            if ($version = $this->get_meta('imsmd_lifecycle_version')) {
                $lom .= $this->tr->lang_tag('version', array(''=>$version));
            }
            if ($status = $this->get_meta('imsmd_lifecycle_status')) {
                $lom .= $this->vocab_tag('status', $status, 'statusValues');
            }
        }
        
        $lom .= $this->tr->end_tag($tag);
        return $lom;
    }

    private function build_contribute_tag($role,$source='roleValues',$person=null) {
        $str = $this->tr->start_tag('contribute');
        $str .= $this->vocab_tag('role',$role,$source);
        if ($this->type == 'imscp') {
            $str .= $this->tr->start_tag('centity');
            $str .= $this->tr->full_tag('vcard', $this->build_vcard($person));
            $str .= $this->tr->end_tag('centity');
        } else {
            $str .= $this->tr->start_tag('entity');
            $str .= "<![CDATA[" . $this->build_vcard($person) . "]]>";
            $str .= $this->tr->end_tag('entity');
        }
        $str .= $this->tr->end_tag('contribute');

        return $str;
    }

    private function build_vcard($person = null) {
        if (is_null($person)) {
            $person = array("name"=>"Open University", "institution"=>"Open University",
            "address"=>"Walton Hall, Milton Keynes, Buckinghamshire, MK7 6AA, United Kingdom");
        }

        $vcard = "BEGIN:VCARD\n";
        if (isset($person['name'])&& $person['name']!='') {
            $vcard .= "FN:$person[name]\n";
            $vcard .= "N:$person[name]\n";
        }
        if (isset($person['institution'])&& $person['institution']!='') {
            $vcard .= "ORG:$person[institution]\n";
        }
        if (isset($person['address'])&& $person['address']!='') {
            $vcard .= "ADR:$person[address]\n";
        }
        $vcard .= "VERSION:3.0\n";
        $vcard .= "END:VCARD";

        return $vcard;
    }

    private function get_metameta() {
        $mtag = 'metaMetadata';
        $stag = 'metadataSchema';
        if ($this->type == 'imscp') {
            $mtag = strtolower($mtag);
            $stag = 'metadatascheme';
        } 

        $lom = $this->tr->start_tag($mtag);
        $tag = ($this->type == 'imscp') ? 'catalogentry' : 'identifier';
        $lom .= $this->tr->start_tag($tag);
        $lom .= $this->tr->full_tag('catalog',$this->course->catalog);
        if ($this->type=='imscp'){
            $lom .= $this->tr->lang_tag('entry',array(''=>$this->course->shortname));
        } else {
            $lom .= $this->tr->full_tag('entry',$this->course->shortname);
        }
        $lom .= $this->tr->end_tag($tag);

        if ($this->lommode=='LREv4.0') {
            $lom .= $this->build_contribute_tag('creator','LRE.roleMetaValues');
        } else {
            $lom .= $this->build_contribute_tag('creator');
        }
        $lom .= $this->tr->full_tag($stag,$this->lommode); // note no change for ILOX lom type because ILOX dont include metameta

        if ($this->course->language != "") { // empty tag not supported for this one
            $lom .= $this->tr->full_tag('language',$this->course->language);
        }

        $lom .= $this->tr->end_tag($mtag);

        return $lom;
    }

    private function get_technical() {
        $lang = get_bloginfo('language');
        
        $lom = $this->tr->start_tag('technical');
        
        if ($this->type != 'imscc') {
            foreach ($this->get_meta('imsmd_technical_format') as $format) {
                $lom .= $this->tr->full_tag('format', $format);
            }
        }
        
        if ($size = $this->get_meta('imsmd_technical_size')) {
            $lom .= $this->tr->full_tag('size', $size);
        }
        
        foreach ($this->get_meta('imsmd_technical_location') as $location) {
            $lom .= $this->tr->full_tag('location', $location);
        }
        
        if ($installationremarks = $this->get_meta('imsmd_technical_installationremarks')) {
            $lom .= $this->tr->lang_tag('installationremarks', array($lang=>$installationremarks));
        }
 
        if ($otherplatformrequirements = $this->get_meta('imsmd_technical_otherplatformrequirements')) {
            $lom .= $this->tr->lang_tag('otherplatformrequirements', array($lang=>$otherplatformrequirements));
        }
 
        if ($duration = $this->get_meta('imsmd_technical_duration')) { //
            $lom .= $this->tr->start_tag('duration');
            $lom .= $this->tr->full_tag('datetime', $duration);
            $lom .= $this->tr->end_tag('duration');
        }
        
        $lom .= $this->tr->end_tag('technical');
        return $lom;
    }

    private function get_educational() {
        // imscp has some tag names different
        $lrt_tag = 'learningResourceType';
        $it_tag = 'interactivityType';
        $il_tag = 'interactivityLevel';
        $eur_tag = 'intendedEndUserRole';
        $tlt_tag = 'typicalLearningTime';
        $tar_tag = 'typicalAgeRange';
        $d_tag = 'duration';
        if($this->type == 'imscp') {
            $lrt_tag = strtolower($lrt_tag);
            $it_tag = strtolower($it_tag);
            $il_tag = strtolower($il_tag);
            $eur_tag = strtolower($eur_tag);
            $tlt_tag = strtolower($tlt_tag);
            $tar_tag = strtolower($tar_tag);
            $d_tag = "datetime";
        }
        
        $lang = get_bloginfo('language');
        $lom = $this->tr->start_tag('educational');

        if ($this->type != "imscc"  && $this->lommode != 'ILOX') { // tag not supported
            if ($intertype = $this->get_meta('imsmd_educational_interactivitytype')) {
                $lom .= $this->vocab_tag($it_tag, $intertype, 'interactivityTypeValues');
            }
        }
        
        if($this->type == 'imscc') {
            $lom .= $this->vocab_tag($lrt_tag, 'IMS Common Cartridge', 'learningResourceTypeValues');
        } else if ($this->type == 'scorm') {
            $lom .= $this->vocab_tag($lrt_tag, 'narrative text', 'LOMv1.0');
        } else if ($this->type == 'lre') {
            $lom .= $this->vocab_tag($lrt_tag, 'text', 'LRE.learningResourceTypeValues');
        } else {
            foreach ($this->get_meta('imsmd_educational_learningresourcetype') as $resourcetype) {
                $lom .= $this->vocab_tag($lrt_tag, $resourcetype, 'learningResourceTypeValues');
            }
        }
        
        if ($this->type != "imscc"  && $this->lommode != 'ILOX') { // tags not supported in CC
            if ($level = $this->get_meta('imsmd_educational_interactivitylevel')) {
                $lom .= $this->vocab_tag($il_tag, $level, 'interactivityLevelValues');
            }
            
            //if ($semanticdensity = $this->get_meta('imsmd_educational_semanticdensity')) {
            //    $lom .= $this->vocab_tag('semanticdensity', $semanticdensity, $rolevocab);
            //}
            
            if ($this->type == 'scorm') {
                $lom .= $this->vocab_tag($eur_tag, array('learner'=>'Learner', 'teacher'=>'Instructor'), 'scormspecial');
            } else {
                $rolevocab = ($this->type=='lre') ? 'LRE.intendedEndUserRoleValues' : 'intendedEndUserRoleValues';
                foreach ($this->get_meta('imsmd_educational_intendedenduserrole') as $userrole) {
                    $lom .= $this->vocab_tag($eur_tag, $userrole, $rolevocab);
                }
            }
            
            $contextvocab = ($this->type=='lre') ? 'LRE.contextValues' : 'contextValues';
            foreach ($this->get_meta('imsmd_educational_context') as $context) {
                $lom .= $this->vocab_tag('context', $context, $contextvocab);
            }
            
            foreach ($this->get_meta('imsmd_educational_typicalagerange') as $agerange) {
                $lom .= $this->tr->lang_tag($tar_tag, array($lang=>$agerange));
            }
            
            if ($difficulty = $this->get_meta('imsmd_educational_difficulty')) {
                    $lom .= $this->vocab_tag('difficulty',$difficulty,'difficultyValues');
            }
            
            if ($learningtime = $this->get_meta('imsmd_educational_typicallearningtime')) { 
                $lom .= $this->tr->start_tag($tlt_tag);
                $lom .= $this->tr->full_tag($d_tag, $learningtime);
                $lom .= $this->tr->end_tag($tlt_tag);
            }
            
            if ($description = $this->get_meta('imsmd_educational_description')) {
                $lom .= $this->tr->lang_tag('description', array($lang=>$description));
            }
            
            foreach ($this->get_meta('imsmd_educational_language') as $language) {
                $lom .= $this->tr->full_tag('language', $language);
            }
        }
        
        $lom .= $this->tr->end_tag('educational');
        return $lom;
    }

    private function get_rights() {
        $lang = get_bloginfo('language');

        // there are a few things that are different for the various LOM flavours
        $cror_tag   = 'copyrightAndOtherRestrictions';
        if($this->type == 'imscp') {
            $cror_tag   = strtolower('copyrightandotherrestrictions');
        }

        $lom = $this->tr->start_tag('rights');
        
        $source = ($this->lommode != 'ILOX') ? 'costValues' : 'http://imsglobal.org/lode/1.0/LOM.rightsCostValues.vdex';
        if ($cost = $this->get_meta('imsmd_rights_cost')) {
            $lom .= $this->vocab_tag('cost', $cost, $source);
        }
        
        $source = ($this->lommode != 'ILOX') ? 'copyrightAndOtherRestrictionsValues' : 'http://imsglobal.org/lode/1.0/LOM.copyrightValues.vdex';
        if ($restriction = $this->get_meta('imsmd_rights_copyrightandotherrestrictions')) {
            $lom .= $this->vocab_tag($cror_tag, $restriction, $source);
        }

        if ($description = $this->get_meta('imsmd_rights_description')) {
            $lom .= $this->tr->lang_tag('description', array($lang=>$description));
        }
        $lom .= $this->tr->end_tag('rights');

        return $lom;
    }

    private function get_relation() {
        $lom = "";
        $kindvalues =  ($this->type == 'lre') ? 'LRE.kindValues' : 'KindValues';

        if( $this->course->groupings) {
            foreach( $this->course->groupings as $id => $relation ) {

                $lom .= $this->tr->start_tag('relation');

                $lom .= $this->vocab_tag('kind','references',$kindvalues);

                $lom .= $this->tr->start_tag('resource');

                $lom .= $this->tr->lang_tag('description',array($this->course->language=>$relation['title']));

                $tag = ($this->type == 'imscp') ? 'catalogentry' : 'identifier';
                $lom .= $this->tr->start_tag($tag);
                $lom .= $this->tr->full_tag('catalog','URL');
                if ($this->type=='imscp'){
                    $lom .= $this->tr->lang_tag('entry',array('x-t-url'=>$relation['url']));
                } else {
                    $lom .= $this->tr->full_tag('entry',$relation['url']);
                }
                $lom .= $this->tr->end_tag($tag);

                $lom .= $this->tr->end_tag('resource');

                $lom .= $this->tr->end_tag('relation');
            }
        }

        //  Write out the parent course relation
        if(!empty($this->course->source)) {
            $lom .= $this->tr->start_tag('relation');

            $lom .= $this->vocab_tag('kind','isbasedon',$kindvalues);

            $lom .= $this->tr->start_tag('resource');

            $lom .= $this->tr->lang_tag('description',array('en-GB'=>'This is the title and course code of the source course material'));

            $tag = ($this->type == 'imscp') ? 'catalogentry' : 'identifier';
            $lom .= $this->tr->start_tag($tag);
            $lom .= $this->tr->full_tag('catalog','Open University course');
            if ($this->type=='imscp'){
                $lom .= $this->tr->lang_tag('entry',array('en-GB'=>$this->course->source));
            } else {
                $lom .= $this->tr->full_tag('entry',$this->course->source);
            }
            $lom .= $this->tr->end_tag($tag);

            $lom .= $this->tr->end_tag('resource');

            $lom .= $this->tr->end_tag('relation');
        }

        return $lom;
    }
    
    private function get_meta($field) {
        global $OAI_CONFIG, $IMSMD_CATEGORY, $IMSMD_CONFIG;
        require_once(ABSPATH.'/wp-admin/includes/taxonomy.php');
        
        $single = $IMSMD_CONFIG[$field]['multiplicity'] == 'single' ? true : false;
        $result = $single ? false : array();
        
        $options = get_option($field, array());
        if ($options[$this->post->post_type]['select'] != 'disable') {
            if ($options[$this->post->post_type]['select'] == 'enable') {
                $result = get_post_meta($this->post->ID, $field, $single);
            } else if ($options[$this->post->post_type]['select'] == 'wp_category') {
                $parent_id = 0;
                foreach ($IMSMD_CATEGORY as $category) {
                    $parent_id = wp_create_category($category, $parent_id);
                }
                $parent_id = wp_create_category($IMSMD_CONFIG[$field]['label'], $parent_id);
                $categories = get_categories(array('type'=>'post', 'child_of'=>$parent_id, 'parent'=>$parent_id));
                foreach (wp_get_object_terms($this->post->ID, 'category') as $term_category) {
                    foreach ($categories as $category) {
                        if ($term_category->term_id == $category->term_id) {
                            if ($single) {
                                $result = trim($term_category->name);
                                break;
                            } else if (!in_array(trim($term_category->name), $result)) {
                                array_push($result, trim($term_category->name));
                                break;
                            }
                        }
                    }
                    if ($single && $result != false) {
                        break;
                    }
                }
            } else if ($options[$this->post->post_type]['select'] == 'wp_post_tag') {
                foreach (wp_get_object_terms($this->post->ID, 'post_tag') as $term_category) {
                    if (!$single && !in_array(trim($term_category->name), $result)) {
                        array_push($result, trim($term_category->name));
                    }
                }
            } else {
                if ($single) {
                    $result = $this->post->{$options[$this->post->post_type]['select']};
                } else {
                    $result = explode(',', $this->post->{$options[$this->post->post_type]['select']});
                }
            }
        }
        //if (is_array($result) && empty($result)) { $result = false; }
        return $result;
    }
    
    public function get_dc($post=null) {
        global $OAI_CONFIG, $IMSMD_CATEGORY, $IMSMD_CONFIG;
        
        if (isset($post)) {
            $this->set_post($post);
        }

        $dc = "";
        if ($this->type != 'rdf') {
            $dc = $this->tr->start_tag('dc', $this->attributes);
            $oldprefix = $this->tr->get_prefix();
            $this->tr->set_prefix('dc:');
        }
        //--
        if ($title = $this->get_meta('imsmd_general_title')) {
            $dc .= $this->tr->full_tag('title', $title);
        }
        
        $subjects = array();  
        $options = get_option('imsmd_general_keyword', array());
        if ($options[$this->post->post_type]['select'] != 'disable') {
            if ($options[$this->post->post_type]['select'] == 'enable') {
                $subjects = get_post_meta($this->post->ID, 'imsmd_general_keyword', false);
            } else if ($options[$this->post->post_type]['select'] == 'wp_post_tag') {
                foreach (wp_get_object_terms($this->post->ID, 'post_tag') as $term_category) {
                    if (!in_array(trim($term_category->name), $subjects)) {
                        array_push($subjects, trim($term_category->name));
                    }
                }
            }
        }
        foreach (wp_get_object_terms($this->post->ID, 'category') as $term_category) {
            if (isset($OAI_CONFIG['SETS'][$term_category->term_id])) {
                $subject = $OAI_CONFIG['SETS'][$term_category->term_id]['setName'];
                if (!in_array(trim($subject), $subjects)) {
                    array_push($subjects, trim($subject));
                }
            }
        }
        foreach ($subjects as $subject) {
            $dc .= $this->tr->full_tag('subject', $subject);
        }
        
        if ($description = $this->get_meta('imsmd_general_description')) {
            $dc .= $this->tr->full_tag('description',$description);
        }
        

        foreach ($this->get_meta('imsmd_technical_format') as $format) {
            $dc .= $this->tr->full_tag('format', $format);
        }
        
        $dc .= $this->tr->full_tag('identifier', $OAI_CONFIG['oaiprefix'].$this->post->ID);
        
        foreach ($this->get_meta('imsmd_technical_location') as $location) {
            $dc .= $this->tr->full_tag('source', $location);
        }
        
        foreach ($this->get_meta('imsmd_general_language') as $language) {
            $dc .= $this->tr->full_tag('language', $language);
        }
               
        //--
        if ($this->type != 'rdf') {
            $this->tr->set_prefix($oldprefix);
            $dc .= $this->tr->end_tag('dc');
        }
        return $dc;
    }

    /*
     * Sets the post variable for this class
     * Means it doesn't have to be passed into the get routine for each
     * LOM top-level element
     *
     * Also checks if the post is a public contribution and sets a few extra
     * fields on the post object accordingly
     */
    private function set_post($post) {
        global $OAI_CONFIG;
        //print_r($course);
        //die;
        if (is_array($post)) {
            $post = (object) $post; // sometimes $course is an array, not an object
        }
        $this->post = $post;
        /*
        // change subject using sets
        $this->course->subject = $OAI_CONFIG['SETS'][$this->course->subject]['setName'];
        // pull in some extra data
        $site = get_site();
        $this->course->catalog = $site->shortname;
        //if (!isset($this->course->itemflag) && !isset($this->course->categoryname) && isset($this->course->category)) {
        //    $course->categoryname = get_field('course_categories','name','id',$this->course->category);
        //}
        
        $this->course->groupings = array();
        $this->course->tags = array();
        
        // and set up some defaults for public contributions
        if (!isset($this->course->licence)) {
            $this->course->licence = get_string($this->course->licenceshortname, 'license');
        }
        if (!$this->course->public) {
            $this->course->language = 'en-GB';
            $this->course->version='1.0';
        } else {
            $this->course->language='';

            preg_match('/^.*_(\d+\.\d+)$/',$this->course->shortname,$matches);
            if (isset($matches[1])) {
                $this->course->version = $matches[1];
            }

            // public contributors names
            $this->course->contributed = array();
        }*/
    }

    // not in tag renderer class because vocab handled differently for each type of metadata
    private function vocab_tag($tag, $content, $vocab) {
        $lang = get_bloginfo('language');
        
        $lom = $this->tr->start_tag($tag);
        if ($this->type == 'imscp') { // ims cp requires language strings
            $lom .= $this->tr->lang_tag('source', array($lang=>$vocab));
            $lom .= $this->tr->lang_tag('value', array($lang=>$content));
        } else if ($this->type == 'oai' || $this->type == 'scorm') {
            if ($vocab == 'scormspecial') {
                $lom .= $this->tr->full_tag('source','IMSGLC_CC_Rolesv1p0');
                $lom .= $this->tr->full_tag('value',$content['learner']);
                $lom .= $this->tr->full_tag('value',$content['teacher']);
            } else {
                $lom .= $this->tr->full_tag('source','LOMv1.0'); // only one valid entry!
                $lom .= $this->tr->full_tag('value',$content);
            }
        } else {
            $lom .= $this->tr->full_tag('source', $vocab);
            $lom .= $this->tr->full_tag('value', $content);
        }

        $lom .= $this->tr->end_tag($tag);

        return $lom;
    }
    
}

// Tag creation functions used by formats block and oucontent module exporter also
class tag_renderer {
    private $indent;
    private $prefix;
    private $langstring;

    function __construct($p='',$indent = 0, $l='string'){
        $this->prefix = $p;
        if ($this->prefix && substr($this->prefix,strlen($this->prefix))!=":") {
            $this->prefix .= ':';
        }

        $this->indent = $indent;
        $this->langstring = $l;
    }

    /*
     * Creates a string for an opening tag
     * @param $tag the tag name
     * @param $attributes array of attribuate key value pairs to add to the tag name
     * @param $oneline true if the return string should not inclue a line return
     * @param $empty true if the tag is empty and so should also be closed
     * @returns $string
     */
    public function start_tag($tag,$attributes=null,$oneline = false, $empty = false) {
        $this->indent++;

        $attrstring = '';
        if (!empty($attributes) && is_array($attributes)) {
            foreach ($attributes as $key => $value) {
                $attrstring .= " ".$this->xml_tag_safe_content($key)."=\"".
                $this->xml_tag_safe_content($value)."\"";
            }
        }

        $str =  str_repeat(" ",$this->indent*2)."<".$this->prefix.$tag.$attrstring;

        if ($empty) {
            $str .= "/";
            --$this->indent;
        }
        $str .= $oneline ? ">" : ">\n";

        return $str;
    }

    /*
     * Return the xml end tag, works in a pair with start_tag
     * @param $tag the tag name
     * @returns string
     */
    public function end_tag($tag,$oneline = false) {
        $str = $oneline ? "" : str_repeat(" ",$this->indent*2);

        $str .="</".$this->prefix.$tag.">"."\n";

        --$this->indent;
        return $str;
    }

    /*
     * Return the start tag, the contents and the end tag using start_tag and end_tag functions
     * @param $tag the tag name
     * @param $content the text to be held between the start and end tags
     * @param $attributes array of attribuate key value pairs to add to the tag name
     * @returns $string
     */
    public function full_tag($tag, $content, $attributes=null) {
        if(empty($content)){
            $tag = $this->start_tag($tag, $attributes,false, true);
        } else{
            $st = $this->start_tag($tag, $attributes,true);
            $co = $this->xml_tag_safe_content($content);
            $et = $this->end_tag($tag, true);
            $tag = $st.$co.$et;
        }
        return $tag;
    }

    public function lang_tag($tag, $content) {
        $lom = $this->start_tag($tag);
        foreach ($content as $lang => $value) {
            $attributes = array();
            if ($lang != '') {
                $langtype = ($this->langstring == 'langstring') ? 'xml:lang' : 'language'; // IMS CP has different attribute name
                $attributes[$langtype] = $lang;
            }
            $lom .= $this->full_tag($this->langstring, $value, $attributes);
        }
        $lom .= $this->end_tag($tag);

        return $lom;
    }

    public function set_prefix($p='') {
        $this->prefix = $p;
    }

    public function get_prefix() {
        return $this->prefix;
    }

    /*
     * strips all the control chars (\x0-\x1f) from the text but tabs (\x9),
     newlines (\xa) and returns (\xd). The delete control char (\x7f) is also included.
     because they are forbiden in XML 1.0 specs. The expression below seems to be
     UTF-8 safe too because it simply ignores the rest of characters.
     Called by full_tag()
     @param $content text to be processed
     @returns $string
     */
    private function xml_tag_safe_content($content) {
        $content = preg_replace("/[\x-\x8\xb-\xc\xe-\x1f\x7f]/is","",$content);
        $content = preg_replace("/\r\n|\r/", "\n", htmlspecialchars($content));
        return $content;
    }
}
?>
