<?php
//https://github.com/iktsenteret/pifu/blob/master/pifu-ims/docs/spesifikasjon.md
namespace askommune\pifu_parser;
use Exception;
use InvalidArgumentException;
use SimpleXMLElement;

class parser
{
    /**
     * @var SimpleXMLElement
     */
	public $xml=null;
	public $config;
    public $xml_file;

    /**
     * pifu_parser constructor.
     * @param string $xml_file PIFU XML file path
     * @param bool $load_xml Load XML
     * @throws Exception PIFU file not found
     */
	function __construct($xml_file=null, $load_xml = true)
	{
        if(empty($xml_file))
        {
            $this->config = require 'config.php';
            $this->xml_file = $this->config['pifu_xml_file'];
        }
        else
            $this->xml_file = $xml_file;
        if(!file_exists($this->xml_file))
            throw new Exception('Could not find XML file: '.$this->xml_file);

        if($load_xml)
		    $this->load_xml();
	}

    /**
     * @throws Exception PIFU file not found
     */
	function load_xml()
	{
		$xml_string=file_get_contents($this->xml_file);
		$xml_string=str_replace(' xmlns="http://pifu.no/xsd/pifu-ims_sas/pifu-ims_sas-1.1"','',$xml_string); //Remove namespace
		$this->xml=simplexml_load_string($xml_string);
	}

    /**
     * Check if an argument is a SimpleXMLElement with the correct XML element name
     * @param SimpleXMLElement $element
     * @param string $tag XML tag
     */
    public static function validate($element, $tag=null)
    {
        if(!is_object($element) || !is_a($element, 'SimpleXMLElement'))
            throw new InvalidArgumentException('Not a SimpleXMLElement');
        if(!empty($tag) && $element->getName()!==$tag)
            throw new InvalidArgumentException(sprintf('Tag name should be %s, not %s', $tag, $element->getName()));
    }

    /**
     * Get groups for a unit
     * @param string $school School id
     * @param int $level Group type
     * @return SimpleXMLElement[] Groups
     */
	function groups($school, $level=1)
	{
		$xpath=sprintf('/enterprise/group/relationship/sourcedid/id[.="%s"]/ancestor::group/grouptype/typevalue[@level=%d]/ancestor::group', $school, $level);
		return $this->xml->xpath($xpath);
	}

    /**
     * Get information about a group
     * @param string|SimpleXMLElement $school School id
     * @param string $group Group code
     * @return SimpleXMLElement
     */
	function group_info($school, $group)
    {
        if(is_object($school) && is_a($school, 'SimpleXMLElement'))
            $school = $school->{'sourcedid'}->{'id'};

        $xpath=sprintf('/enterprise/group/relationship/sourcedid/id[.="%s"]/ancestor::group/description/short[.="%s"]/ancestor::group', $school, $group);
        $result = $this->xml->xpath($xpath);
        if(!empty($result))
            return $result[0];
        else
            return null;
    }

    /**
     * Get information about a group
     * @param string $group_id Group id
     * @return SimpleXMLElement Group info
     */
    function group_info_id($group_id)
    {
        $xpath=sprintf('/enterprise/group/sourcedid/id[.="%s"]/ancestor::group', $group_id);
        $result = $this->xml->xpath($xpath);
        if(!empty($result))
            return $result[0];
        else
            return null;
    }

	function schools()
	{
		$xpath='/enterprise/group/grouptype[scheme="pifu-ims-go-org" and typevalue[@level=2]]/ancestor::group';
		return $this->xml->xpath($xpath);
	}
	/**
     * @deprecated To be removed, use group_members instead
     **/
	function members($group)
	{
		if(is_object($group))
			$group=$group->sourcedid->id;
		if(empty($group))
			throw new InvalidArgumentException('Empty argument');

		$xpath=sprintf('/enterprise/membership/sourcedid/id[.="%s"]/ancestor::membership/member',$group);
		return $this->xml->xpath($xpath);
	}

