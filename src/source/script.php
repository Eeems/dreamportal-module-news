<?php

if (!defined('DP'))
	die('Hacking Attempt...');

/*
	(c) Site News 1.0 2011 Dream Portal Team
*/

function module_news($params)
{
	global $context, $txt, $settings, $modSettings;

	// Grab the parameters, if they exist.
	if (is_array($params))
	{
		$board = empty($params['board']) ? 1 : $params['board'];
		$limit = empty($params['limit']) ? 5 : $params['limit'];

		// Store the board news
		$input = dp_boardNews($board, $limit);

		// Default - Any content?
		if (empty($input))
		{
			module_error('empty');
			return;
		}
		foreach ($input as $news)
		{
			echo '
									<div class="dp_news">
										<div class="dp_news_head">
											<img src="', $settings['images_url'], '/on.png" alt="" />
											<p>
												<a href="', $news['href'], '"><strong>', $news['subject'], '</strong></a> ', $txt['by'], ' ', (!empty($modSettings['dp_color_members']) ? $news['color_poster'] : $news['poster']), '<br />
												<span class="smalltext">', $news['time'], '</span>
											</p>
										</div>';
			echo parse_bbc($news['body']);
			echo '<hr/> <a href="?topic=',$news['id_about'] , ' ">Discuss this article ( ', $news['posts_about'] ,' )</a>';
			
			if(!$news['is_last']){
				echo '
										<div class="dp_dashed clear"><!-- // --></div>';
			}else{
				echo '
										<div class="clear"><!-- // --></div>';
			}
			echo '
									</div>';

		}
	}
	// Throw an error.
	else
		module_error();
}


function dp_boardNews($board, $limit)
{
	global $scripturl, $smcFunc, $modSettings,$smf_eeems,$boarddir,$db_prefix;
	require_once($boarddir . '/SSI_eeems.php');

	if(!($smf_eeems instanceof SMF))
		$smf_eeems = new SMF();	

	if (!loadLanguage('Stats'))
		loadLanguage('Stats');

	$request = $smcFunc['db_query']('', '
		SELECT b.id_board
		FROM {db_prefix}boards AS b
		WHERE b.id_board = {int:current_board}
			AND {query_see_board}
		LIMIT 1',
		array(
			'current_board' => $board,
		)
	);

	if ($smcFunc['db_num_rows']($request) == 0)
		return array();

	list ($board) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$request = $smcFunc['db_query']('', '
		SELECT id_first_msg
		FROM {db_prefix}topics
		WHERE id_board = {int:current_board}' . ($modSettings['postmod_active'] ? '
			AND approved = {int:is_approved}' : '') . '
		ORDER BY id_first_msg DESC
		LIMIT ' . $limit,
		array(
			'current_board' => $board,
			'is_approved' => 1,
		)
	);

	$posts = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$posts[] = $row['id_first_msg'];
	$smcFunc['db_free_result']($request);

	if (empty($posts))
		return array();

	$request = $smcFunc['db_query']('', '
		SELECT
			m.body,m.subject, IFNULL(mem.real_name, m.poster_name) AS poster_name, m.poster_time,
			t.id_topic, m.id_member, mg.online_color
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)
		WHERE t.id_first_msg IN ({array_int:post_list})
		ORDER BY t.id_first_msg DESC
		LIMIT ' . count($posts),
		array(
			'post_list' => $posts,
		)
	);

	$return = array();

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$res = $smf_eeems->sql->query("
			SELECT topic_about_id
			FROM {$db_prefix}topic_articles
			WHERE topic_article_id = ?
			",'i',$row['id_topic'])->assoc_result;
		if(!empty($res)){
			 $posts=$smf_eeems->topic($res['topic_about_id'])->post_count;
			$return[] = array(
				'body'=>$row['body'],
				'subject' => $row['subject'],
				'time' => timeformat($row['poster_time']),
				'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
				'poster' => !empty($row['id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>' : $row['poster_name'],
				'color_poster' => !empty($row['id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '"><span style="color: ' . $row['online_color'] . ';">' . $row['poster_name'] . '</span></a>' : $row['poster_name'],
				'id_about'=>$res['topic_about_id'],
				'posts_about'=>$posts,
				'is_last' => false
			);}
		else {
			$posts=$smf_eeems->topic($row['id_topic'])->post_count;
			$return[] = array(
				'body'=>$row['body'],
				'subject' => $row['subject'],
				'time' => timeformat($row['poster_time']),
				'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
				'poster' => !empty($row['id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>' : $row['poster_name'],
				'color_poster' => !empty($row['id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '"><span style="color: ' . $row['online_color'] . ';">' . $row['poster_name'] . '</span></a>' : $row['poster_name'],
				'id_about'=>$row['id_topic'],
				'posts_about'=>$posts,
				'is_last' => false
			);}
		
	}

	$smcFunc['db_free_result']($request);

	if(empty($return)){
		return $return;
	}
	$return[count($return) - 1]['is_last'] = true;
	return $return;
}


?>
