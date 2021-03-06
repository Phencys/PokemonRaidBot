<?php
// Write to log.
debug_log('vote_time()');

// For debug.
//debug_log($update);
//debug_log($data);

// Check if the user has voted for this raid before.
$rs = my_query(
    "
    SELECT    user_id, remote, (1 + extra_valor + extra_instinct + extra_mystic) as user_count
    FROM      attendance
      WHERE   raid_id = {$data['id']}
        AND   user_id = {$update['callback_query']['from']['id']}
    "
);

// Get the answer.
$answer = $rs->fetch();

// Write to log.
debug_log($answer);

// Get the arg.
$arg = $data['arg'];

// Raid anytime?
if($arg == 0) {
    // Raid anytime.
    $attend_time = '0000-00-00 00:00:00';
} else {
    // Normal raid time - convert data arg to UTC time.
    $dt = new DateTime();
    $dt_attend = $dt->createFromFormat('YmdHis', $arg, new DateTimeZone('UTC'));
    $attend_time = $dt_attend->format('Y-m-d H:i:s');
}

// Get current time.
$now = new DateTime('now', new DateTimeZone('UTC'));
$now = $now->format('Y-m-d H:i') . ':00';

// Vote time in the future or Raid anytime?
if($now <= $attend_time || $arg == 0) {
    // If user is attending remotely, get the number of remote users already attending
    $remote_users = (($answer['remote']==0) ? 0 : get_remote_users_count($data['id'], $update['callback_query']['from']['id'], $attend_time));
    // Check if max remote users limit is already reached, unless voting for 'Anytime'
    if ($answer['remote'] == 0 || $remote_users + $answer['user_count'] <= $config->RAID_REMOTEPASS_USERS_LIMIT || $arg == 0) {
        // User has voted before.
        if (!empty($answer)) {
            // Update attendance.
            alarm($data['id'],$update['callback_query']['from']['id'],'change_time', $attend_time);
            my_query(
                "
                UPDATE    attendance
                SET       attend_time = '{$attend_time}',
                          cancel = 0,
                          arrived = 0,
                          raid_done = 0,
                          late = 0
                  WHERE   raid_id = {$data['id']}
                    AND   user_id = {$update['callback_query']['from']['id']}
                "
            );

        // User has not voted before.
        } else {
            // Create attendance.
            alarm($data['id'],$update['callback_query']['from']['id'],'new_att', $attend_time);
            my_query(
                "
                INSERT INTO   attendance
                SET           raid_id = {$data['id']},
                              user_id = {$update['callback_query']['from']['id']},
                              attend_time = '{$attend_time}'
                "
            );
        }
    } else {
        // Send max remote users reached.
        send_vote_remote_users_limit_reached($update);
    }

} else {
    // Send vote time first.
    send_vote_time_future($update);
    send_response_vote($update, $data);
}

// Send vote response.
   if($config->RAID_PICTURE) {
	    send_response_vote($update, $data,false,false);
    } else {
	    send_response_vote($update, $data);
    }

exit();
