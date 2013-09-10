<?php

/**
 *
 * This Model is responsible for getting data from the
 * com_easyblog data source
 *
 **/

// no direct access
defined('_JEXEC') or die('Restricted access');

class NSP_GK5_com_easyblog_Model {
	// Method to get sources of articles
	static function getSources($config) {
		if($config['data_source'] != 'easyblog_all' && $config['data_source'] != 'easyblog_authors') {
			//
			$db = JFactory::getDBO();
			// if source type is section / sections
			$source = false;
			$where1 = '';
			$where2 = '';
			$tag_join= '';
			//
			if( $config['data_source'] == 'easyblog_tags' ) {
				$tag_join = ' LEFT JOIN #__easyblog_post_tag AS xref ON content.id = xref.post_id LEFT JOIN #__easyblog_tag AS t ON t.id = xref.tag_id ';
			}
			//
			if($config['data_source'] == 'easyblog_categories'){
				$source = $config['easyblog_categories'];
				$where1 = ' c.id = ';
				$where2 = ' OR c.id = ';
			} else if($config['data_source'] == 'easyblog_tags') {
	           	$where1 = ' t.id = ';
	           	// adding quotes to tag name
	          	$source = $config['easyblog_tags'];
	         
	        	if(!is_array($source)) {
	           		$source = array($source);
	        	}
			} else {
				$source = strpos($config['easyblog_articles'],',') !== false ? explode(',', $config['easyblog_articles']) : $config['easyblog_articles'];
				$where1 = ' content.id = ';
				$where2 = ' OR content.id = ';	
			}
			//	
			$where = ''; // initialize WHERE condition
			// generating WHERE condition
			for($i = 0;$i < count($source);$i++){
				if(count($source) == 1) $where .= (is_array($source)) ? $where1.$source[0] : $where1.$source;
				else $where .= ($i == 0) ? $where1.$source[$i] : $where2.$source[$i];		
			}
			//
			$query_name = '
				SELECT 
					c.id AS CID
				FROM 
					#__easyblog_category AS c
				LEFT JOIN 
					#__easyblog_post AS content 
					ON 
					c.id = content.category_id 	
				'.$tag_join.' 
				WHERE 
					( '.$where.' ) 
					AND 
					c.published = 1
		        ';	
			// Executing SQL Query
			$db->setQuery($query_name);
	
			// check if some categories was detected
			if($categories = $db->loadObjectList()) {
				$categories_array = array();
				// iterate through all items 
				foreach($categories as $item) {
					if(!in_array($item->CID, $categories_array)) {
						array_push($categories_array, $item->CID);
					}
				}
				//
				return $categories_array;
			} else {
				// when no categories detected
				return null;
			}
		} else {
			return null;
		}
	}
	// Method to get articles in standard mode 
	static function getArticles($categories, $config, $amount) {	
		//
		$sql_where = '';
		$tag_join = '';
		//
		if($categories) {		
			// getting categories ItemIDs
			for($j = 0; $j < count($categories); $j++) {
				$sql_where .= ($j != 0) ? ' OR content.category_id = ' . $categories[$j] : ' content.category_id = '. $categories[$j];
			}	
		}
		// Overwrite SQL query when user set IDs manually
		if($config['data_source'] == 'easyblog_articles' && $config['easyblog_articles'] != ''){
			// initializing variables
			$sql_where = '';
			$ids = explode(',', $config['easyblog_articles']);
			//
			for($i = 0; $i < count($ids); $i++ ){	
				// linking string with content IDs
				$sql_where .= ($i != 0) ? ' OR content.id = '.$ids[$i] : ' content.id = '.$ids[$i];
			}
		}
		// Overwrite SQL query when user specified tags
		if($config['data_source'] == 'easyblog_tags' && $config['easyblog_tags'] != ''){
			// initializing variables
			$sql_where = '';
			$tag_join = ' LEFT JOIN #__easyblog_post_tag AS xref ON content.id = xref.post_id LEFT JOIN #__easyblog_tag AS t ON t.id = xref.tag_id ';
			// getting tag
			$sql_where .= ' t.id = '. $config['easyblog_tags'];
		}
		// Overwrite SQL query when user specified authors
		if($config['data_source'] == 'easyblog_authors' && $config['easyblog_authors'] != ''){
			// initializing variables
			$sql_where = '';
			$ids = explode(',', $config['easyblog_authors']);
			//
			for($i = 0; $i < count($ids); $i++ ){	
				// linking string with content IDs
				$sql_where .= ($i != 0) ? ' OR content.created_by = '.$ids[$i] : ' content.created_by = '.$ids[$i];
			}
		}
		// Arrays for content
		$content = array();
		$news_amount = 0;
		// Initializing standard Joomla classes and SQL necessary variables
		$db = JFactory::getDBO();
		$access_con = '';
		
		if($config['news_unauthorized'] == '1') {
			$access_con = ' AND (content.private = 1 OR content.private = 0) ';
		} else {
			$access_con = ' AND content.private = 0 ';
		}
		$app = JFactory::getApplication();
		$timezone = $app->getCfg('offset') + $config['time_offset'];
		$date = JFactory::getDate("now", $timezone);
		$now  = $date->toSql(true);
		$nullDate = $db->getNullDate();
		// if some data are available
		// when showing only frontpage articles is disabled
		$frontpage_con1 = '';
		$frontpage_con2 = '';
		
		if($config['only_featured'] == 0 && $config['news_featured'] == 0) {
		 	$frontpage_con1 = ' LEFT JOIN #__easyblog_featured as featured ON content.id = featured.content_id ';
		 	$frontpage_con2 = ' AND (featured.id IS NULL OR (featured.id IS NOT NULL AND (featured.type NOT LIKE "post" OR featured.type IS NULL))) ';
		} else if($config['only_featured'] == 1) {
			$frontpage_con1 = ' LEFT JOIN #__easyblog_featured as featured ON content.id = featured.content_id ';
			$frontpage_con2 = ' AND featured.id IS NOT NULL AND featured.type LIKE "post"';
		}
		
		$since_con = '';
		//
		if($config['news_since'] !== '') {
			$since_con = ' AND content.created >= ' . $db->Quote($config['news_since']);
		}
		//
		if($config['news_since'] == '' && $config['news_in'] != '') {
			$since_con = ' AND content.created >= ' . $db->Quote(strftime('%Y-%m-%d 00:00:00', time() - ($config['news_in'] * 24 * 60 * 60)));
		}
		// current article hiding
		$current_con = '';
		
		if(
			$config['hide_current_easyblog_article'] == '1' && 
			JRequest::getCmd('option') == 'com_easyblog' &&
			JRequest::getCmd('view') == 'entry' &&
			JRequest::getVar('id') != ''
		) {
			$id = JRequest::getVar('id');
			// filter the alias from ID
			if(stripos($id, ':') !== FALSE) {
				$id = explode(':', $id);
				$id = $id[0];
			}
			// create the condition
			$current_con = ' AND (content.id != '.$id.') ';
		}
		// Ordering string
		$order_options = '';
		// When sort value is random
		if($config['news_sort_value'] == 'random') {
			$order_options = ' RAND() '; 
		}else{ // when sort value is different than random
			$order_options = ' content.'.$config['news_sort_value'].' '.$config['news_sort_order'].' ';
		}	
		// language filters
		$lang_filter = '';
		if (JFactory::getApplication()->getLanguageFilter()) {
			$lang_filter = ' AND content.language in ('.$db->quote(JFactory::getLanguage()->getTag()).','.$db->quote('*').') ';
		}
		
		if($config['data_source'] != 'easyblog_all') {
			$sql_where = ' AND ( ' . $sql_where . ' ) ';
		}
		// one article per page - helper variables
		$article_id_query = 'content.id AS id';
		$one_article_query = '';
		
		if($config['one_article_per_category']) {
			$article_id_query = 'MAX(content.id) AS id, content.category_id AS cid';
			$one_article_query = ' GROUP BY content.category_id ';
		}
		// creating SQL query			
		$query_news = '
		SELECT
			'.$article_id_query.'				
		FROM 
			#__easyblog_post AS content 
			'.$tag_join.'
		'.$frontpage_con1.'
		WHERE 
			content.published = 1
                '. $access_con .'   
		 		AND ( content.publish_up = '.$db->Quote($nullDate).' OR content.publish_up <= '.$db->Quote($now).' )
				AND ( content.publish_down = '.$db->Quote($nullDate).' OR content.publish_down >= '.$db->Quote($now).' )
			'.$sql_where.'
			'.$lang_filter.'
			'.$frontpage_con.' 
			'.$since_con.'
			'.$current_con.'
			'.$frontpage_con2.'
		'.$one_article_query.'	
		
		ORDER BY 
			'.$order_options.'
		LIMIT
			'.($config['offset']).','.$amount.';
		';
		// run SQL query
		$db->setQuery($query_news);
		// when exist some results
		if($news = $db->loadAssocList()) {			
			// generating tables of news data
			foreach($news as $item) {	
				$content[] = $item; // store item in array
				$news_amount++;	// news amount
			}
		}
		// generate SQL WHERE condition
		$second_sql_where = '';
		for($i = 0; $i < count($content); $i++) {
			$second_sql_where .= (($i != 0) ? ' OR ' : '') . ' content.id = ' . $content[$i]['id'];
		}
		// second SQL query to get rest of the data and avoid the DISTINCT
		$second_query_news = '
		SELECT
			content.id AS id,
			content.permalink AS alias,
			'.($config['use_title_alias'] ? 'content.permalink' : 'content.title').' AS title, 
			content.intro AS text, 
			content.created AS date, 
			content.publish_up AS date_publish,
			content.hits AS hits,
			content.frontpage AS frontpage,
			content.category_id AS cid,
			categories.title AS catname, 
			categories.alias AS cat_alias,
			users.email AS author_email,
			'.$config['username'].' AS author_username,
			content.created_by AS author_id,
			content.image AS image
		FROM 
			#__easyblog_post AS content 
			LEFT JOIN 
				#__easyblog_category AS categories 
				ON categories.id = content.category_id 
			LEFT JOIN 
				#__users AS users 
				ON users.id = content.created_by 			
		WHERE 
			'.$second_sql_where.'
		ORDER BY 
			'.$order_options.'
		';
		// run the query
		$db->setQuery($second_query_news);
		// when exist some results
		if($news2 = $db->loadAssocList()) {
			// create the iid array
			$content_id = array();
			// create the content IDs array
			foreach($content as $item) {
				array_push($content_id, $item['id']);
			}
			// generating tables of news data
			foreach($news2 as $item) {						
			    $pos = array_search($item['id'], $content_id);
				// merge the new data to the array of items data
				$content[$pos] = array_merge($content[$pos], (array) $item);
			}
		}
		// load comments
		if(stripos($config['info_format'], '%COMMENTS') !== FALSE || stripos($config['info2_format'], '%COMMENTS') !== FALSE) {
			$content = NSP_GK5_com_easyblog_Model::getComments($content, $config);
		}
		// load tags
		if(stripos($config['info_format'], '%TAGS') !== FALSE || stripos($config['info2_format'], '%TAGS') !== FALSE) {
			$content = NSP_GK5_com_easyblog_Model::getTags($content, $config);
		}
		// the content array
		return $content; 
	}
	// method to get comments amount
	static function getComments($content, $config) {
		// 
		$db = JFactory::getDBO();
		$counters_tab = array();
		// 
		if(count($content) > 0) {
			// initializing variables
			$sql_where = '';
			//
			for($i = 0; $i < count($content); $i++ ) {	
				// linking string with content IDs
				$sql_where .= ($i != 0) ? ' OR content.id = '.$content[$i]['id'] : ' content.id = '.$content[$i]['id'];
			}
			// check the comments source
			if($config['easyblog_comments_source'] == 'easyblog') {
				// creating SQL query
				$query_news = '
				SELECT 
					content.id AS id,
					COUNT(comments.post_id) AS count			
				FROM 
					#__easyblog_post AS content 
					LEFT JOIN 
						#__easyblog_comment AS comments
						ON comments.post_id = content.id 		
				WHERE 
					comments.published
					AND ( '.$sql_where.' ) 
				GROUP BY 
					comments.post_id
				;';
			} elseif($config['easyblog_comments_source'] == 'komento') {
				// creating SQL query
				$query_news = '
				SELECT 
					content.id AS id,
					COUNT(comments.cid) AS count			
				FROM 
					#__easyblog_post AS content 
					LEFT JOIN 
						#__komento_comments AS comments
						ON comments.cid = content.id 		
				WHERE 
					comments.published = 1
					AND 
					( '.$sql_where.' )
					AND
					comments.component = \'com_easyblog\'  
				GROUP BY 
					comments.cid
				;';
			}
			// run SQL query
			$db->setQuery($query_news);
			// when exist some results
			if($counters = $db->loadObjectList()) {
				// generating tables of news data
				foreach($counters as $item) {						
					$counters_tab[$item->id] = $item->count;
				}
			}
		}
		//
		for($i = 0; $i < count($content); $i++ ) {	
			if(isset($counters_tab[$content[$i]['id']])) {
				$content[$i]['comments'] = $counters_tab[$content[$i]['id']];
			}
		}
		
		return $content;
	}	
	//
	static function getTags($content, $config) {
		// 
		$db = JFactory::getDBO();
		$counters_tab = array();
		// 
		if(count($content) > 0) {
			// initializing variables
			$sql_where = '';
			//
			for($i = 0; $i < count($content); $i++ ) {	
				// linking string with content IDs
				$sql_where .= ($i != 0) ? ' OR content.id = '.$content[$i]['id'] : ' content.id = '.$content[$i]['id'];
			}
			// creating SQL query
			$query_news = '
			SELECT 
				content.id AS id,
				tags.title AS tag,
				tags.id AS tag_id	
			FROM 
				#__easyblog_post AS content 
				LEFT JOIN 
					#__easyblog_post_tag AS xref
					ON xref.post_id = content.id
				LEFT JOIN
					#__easyblog_tag AS tags
					ON xref.tag_id = tags.id 		
			WHERE 
				tags.published = 1
				AND ( '.$sql_where.' ) 
			ORDER BY
				content.id ASC
			;';
			// run SQL query
			$db->setQuery($query_news);
			// when exist some results
			if($counters = $db->loadObjectList()) {
				// generating tables of news data
				foreach($counters as $item) {			
					if(isset($counters_tab[$item->id])) {			
						$counters_tab[$item->id][$item->tag] = $item->tag_id;
					} else {
						$counters_tab[$item->id] = array($item->tag => $item->tag_id);
					}
				}
			}
		}
		//
		for($i = 0; $i < count($content); $i++ ) {	
			if(isset($counters_tab[$content[$i]['id']])) {
				$content[$i]['tags'] = $counters_tab[$content[$i]['id']];
			}
		}
		
		return $content;
	}	
}

// EOF
