<?php

namespace Controller\User;

use Model\User\ProfileModel;
use Model\User\ProfileTagModel;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class ProfileController
 * @package Controller
 */
class ProfileController
{
    /**
     * @param Request $request
     * @param Application $app
     * @return JsonResponse
     */
    public function getAction(Request $request, Application $app)
    {
        // TODO: Change with $this->getUserId() and remove Request from parameters
        $userId = $request->request->get('userId');

        $locale = $request->query->get('locale');
        /* @var $model ProfileModel */
        $model = $app['users.profile.model'];

        $profile = $model->getById($userId, $locale);

        return $app->json($profile);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return JsonResponse
     */
    public function getOtherAction(Request $request, Application $app)
    {
        $locale = $request->query->get('locale');
        $id = $request->get('id');
        /* @var $model ProfileModel */
        $model = $app['users.profile.model'];

        $profile = $model->getById($id, $locale);

        return $app->json($profile);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return JsonResponse
     */
    public function postAction(Request $request, Application $app)
    {
        // TODO: Change with $this->getUserId()
        $userId = $request->request->get('userId');

        /* @var $model ProfileModel */
        $model = $app['users.profile.model'];

        $profile = $model->create($userId, $request->request->all());

        return $app->json($profile, 201);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return JsonResponse
     */
    public function putAction(Request $request, Application $app)
    {
        // TODO: Change with $this->getUserId()
        $userId = $request->request->get('userId');

        /* @var $model ProfileModel */
        $model = $app['users.profile.model'];

        $profile = $model->update($userId, $request->request->all());

        return $app->json($profile);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return JsonResponse
     */
    public function deleteAction(Request $request, Application $app)
    {
        // TODO: Change with $this->getUserId()
        $userId = $request->request->get('userId');

        /* @var $model ProfileModel */
        $model = $app['users.profile.model'];

        $profile = $model->getById($userId);
        $model->remove($userId);

        return $app->json($profile);
    }

    /**
     * @param Application $app
     * @param Request $request
     * @return JsonResponse
     */
    public function getMetadataAction(Request $request, Application $app)
    {
        $locale = $request->query->get('locale');

        /* @var $model ProfileModel */
        $model = $app['users.profile.model'];
        $metadata = $model->getMetadata($locale);

        return $app->json($metadata);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return JsonResponse
     */
    public function getFiltersAction(Request $request, Application $app)
    {
        $locale = $request->query->get('locale');

        /* @var $model ProfileModel */
        $model = $app['users.profile.model'];
        $filters = $model->getFilters($locale);

        return $app->json($filters);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return JsonResponse
     */
    public function validateAction(Request $request, Application $app)
    {
        /* @var $model ProfileModel */
        $model = $app['users.profile.model'];

        $model->validate($request->request->all());

        return $app->json();
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws NotFoundHttpException
     */
    public function getProfileTagsAction(Request $request, Application $app)
    {

        $type = $request->get('type');
        $search = $request->get('search', '');
        $limit = $request->get('limit', 0);

        if (null === $type) {
            throw new NotFoundHttpException('type needed');
        }

        if ($search) {
            $search = urldecode($search);
        }

        /* @var $model ProfileTagModel */
        $model = $app['users.profile.tag.model'];

        $result = $model->getProfileTags($type, $search, $limit);

        return $app->json($result);
    }
}
