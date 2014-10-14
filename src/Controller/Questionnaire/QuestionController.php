<?php

namespace Controller\Questionnaire;

use Model\Questionnaire\QuestionModel;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class QuestionController
{

    /**
     * Returns an unanswered question for given user
     * @param Request $request
     * @param Application $app
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function nextAction(Request $request, Application $app)
    {

        $userId = $request->query->get('userId');

        if (null === $userId) {
            return $app->json(array(), 400);
        }

        /** @var QuestionModel $model */
        $model = $app['questionnaire.questions.model'];
        $result = $model->getNextByUser($userId);

        $question = array();

        foreach ($result as $row) {
            $question['id'] = $row['next']->getId();
            $question['text'] = $row['next']->getProperty('text');

            foreach ($row['nextAnswers'] as $answer) {
                $question['answers'][$answer->getId()] = $answer->getProperty('text');
            }
        }

        if(!empty($question)){
            return $app->json($question, 200);
        } else {
            return $app->json(array(), 404);
        }
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function createAction(Request $request, Application $app)
    {

        $data = $request->request->all();

        if (false === $this->isValidDataForCreateQuestion($data)) {
            return $app->json(array('Bad data passed'), 400);
        }

        try {
            /** @var QuestionModel $model */
            $model = $app['questionnaire.questions.model'];
            $result = $model->create($data);
            if(null !== $result){
                return $app->json(array('Resource created successfully'), 201);
            }
        } catch (\Exception $e) {
            if ($app['env'] == 'dev') {
                throw $e;
            }

            return $app->json(array($e->getMessage()), 500);
        }

        $app->json(array(), 200);
    }

    private function isValidDataForCreateQuestion(array $data)
    {

        if (empty($data)) {
            return false;
        } elseif (!array_key_exists('text', $data) || !array_key_exists('answers', $data)) {
            return false;
        } elseif (!is_array($data['answers'])) {
            return false;
        } elseif (empty($data['answers'])) {
            return false;
        }

        return true;
    }

    public function skipAction(Request $request, Application $app)
    {

        $data = $request->request->all();

        try {
            /** @var QuestionModel $model */
            $model = $app['questionnaire.questions.model'];
            $model->skip($data);
        } catch (\Exception $e) {
            if ($app['env'] == 'dev') {
                throw $e;
            }

            return $app->json(array(), 500);
        }

        return $app->json(array('Question skipped successfully'), 200);
    }

    public function reportAction(Request $request, Application $app)
    {

        $data = $request->request->all();

        try {
            /** @var QuestionModel $model */
            $model = $app['questionnaire.questions.model'];
            $model->report($data);
        } catch (\Exception $e) {
            if ($app['env'] == 'dev') {
                throw $e;
            }

            return $app->json(array(), 500);
        }

        return $app->json(array('Question reported successfully'), 200);
    }

    public function statsAction(Request $request, Application $app)
    {

        $id = $request->get('id');

        try {
            /** @var QuestionModel $model */
            $model = $app['questionnaire.questions.model'];

            $stats = array();

            $result = $model->getQuestionStats($id);
            foreach ($result as $row) {
                $stats[$id]['answers'][$row['answer']] = array(
                    'id' => $row['answer'],
                    'nAnswers' => $row['nAnswers'],
                );
                $stats[$id]['totalAnswers'] += $row['nAnswers'];
                $stats[$id]['id'] = $id;
            }

            if(empty($stats)){
                return $app->json('Not question found with that ID', 404);
            }
            return $app->json($stats, 200);

        } catch (\Exception $e) {
            if ($app['env'] == 'dev') {
                throw $e;
            }

            return $app->json(array(), 500);
        }

    }
}
