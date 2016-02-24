<?php

/**
 * Social routes
 */

$social = $app['controllers_factory'];

$social->get('/users/find', 'social.users.controller:findAction');
$social->put('/users/{id}', 'social.users.controller:putAction');

$social->get('/profile/{id}', 'social.profile.controller:getAction')->value('id', null);
$social->post('/profile/{id}', 'social.profile.controller:postAction');
$social->put('/profile/{id}', 'social.profile.controller:putAction');

$social->get('/tokens/{id}', 'social.tokens.controller:getAllAction');
$social->get('/users/{id}/tokens/{resourceOwner}', 'social.tokens.controller:getAction');
$social->post('/users/{id}/tokens/{resourceOwner}', 'social.tokens.controller:postAction');
$social->put('/users/{id}/tokens/{resourceOwner}', 'social.tokens.controller:putAction');
$social->delete('/users/{id}/tokens/{resourceOwner}', 'social.tokens.controller:deleteAction');

$social->get('/users/{id}/privacy', 'social.privacy.controller:getAction')->value('id', null);
$social->get('/privacy/metadata', 'users.privacy.controller:getMetadataAction');

$social->get('/answers/compare/{id}', 'social.users.answers.controller:getUserAnswersCompareAction');
$social->post('/answers/{questionId}', 'social.users.answers.controller:updateAction');

$app->mount('/social', $social);
