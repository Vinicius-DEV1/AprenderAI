<?php
$l = app(\App\Services\AI\QueryLexicalAnalyser::class);
dump($l->analyse('portugues, questoes médias'));
dump($l->analyse('portugues, questoes medias'));
