<?php

use App\Models\UserTopicStat;

$s = UserTopicStat::where('user_id', 4)->get();
echo $s->toJson(JSON_PRETTY_PRINT);
