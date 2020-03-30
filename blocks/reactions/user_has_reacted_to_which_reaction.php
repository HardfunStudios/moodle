<?php

define('AJAX_SCRIPT', true);
require_once('../../config.php');

global $DB;
$postid = required_param('postid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

$sql = "SELECT rp.reactionid
            FROM {block_reactions_posts} rp
            WHERE rp.postid = ? AND rp.userid = ?;";
$params = [$postid, $userid];

$result = $DB->get_records_sql($sql, $params);


echo json_encode($result);
