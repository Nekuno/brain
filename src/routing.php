<?php

// User routes
$app->get('/users', 'users.controller:indexAction');
$app->post('/users', 'users.controller:addAction');

$app->get('/users/{id}', 'users.controller:showAction')->value('id', null);
$app->delete('/users/{id}', 'users.controller:deleteAction')->value('id', null);

$app->get('/users/{id}/matching', 'users.controller:getUserRecommendationAction')
    ->value('type', 'answers');
$app->get('/users/{id1}/matching/{id2}', 'users.controller:getMatchingAction')
    ->value('type', 'answers');

$app->get('/users/{id}/content', 'users.controller:getContentRecommendationAction');

// Question routes
$app->post('/questions/answers', 'questions.controller:answerAction');
$app->post('/questions', 'questions.controller:addAction');

// Content routes
$app->post('/add/links', 'fetch.controller:addLink');
$app->get('/fetch/links', 'fetch.controller:fetchLinksAction')->value('userId', null)->value('resource', null);
