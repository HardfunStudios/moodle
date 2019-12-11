<?php

define('AJAX_SCRIPT', true);
require_once('../../config.php');

global $DB;
$reactionid = required_param('reactionid', PARAM_TEXT);
$postid = required_param('postid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

$record = new stdClass;
$record->reactionid = $reactionid;
$record->userid = $userid;
$record->postid = $postid;

// Checks if user already reacted to the post

// Register reaction
$conditions = [
    'userid' => $userid,
    'postid' => $postid
];
$existingrecord = $DB->get_record('block_reactions_posts', $conditions);
if (!$existingrecord) {
    $res = $DB->insert_record('block_reactions_posts', $record);
} else {
    $existingrecord->reactionid = $reactionid;
    $res = $DB->update_record('block_reactions_posts', $existingrecord);
}

echo json_encode($res);
