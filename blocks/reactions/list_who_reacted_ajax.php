<?php

define('AJAX_SCRIPT', true);
require_once('../../config.php');

global $DB;
$postid = required_param('postid', PARAM_INT);
$reactionid = required_param('reactionid', PARAM_INT);

$sql = "SELECT ROW_NUMBER() OVER(ORDER BY u.firstname ASC) -1 AS id,
               u.firstname,
               u.lastname
            FROM {user} u
            JOIN {block_reactions_posts} rp ON rp.userid = u.id
            WHERE rp.postid = ? AND rp.reactionid = ?;";
$params = [$postid, $reactionid];

$result = $DB->get_records_sql($sql, $params);


echo json_encode($result);
