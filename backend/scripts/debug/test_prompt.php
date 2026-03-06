<?php $p = App\Models\SystemPrompt::where("slug", "essay_evaluator")->first(); echo $p ? $p->content : "MISSING";
