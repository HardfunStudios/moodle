<?php

define('AJAX_SCRIPT', true);
require_once('../../config.php');

global $DB;
$postid = required_param('postid', PARAM_INT);

$sql = "SELECT rp.reactionid,
        rp.postid,
        COUNT (rp.postid) as reactions_count
            FROM {block_reactions_posts} rp
            WHERE rp.postid = ?
            GROUP BY rp.postid, rp.reactionid;";
$params = [$postid];

$result = $DB->get_records_sql($sql, $params);


echo json_encode($result);
