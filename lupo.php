<?php
/**
 * LUPO Search plugin
 *
 * @package		Joomla
 * @subpackage	LUPO
 * @copyright   Copyright (C) databauer / Stefan Bauer 
 * @author		Stefan Bauer
 * @link		http://www.ludothekprogramm.ch
 * @license		License GNU General Public License version 2 or later
 */

// no direct access
defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

class plgSearchLupo extends JPlugin
{
	/**
	 * Constructor
	 *
	 * @access      protected
	 * @param       object  $subject The object to observe
	 * @param       array   $config  An array that holds the plugin configuration
	 * @since       1.5
	 */
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}

	/**
	* @return array An array of search areas
	*/
	function onContentSearchAreas()
	{
		static $areas = array(
			'lupo' => 'PLG_SEARCH_LUPO_TOYS'
		);
		return $areas;
	}

	/**
	* Search method
	*
	* The sql must return the following fields that are used in a common display
	* routine: href, title, section, created, text, browsernav
	* @param string Target search string
	* @param string mathcing option, exact|any|all
	* @param string ordering option, newest|oldest|popular|alpha|category
	 */
	function onContentSearch($text, $phrase='', $ordering='', $areas=null)
	{
		if (!class_exists( 'LupoModelLupo' )){
			JLoader::import( 'lupo', JPATH_BASE . '/components/com_lupo/models' );
		}
		$model = & new LupoModelLupo();


		$db		= JFactory::getDbo();
		$app	= JFactory::getApplication();
		$user	= JFactory::getUser();
		$groups	= implode(',', $user->getAuthorisedViewLevels());

        $request_type = JRequest::getVar('type');

		if (is_array($areas)) {
			if (!array_intersect($areas, array_keys($this->onContentSearchAreas()))) {
				return array();
			}
		}
		
		$limit			= $this->params->def('search_limit',		50);
		$params_itemid         = $this->params->def('Itemid',		'');

		$text = trim($text);
		if ($text == '') {
			return array();
		}

		$section = JText::_('PLG_SEARCH_LUPO_TOYS');

		switch ($ordering) {
			case 'alpha':
				$order = 'a.title ASC';
				break;

			case 'category':
				$order = 'c.title ASC, a.title ASC';
				break;

			case 'popular':
			case 'newest':
			case 'oldest':
			default:
				$order = 'a.title DESC';
		}

		$text_like	= $db->quote('%' . $db->escape($text, true) . '%', false);
		$rows = array();
		$query	= $db->getQuery(true);
		$query->select('a.id
						, a.title as title
						, "" AS created
						, a.id AS slug
						, c.id AS catslug
						, CONCAT (a.description_title, " ", a.description) as text
						, CONCAT("'.$section.'", " / ", c.title, IF(a.age_catid<>"", CONCAT(", " , ac.title),"")) AS section
						, CONCAT(c.title, " / ", ac.title) as cat_agecat
						, "2" AS browsernav
						, a.number');
		$query->from('#__lupo_game AS a');
		$query->leftJoin('#__lupo_categories AS c ON c.id = a.catid');
		$query->leftJoin('#__lupo_agecategories AS ac ON ac.id = a.age_catid');
		$query->where('(a.title LIKE '. $text_like .' OR a.description LIKE '. $text_like .' OR a.keywords LIKE '. $text_like .' OR a.genres LIKE '. $text_like .' OR a.number = '. $db->quote($db->escape($text, true)) .')');
		$query->order($order);
		$db->setQuery($query, 0, $limit);
		
		$rows = $db->loadObjectList();

		if ($rows) {
			foreach($rows as $key => $row) {

                //get Itemid for each search-result. Seach first for lupo-category menuitem, if not set take plugin-Itemid if set
                $query	= $db->getQuery(true);
                $query->select('m.id');
                $query->from('#__menu AS m');
                $query->where('m.link = "index.php?option=com_lupo&view=category&id='.$row->catslug.'"');
                $db->setQuery($query);
                $menu = $db->loadAssoc();
                $itemid = "";
                if(isset($menu['id'])){
                    $itemid = '&Itemid='.$menu['id'];
                } else {
                    if($params_itemid!=''){
                        $itemid = '&Itemid='.$params_itemid;
                    }
                }
                if ($request_type == 'json'){
                    $rows[$key]->text = $row->cat_agecat;
                }
				$rows[$key]->href = 'index.php?option=com_lupo&view=game&id='.$row->id.$itemid;


				//check if image exists
				$image = $model->getGameFoto($row->number, 'thumb_');
				$rows[$key]->image = $image['image_thumb'];
			}
		}
	
		return $rows;
	}
}