    /**
     * Get members of a group
     * @param string|SimpleXMLElement $group
     * @param array $options
     * @return SimpleXMLElement[] Group members
     */
	function group_members($group,$options=array('status'=>1,'roletype'=>null))
	{
	    if(!is_string($group))
        {
            self::validate($group, 'group');
            $group=(string)$group->{'sourcedid'}->{'id'};
        }

		if(empty($group))
			throw new InvalidArgumentException('Empty argument');
		if(!is_string($group))
			throw new InvalidArgumentException('Invalid argument');
		if(!empty($options['roletype']) && !is_numeric($options['roletype']))
			throw new InvalidArgumentException('roletype must be numeric');

		$xpath=sprintf('/enterprise/membership/sourcedid/id[.="%s"]/ancestor::membership/member',$group);
		if(!empty($options['roletype']))
			$xpath=sprintf('%s/role[@roletype="%s"]',$xpath,$options['roletype']);
		else
			$xpath.='/role';

		if(isset($options['status']) && $options['status']!==false)
			$xpath.=sprintf('/status[.="%d"]',$options['status']);

		$xpath.='/ancestor::member';

		return $this->xml->xpath($xpath);
	}

    /**
     * Get person memberships
     * @param string $person Person id
     * @param string $status Membership status
     * @return SimpleXMLElement[] Array of memberships
     */
	function person_memberships($person,$status=null)
	{
		if(empty($person) || !is_string($person))
			throw new InvalidArgumentException('Empty or invalid argument');
		if(empty($status))
			$xpath=sprintf('/enterprise/membership/member/sourcedid/id[.="%s"]/parent::sourcedid/parent::member',$person);
		else
			$xpath=sprintf('/enterprise/membership/member/sourcedid/id[.="%s"]/parent::sourcedid/parent::member/role/status[.="%d"]/parent::role/parent::member',$person,$status);
		return $this->xml->xpath($xpath);
	}

    /**
     * Get information about a person
     * @param string $person_id Person id
     * @return SimpleXMLElement|null
     */
	function person($person_id)
	{
		$xpath=sprintf('/enterprise/person/sourcedid/id[.="%s"]/ancestor::person',$person_id);
		$person=$this->xml->xpath($xpath);
		if(empty($person))
			return null;
		else
			return $person[0];
	}

    /**
     * Find person by user id
     * @param string $id User id
     * @param string $type User id type
     * @return SimpleXMLElement
     */
	function person_by_userid($id,$type)
	{
		$xpath=sprintf('/enterprise/person/userid[@useridtype="%s" and .="%s"]/ancestor::person',$type,$id);
		$person=$this->xml->xpath($xpath);
		if(empty($person))
			return null;
		else
			return $person[0];
	}

    /**
     * Get a persons phone number
     * @param SimpleXMLElement $person Person object
     * @param int $teltype Telephone type
     * @return string Telephone number
     */
	function phone($person,$teltype)
	{
        self::validate($person, 'person');
		$xpath=sprintf('.//tel[@teltype="%s"]',$teltype);
		$result=$person->xpath($xpath);
		if(!empty($result))
			return (string)$result[0];
		else
		    return '';
	}

    /**
     * Get groups for a unit and order them using natural sort
     * @param string $school Unit id
     * @param int $level Group type
     * @return array Ordered groups
     */
	function ordered_groups($school, $level=1)
	{
		foreach($this->groups($school, $level) as $group)
		{
			$sort_parameter=(string)$group->{'description'}->{'short'};
			$groups[$sort_parameter]=$group;
		}
		if(!isset($groups))
			return null;
		ksort($groups,SORT_NATURAL);
		return $groups;
	}

    /**
     * Get group members ordered by name
     * @param SimpleXMLElement|string $group Group
     * @param array $options Options
     * @return SimpleXMLElement[] Ordered members
     */
	function ordered_members($group, $options=array('status'=>1,'roletype'=>'01', 'order_by_name'=>'given'))
	{
	    if(empty($options['order_by_name']))
            $options['order_by_name']='given';

		foreach($this->group_members($group, $options) as $member)
		{
		    $person = $this->person($member->{'sourcedid'}->{'id'});
            if($options['order_by_name']==='given')
                $name = (string)$person->{'name'}->{'n'}->{'given'};
            elseif($options['order_by_name']==='family')
                $name = (string)$person->{'name'}->{'n'}->{'family'};
            else
                throw new InvalidArgumentException('Invalid sort');

            $members[$name]=$member;
		}
		if(empty($members))
			return null;
		ksort($members,SORT_NATURAL);
		return $members;
	}
}